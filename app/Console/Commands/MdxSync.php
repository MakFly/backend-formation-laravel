<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Formation;
use App\Support\Mdx\MdxSyncService;
use Illuminate\Console\Command;

final class MdxSync extends Command
{
    protected $signature = 'mdx:sync
                                {slug? : Slug de la formation à synchroniser}
                                {--all : Synchroniser toutes les formations}
                                {--bidirectional : Synchronisation bidirectionnelle}
                                {--export-only : Export uniquement (backend → MDX)}
                                {--import-only : Import uniquement (MDX → backend)}';

    protected $description = 'Synchroniser les formations avec les fichiers MDX du frontend';

    public function __construct(
        private readonly MdxSyncService $mdxSyncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $all = $this->option('all');
        $bidirectional = $this->option('bidirectional');
        $exportOnly = $this->option('export-only');
        $importOnly = $this->option('import-only');

        if ($all) {
            return $this->syncAllFormations($bidirectional);
        }

        if ($slug) {
            return $this->syncFormation($slug, $bidirectional, $exportOnly, $importOnly);
        }

        $this->error('Veuillez spécifier un slug ou utiliser l\'option --all');

        return self::FAILURE;
    }

    private function syncFormation(
        string $slug,
        bool $bidirectional = false,
        bool $exportOnly = false,
        bool $importOnly = false
    ): int {
        $formation = Formation::where('slug', $slug)->first();

        if (! $formation) {
            $this->error("Formation '{$slug}' non trouvée.");

            return self::FAILURE;
        }

        $this->info("Synchronisation de : {$formation->title}");

        if ($importOnly) {
            return $this->importFormation($formation);
        }

        if ($exportOnly) {
            return $this->exportFormation($formation);
        }

        if ($bidirectional) {
            $results = $this->mdxSyncService->syncBidirectional($formation);

            $this->info("✓ Exporté vers : {$results['exported']}");

            if ($results['imported'] ?? false) {
                $this->info('✓ Importé depuis MDX (modifications appliquées)');
            } else {
                $this->info('○ Aucune modification à importer');
            }

            return self::SUCCESS;
        }

        // Par défaut, export uniquement
        return $this->exportFormation($formation);
    }

    private function syncAllFormations(bool $bidirectional = false): int
    {
        $formations = Formation::all();
        $count = $formations->count();

        if ($count === 0) {
            $this->warn('Aucune formation à synchroniser.');

            return self::SUCCESS;
        }

        $this->info("Synchronisation de {$count} formation(s)...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $results = [];
        foreach ($formations as $formation) {
            if ($bidirectional) {
                $results[$formation->slug] = $this->mdxSyncService->syncBidirectional($formation);
            } else {
                $this->mdxSyncService->exportFormation($formation);
                $results[$formation->slug] = ['exported' => true];
            }
            $bar->advance();
        }

        $bar->finish();

        $exported = count(array_filter($results, fn ($r) => isset($r['exported'])));
        $imported = count(array_filter($results, fn ($r) => ($r['imported'] ?? false)));

        $this->newLine();
        $this->info("✓ Exporté : {$exported}/{$count}");

        if ($bidirectional) {
            $this->info("✓ Importé : {$imported}/{$count}");
        }

        return self::SUCCESS;
    }

    private function exportFormation(Formation $formation): int
    {
        $path = $this->mdxSyncService->exportFormation($formation);

        $this->info("✓ Exporté vers : {$path}");

        return self::SUCCESS;
    }

    private function importFormation(Formation $formation): int
    {
        $data = $this->mdxSyncService->importFormation($formation->slug);

        if (! $data) {
            $this->warn("○ Aucun fichier MDX trouvé pour '{$formation->slug}'");

            return self::SUCCESS;
        }

        $formation->update($this->mdxSyncService->mapMdxToFormation($data));
        $formation->content_mdx = $data;
        $formation->save();

        $this->info('✓ Importé depuis MDX (modifications appliquées)');

        return self::SUCCESS;
    }
}
