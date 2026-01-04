<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Enums\PricingTier;
use App\Models\Formation;
use App\Support\Mdx\MdxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MdxSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_MDX_DIR = __DIR__ . '/../../../../storage/test_mdx_commands';

    private string $testMdxPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer le dossier de test
        if (!is_dir(self::TEST_MDX_DIR)) {
            mkdir(self::TEST_MDX_DIR, recursive: true);
        }

        $this->testMdxPath = self::TEST_MDX_DIR . '/%s.mdx';

        // Remplacer le service dans le conteneur par une instance de test
        $this->app->instance(MdxSyncService::class, new MdxSyncService($this->testMdxPath));
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers de test
        if (is_dir(self::TEST_MDX_DIR)) {
            $files = glob(self::TEST_MDX_DIR . '/*.mdx');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_shows_error_when_no_slug_or_all_option(): void
    {
        $code = Artisan::call('mdx:sync');

        $this->assertEquals(1, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Veuillez spécifier un slug ou utiliser l\'option --all', $output);
    }

    #[Test]
    public function it_shows_error_when_formation_not_found(): void
    {
        $code = Artisan::call('mdx:sync', [
            'slug' => 'non-existent-formation',
        ]);

        $this->assertEquals(1, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Formation \'non-existent-formation\' non trouvée', $output);
    }

    #[Test]
    public function it_exports_a_single_formation(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Test Formation',
            'slug' => 'test-formation-export',
            'summary' => 'Test export',
            'pricing_tier' => PricingTier::STANDARD,
            'price' => 49.99,
        ]);

        $code = Artisan::call('mdx:sync', [
            'slug' => 'test-formation-export',
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Synchronisation de : Test Formation', $output);
        $this->assertStringContainsString('✓ Exporté vers :', $output);
        $this->assertStringContainsString('test-formation-export.mdx', $output);
    }

    #[Test]
    public function it_exports_only_with_export_only_option(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Export Only Test',
            'slug' => 'export-only-test',
        ]);

        $code = Artisan::call('mdx:sync', [
            'slug' => 'export-only-test',
            '--export-only' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('✓ Exporté vers :', $output);
    }

    #[Test]
    public function it_imports_only_with_import_only_option(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Import Only Test',
            'slug' => 'import-only-test',
        ]);

        // Créer le fichier MDX d'abord
        $mdxContent = "---\n";
        $mdxContent .= "title: Updated Title\n";
        $mdxContent .= "slug: import-only-test\n";
        $mdxContent .= "pricing_tier: premium\n";
        $mdxContent .= "price: 199.99\n";
        $mdxContent .= "mode: online\n";
        $mdxContent .= "language: fr\n";
        $mdxContent .= "difficulty_level: advanced\n";
        $mdxContent .= "is_published: true\n";
        $mdxContent .= "is_featured: false\n";
        $mdxContent .= "---\n\n";
        $mdxContent .= "Updated content\n";

        $mdxFile = self::TEST_MDX_DIR . '/import-only-test.mdx';
        file_put_contents($mdxFile, $mdxContent);

        $code = Artisan::call('mdx:sync', [
            'slug' => 'import-only-test',
            '--import-only' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('✓ Importé depuis MDX', $output);

        // Vérifier que la formation a été mise à jour
        $formation->refresh();
        $this->assertEquals('Updated Title', $formation->title);
        $this->assertEquals('premium', $formation->pricing_tier->value);
        $this->assertEquals(199.99, $formation->price);
        $this->assertEquals('Updated content', $formation->description);
    }

    #[Test]
    public function it_shows_warning_when_no_mdx_file_for_import(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'No MDX File',
            'slug' => 'no-mdx-file-test',
        ]);

        $code = Artisan::call('mdx:sync', [
            'slug' => 'no-mdx-file-test',
            '--import-only' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('○ Aucun fichier MDX trouvé', $output);
    }

    #[Test]
    public function it_performs_bidirectional_sync(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Bidirectional Test',
            'slug' => 'bidirectional-test',
            'price' => 29.99,
        ]);

        $code = Artisan::call('mdx:sync', [
            'slug' => 'bidirectional-test',
            '--bidirectional' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('✓ Exporté vers :', $output);
        $this->assertStringContainsString('✓ Importé depuis MDX', $output);
    }

    #[Test]
    public function it_syncs_all_formations(): void
    {
        Formation::factory()->create([
            'title' => 'Formation 1',
            'slug' => 'formation-1',
        ]);

        Formation::factory()->create([
            'title' => 'Formation 2',
            'slug' => 'formation-2',
        ]);

        Formation::factory()->create([
            'title' => 'Formation 3',
            'slug' => 'formation-3',
        ]);

        $code = Artisan::call('mdx:sync', [
            '--all' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Synchronisation de 3 formation(s)', $output);
        $this->assertStringContainsString('✓ Exporté : 3/3', $output);
    }

    #[Test]
    public function it_shows_warning_when_no_formations_to_sync(): void
    {
        // S'assurer qu'il n'y a pas de formations
        Formation::query()->delete();

        $code = Artisan::call('mdx:sync', [
            '--all' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Aucune formation à synchroniser', $output);
    }

    #[Test]
    public function it_syncs_all_formations_bidirectional(): void
    {
        Formation::factory()->create([
            'title' => 'Bio Test 1',
            'slug' => 'bio-test-1',
        ]);

        Formation::factory()->create([
            'title' => 'Bio Test 2',
            'slug' => 'bio-test-2',
        ]);

        $code = Artisan::call('mdx:sync', [
            '--all' => true,
            '--bidirectional' => true,
        ]);

        $this->assertEquals(0, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('Synchronisation de 2 formation(s)', $output);
        $this->assertStringContainsString('✓ Exporté : 2/2', $output);
        $this->assertStringContainsString('✓ Importé :', $output);
    }
}
