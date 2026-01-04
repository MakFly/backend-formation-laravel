<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminFormationController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\LessonResourceController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Module Routes (nested under formations)
Route::prefix('formations/{formationId}/modules')->group(function () {
    Route::get('/', [ModuleController::class, 'index']);
    Route::post('/', [ModuleController::class, 'store']);
    Route::get('/{id}', [ModuleController::class, 'show']);
    Route::patch('/{id}', [ModuleController::class, 'update']);
    Route::delete('/{id}', [ModuleController::class, 'destroy']);
    Route::post('/reorder', [ModuleController::class, 'reorder']);
    Route::post('/{id}/publish', [ModuleController::class, 'publish']);
    Route::post('/{id}/unpublish', [ModuleController::class, 'unpublish']);
    Route::get('/{id}/lessons', [ModuleController::class, 'lessons']);
});

// Lesson Resource Routes (nested under lessons)
Route::prefix('lessons/{lessonId}/resources')->group(function () {
    Route::get('/', [LessonResourceController::class, 'index']);
    Route::post('/', [LessonResourceController::class, 'store']);
    Route::get('/{id}', [LessonResourceController::class, 'show']);
    Route::patch('/{id}', [LessonResourceController::class, 'update']);
    Route::delete('/{id}', [LessonResourceController::class, 'destroy']);
    Route::post('/reorder', [LessonResourceController::class, 'reorder']);
});

// Lesson Routes
Route::prefix('lessons')->group(function () {
    Route::get('/', [AdminLessonController::class, 'index']);
    Route::post('/', [AdminLessonController::class, 'store']);
    Route::get('/{id}', [AdminLessonController::class, 'show']);
    Route::patch('/{id}', [AdminLessonController::class, 'update']);
    Route::delete('/{id}', [AdminLessonController::class, 'destroy']);
    Route::post('/reorder', [AdminLessonController::class, 'reorder']);
    Route::post('/{id}/publish', [AdminLessonController::class, 'publish']);
    Route::post('/{id}/unpublish', [AdminLessonController::class, 'unpublish']);
    Route::post('/{id}/content', [AdminLessonController::class, 'uploadContent']);
    Route::post('/{id}/thumbnail', [AdminLessonController::class, 'uploadThumbnail']);
    Route::get('/{id}/resources', [AdminLessonController::class, 'resources']);
});

// Enrollment Routes
Route::prefix('enrollments')->group(function () {
    Route::get('/', [EnrollmentController::class, 'index']);
    Route::post('/', [EnrollmentController::class, 'store']);
    Route::get('/{id}', [EnrollmentController::class, 'show']);

    // Customer enrollments
    Route::get('/customers/{customerId}', [EnrollmentController::class, 'getByCustomer']);

    // Formation enrollments
    Route::get('/formations/{formationId}', [EnrollmentController::class, 'getByFormation']);

    // Actions
    Route::post('/{id}/validate', [EnrollmentController::class, 'validate']);
    Route::post('/{id}/cancel', [EnrollmentController::class, 'cancel']);

    // Lesson access check
    Route::get('/{enrollmentId}/lessons/{lessonId}/access', [EnrollmentController::class, 'checkLessonAccess']);
});

// Progress Routes
Route::prefix('progress')->group(function () {
    Route::get('/', [ProgressController::class, 'index']);
    Route::get('/{id}', [ProgressController::class, 'show']);

    // Enrollment progress
    Route::get('/enrollments/{enrollmentId}', [ProgressController::class, 'getByEnrollment']);

    // Lesson actions
    Route::post('/enrollments/{enrollmentId}/lessons/{lessonId}/start', [ProgressController::class, 'start']);
    Route::patch('/enrollments/{enrollmentId}/lessons/{lessonId}', [ProgressController::class, 'update']);
    Route::post('/enrollments/{enrollmentId}/lessons/{lessonId}/complete', [ProgressController::class, 'complete']);
    Route::post('/enrollments/{enrollmentId}/lessons/{lessonId}/favorite', [ProgressController::class, 'toggleFavorite']);
    Route::patch('/enrollments/{enrollmentId}/lessons/{lessonId}/notes', [ProgressController::class, 'updateNotes']);
});

// Certificate Routes
Route::prefix('certificates')->group(function () {
    Route::get('/', [CertificateController::class, 'index']);
    Route::get('/{id}', [CertificateController::class, 'show']);

    // Customer certificates
    Route::get('/customers/{customerId}', [CertificateController::class, 'getByCustomer']);

    // Formation certificates
    Route::get('/formations/{formationId}', [CertificateController::class, 'getByFormation']);

    // Generate certificate for enrollment
    Route::post('/enrollments/{enrollmentId}/generate', [CertificateController::class, 'generate']);

    // Certificate actions
    Route::post('/{id}/revoke', [CertificateController::class, 'revoke']);
    Route::post('/{id}/regenerate', [CertificateController::class, 'regenerate']);
    Route::get('/{id}/download', [CertificateController::class, 'download']);

    // Public verification endpoints (no auth required)
    Route::get('/verify/{code}', [CertificateController::class, 'verify']);
    Route::get('/verify/number/{number}', [CertificateController::class, 'verifyByNumber']);
});

// Payment Routes
Route::prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{id}', [PaymentController::class, 'show']);
    Route::post('/{id}/refund', [PaymentController::class, 'refund']);

    // Stripe Checkout redirect handlers (no auth required)
    Route::get('/success', [PaymentController::class, 'success']);
    Route::get('/cancel', [PaymentController::class, 'cancel']);
});

// Webhook Routes (no auth required, signature verified)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe']);

// Admin Routes (requires authentication, admin role check should be added via middleware)
Route::prefix('admin')->group(function () {
    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/revenue', [DashboardController::class, 'revenue']);
        Route::get('/popular-formations', [DashboardController::class, 'popularFormations']);
    });

    // Customer Management
    Route::prefix('customers')->group(function () {
        Route::get('/', [AdminCustomerController::class, 'index']);
        Route::post('/', [AdminCustomerController::class, 'store']);
        Route::get('/{id}', [AdminCustomerController::class, 'show']);
        Route::patch('/{id}', [AdminCustomerController::class, 'update']);
        Route::delete('/{id}', [AdminCustomerController::class, 'destroy']);

        // Customer relations
        Route::get('/{id}/enrollments', [AdminCustomerController::class, 'enrollments']);
        Route::get('/{id}/payments', [AdminCustomerController::class, 'payments']);
        Route::get('/{id}/stats', [AdminCustomerController::class, 'stats']);
    });

    // Formation Management
    Route::prefix('formations')->group(function () {
        Route::get('/', [AdminFormationController::class, 'index']);
        Route::post('/', [AdminFormationController::class, 'store']);
        Route::get('/{id}', [AdminFormationController::class, 'show']);
        Route::patch('/{id}', [AdminFormationController::class, 'update']);
        Route::delete('/{id}', [AdminFormationController::class, 'destroy']);

        // Formation actions
        Route::post('/{id}/duplicate', [AdminFormationController::class, 'duplicate']);
        Route::post('/{id}/publish', [AdminFormationController::class, 'publish']);
        Route::post('/{id}/unpublish', [AdminFormationController::class, 'unpublish']);

        // Formation relations
        Route::get('/{id}/enrollments', [AdminFormationController::class, 'enrollments']);
        Route::get('/{id}/payments', [AdminFormationController::class, 'payments']);
        Route::get('/{id}/stats', [AdminFormationController::class, 'stats']);
    });

    // Order Management
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/stats', [AdminOrderController::class, 'stats']);
        Route::get('/{id}', [AdminOrderController::class, 'show']);
        Route::post('/{id}/refund', [AdminOrderController::class, 'refund']);
    });
});
