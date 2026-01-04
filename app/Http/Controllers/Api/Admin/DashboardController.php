<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Payment;
use App\Support\Http\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d'); // 7d, 30d, 90d, 1y, all
        $startDate = $this->getStartDate($period);

        // Revenue stats
        $totalRevenue = Payment::where('status', PaymentStatus::COMPLETED)
            ->when($startDate, fn ($q) => $q->where('paid_at', '>=', $startDate))
            ->sum('amount');

        $refundedAmount = Payment::whereIn('status', [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED])
            ->when($startDate, fn ($q) => $q->where('refunded_at', '>=', $startDate))
            ->sum('amount_refunded');

        $netRevenue = $totalRevenue - $refundedAmount;

        // Payment counts
        $completedPayments = Payment::where('status', PaymentStatus::COMPLETED)
            ->when($startDate, fn ($q) => $q->where('paid_at', '>=', $startDate))
            ->count();

        $refundedPayments = Payment::whereIn('status', [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED])
            ->when($startDate, fn ($q) => $q->where('refunded_at', '>=', $startDate))
            ->count();

        $pendingPayments = Payment::where('status', PaymentStatus::PENDING)
            ->count();

        // Enrollment stats
        $totalEnrollments = Enrollment::count();
        $activeEnrollments = Enrollment::where('status', EnrollmentStatus::ACTIVE)->count();
        $completedEnrollments = Enrollment::where('status', EnrollmentStatus::COMPLETED)->count();
        $newEnrollments = Enrollment::when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))->count();

        // Formation stats
        $totalFormations = Formation::where('is_published', true)->count();
        $draftFormations = Formation::where('is_published', false)->count();

        // Popular formations (by enrollment count)
        $popularFormations = Formation::withCount('enrollments')
            ->where('is_published', true)
            ->orderBy('enrollments_count', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'enrollments_count', 'price', 'pricing_tier']);

        // Recent payments
        $recentPayments = Payment::with(['customer', 'formation'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Revenue by month (last 12 months)
        $revenueByMonth = $this->getRevenueByMonth(12);

        // Conversion rate (enrollments vs payments)
        $conversionRate = $completedPayments > 0
            ? round(($activeEnrollments / $completedPayments) * 100, 2)
            : 0;

        return ApiResponseBuilder::success([
            'revenue' => [
                'total' => (float) $totalRevenue,
                'refunded' => (float) $refundedAmount,
                'net' => (float) $netRevenue,
                'by_month' => $revenueByMonth,
            ],
            'payments' => [
                'completed' => $completedPayments,
                'refunded' => $refundedPayments,
                'pending' => $pendingPayments,
            ],
            'enrollments' => [
                'total' => $totalEnrollments,
                'active' => $activeEnrollments,
                'completed' => $completedEnrollments,
                'new' => $newEnrollments,
            ],
            'formations' => [
                'published' => $totalFormations,
                'draft' => $draftFormations,
            ],
            'popular_formations' => $popularFormations->map(fn ($f) => [
                'id' => $f->id,
                'title' => $f->title,
                'slug' => $f->slug,
                'enrollments_count' => $f->enrollments_count,
                'price' => (float) $f->price,
                'pricing_tier' => $f->pricing_tier?->value,
            ]),
            'recent_payments' => $recentPayments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'status' => $p->status->value,
                'created_at' => $p->created_at->toIso8601String(),
                'customer' => [
                    'id' => $p->customer->id,
                    'email' => $p->customer->email,
                    'last_name' => $p->customer->last_name,
                ],
                'formation' => $p->formation ? [
                    'id' => $p->formation->id,
                    'title' => $p->formation->title,
                ] : null,
            ]),
            'metrics' => [
                'conversion_rate' => $conversionRate,
                'average_order_value' => $completedPayments > 0 ? round($netRevenue / $completedPayments, 2) : 0,
                'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * Get revenue analytics data.
     */
    public function revenue(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $groupBy = $request->query('group_by', 'day'); // day, week, month

        $startDate = $this->getStartDate($period);

        $query = Payment::where('status', PaymentStatus::COMPLETED)
            ->when($startDate, fn ($q) => $q->where('paid_at', '>=', $startDate));

        $isSqlite = config('database.default') === 'sqlite';

        $data = match ($groupBy) {
            'day' => $query->selectRaw('DATE(paid_at) as date, SUM(amount) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'week' => $query->when($isSqlite, fn ($q) => $q->selectRaw("strftime('%Y-%W', paid_at) as week, SUM(amount) as revenue"))
                ->when(! $isSqlite, fn ($q) => $q->selectRaw('YEARWEEK(paid_at) as week, SUM(amount) as revenue'))
                ->groupBy('week')
                ->orderBy('week')
                ->get(),
            'month' => $query->when($isSqlite, fn ($q) => $q->selectRaw("strftime('%Y-%m', paid_at) as month, SUM(amount) as revenue"))
                ->when(! $isSqlite, fn ($q) => $q->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(amount) as revenue'))
                ->when($isSqlite, fn ($q) => $q->groupBy('month'))
                ->when(! $isSqlite, fn ($q) => $q->groupBy('year', 'month'))
                ->when($isSqlite, fn ($q) => $q->orderBy('month'))
                ->when(! $isSqlite, fn ($q) => $q->orderBy('year')->orderBy('month'))
                ->get(),
            default => throw new \InvalidArgumentException('Invalid group_by parameter'),
        };

        return ApiResponseBuilder::success($data);
    }

    /**
     * Get popular formations.
     */
    public function popularFormations(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 10), 100);

        $formations = Formation::withCount(['enrollments', 'payments' => fn ($q) => $q->where('status', PaymentStatus::COMPLETED)])
            ->where('is_published', true)
            ->orderBy('enrollments_count', 'desc')
            ->limit($limit)
            ->get();

        return ApiResponseBuilder::success($formations->map(fn ($f) => [
            'id' => $f->id,
            'title' => $f->title,
            'slug' => $f->slug,
            'price' => (float) $f->price,
            'pricing_tier' => $f->pricing_tier?->value,
            'enrollments_count' => $f->enrollments_count,
            'revenue' => (float) $f->payments_sum_amount ?? 0,
        ]));
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(string $period): ?\Carbon\Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            'all' => null,
            default => now()->subDays(30),
        };
    }

    /**
     * Get revenue by month for the last N months.
     */
    private function getRevenueByMonth(int $months): array
    {
        // Use DATE_FORMAT for MySQL or strftime for SQLite
        $isSqlite = config('database.default') === 'sqlite';

        if ($isSqlite) {
            $data = Payment::where('status', PaymentStatus::COMPLETED)
                ->where('paid_at', '>=', now()->subMonths($months))
                ->selectRaw("strftime('%Y', paid_at) as year, strftime('%m', paid_at) as month, SUM(amount) as revenue")
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        } else {
            $data = Payment::where('status', PaymentStatus::COMPLETED)
                ->where('paid_at', '>=', now()->subMonths($months))
                ->selectRaw('YEAR(paid_at) as year, MONTH(paid_at) as month, SUM(amount) as revenue')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        }

        return $data->map(fn ($d) => [
            'year' => $d->year,
            'month' => $d->month,
            'revenue' => (float) $d->revenue,
        ])->toArray();
    }
}
