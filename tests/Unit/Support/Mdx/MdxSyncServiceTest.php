<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Mdx;

use App\Enums\PricingTier;
use App\Models\Formation;
use App\Models\Module;
use App\Support\Mdx\MdxSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MdxSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_MDX_DIR = __DIR__ . '/../../../storage/test_mdx';

    private string $testMdxPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer le dossier de test
        if (!is_dir(self::TEST_MDX_DIR)) {
            mkdir(self::TEST_MDX_DIR, recursive: true);
        }

        // Définir le chemin MDX pour les tests
        $this->testMdxPath = self::TEST_MDX_DIR . '/%s.mdx';
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

    private function createTestService(): MdxSyncService
    {
        return new MdxSyncService($this->testMdxPath);
    }

    #[Test]
    public function it_exports_a_formation_to_mdx(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Laravel pour Débutants',
            'slug' => 'laravel-pour-debutants',
            'summary' => 'Apprenez Laravel de zéro',
            'pricing_tier' => PricingTier::STANDARD,
            'price' => 49.99,
            'mode' => 'online',
            'description' => 'Une formation complète',
            'tags' => ['laravel', 'php', 'web'],
            'objectives' => ['Maîtriser Laravel', 'Créer des API'],
            'requirements' => ['Connaître PHP', 'HTML de base'],
            'target_audience' => ['Débutants', 'Développeurs'],
            'language' => 'fr',
            'subtitles' => ['en', 'es'],
            'difficulty_level' => 'beginner',
            'duration_hours' => 10,
            'duration_minutes' => 30,
            'instructor_name' => 'John Doe',
            'instructor_title' => 'Senior Developer',
            'instructor_bio' => 'Expert Laravel depuis 2015',
            'is_published' => true,
            'is_featured' => true,
        ]);

        $module = Module::factory()->create([
            'formation_id' => $formation->id,
            'title' => 'Introduction',
            'description' => 'Module introductif',
            'order' => 1,
        ]);

        $service = $this->createTestService();
        $path = $service->exportFormation($formation);

        // Vérifier que le fichier existe
        $this->assertFileExists($path);
        $this->assertStringEndsWith('laravel-pour-debutants.mdx', $path);

        // Vérifier le contenu
        $content = file_get_contents($path);
        $this->assertStringContainsString('title: Laravel pour Débutants', $content);
        $this->assertStringContainsString('slug: laravel-pour-debutants', $content);
        $this->assertStringContainsString('pricing_tier: standard', $content);
        $this->assertStringContainsString('price: 49.99', $content);
        $this->assertStringContainsString('summary: Apprenez Laravel de zéro', $content);
        $this->assertStringContainsString('tags: ["laravel", "php", "web"]', $content);
        $this->assertStringContainsString('objectives:', $content);
        $this->assertStringContainsString('- Maîtriser Laravel', $content);
        $this->assertStringContainsString('requirements:', $content);
        $this->assertStringContainsString('- Connaître PHP', $content);
        $this->assertStringContainsString('language: fr', $content);
        $this->assertStringContainsString('difficulty_level: beginner', $content);
        $this->assertStringContainsString('duration_hours: 10', $content);
        $this->assertStringContainsString('instructor_name: John Doe', $content);
        $this->assertStringContainsString('is_published: true', $content);
        $this->assertStringContainsString('## Introduction', $content);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Test Formation',
            'slug' => 'test-formation',
        ]);

        $nonExistentDir = self::TEST_MDX_DIR . '/nested/dir';
        $customPath = $nonExistentDir . '/%s.mdx';
        $service = new MdxSyncService($customPath);

        $path = $service->exportFormation($formation);

        // Vérifier que le dossier a été créé
        $this->assertDirectoryExists($nonExistentDir);
        $this->assertFileExists($path);

        // Nettoyer
        $files = glob($nonExistentDir . '/*.mdx');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($nonExistentDir);
        rmdir(dirname($nonExistentDir));
    }

    #[Test]
    public function it_imports_mdx_to_formation_data(): void
    {
        // Créer un fichier MDX de test
        $mdxContent = "---\n";
        $mdxContent .= "title: Laravel Advanced\n";
        $mdxContent .= "slug: laravel-advanced\n";
        $mdxContent .= "summary: Advanced Laravel concepts\n";
        $mdxContent .= "pricing_tier: premium\n";
        $mdxContent .= "price: 99.99\n";
        $mdxContent .= "mode: online\n";
        $mdxContent .= "thumbnail: /images/laravel.jpg\n";
        $mdxContent .= "tags: [\"laravel\", \"advanced\"]\n";
        $mdxContent .= "objectives:\n";
        $mdxContent .= "  - Master advanced concepts\n";
        $mdxContent .= "  - Build complex applications\n";
        $mdxContent .= "requirements:\n";
        $mdxContent .= "  - Laravel basics\n";
        $mdxContent .= "target_audience:\n";
        $mdxContent .= "  - Experienced developers\n";
        $mdxContent .= "language: en\n";
        $mdxContent .= "subtitles: [\"fr\", \"es\"]\n";
        $mdxContent .= "difficulty_level: advanced\n";
        $mdxContent .= "duration_hours: 20\n";
        $mdxContent .= "duration_minutes: 45\n";
        $mdxContent .= "instructor_name: Jane Smith\n";
        $mdxContent .= "instructor_title: Laravel Expert\n";
        $mdxContent .= "instructor_avatar: /avatars/jane.jpg\n";
        $mdxContent .= "instructor_bio: 10 years of experience\n";
        $mdxContent .= "is_published: true\n";
        $mdxContent .= "is_featured: false\n";
        $mdxContent .= "---\n\n";
        $mdxContent .= "This is the course description.\n";

        $testFile = self::TEST_MDX_DIR . '/laravel-advanced.mdx';
        file_put_contents($testFile, $mdxContent);

        $service = $this->createTestService();
        $data = $service->importFormation('laravel-advanced');

        $this->assertNotNull($data);
        $this->assertArrayHasKey('frontmatter', $data);
        $this->assertArrayHasKey('content', $data);

        $frontmatter = $data['frontmatter'];
        $this->assertEquals('Laravel Advanced', $frontmatter['title']);
        $this->assertEquals('laravel-advanced', $frontmatter['slug']);
        $this->assertEquals('premium', $frontmatter['pricing_tier']);
        $this->assertEquals(99.99, $frontmatter['price']);
        $this->assertEquals('online', $frontmatter['mode']);
        $this->assertEquals(['laravel', 'advanced'], $frontmatter['tags']);
        $this->assertEquals(['Master advanced concepts', 'Build complex applications'], $frontmatter['objectives']);
        $this->assertEquals('en', $frontmatter['language']);
        $this->assertTrue($frontmatter['is_published']);
        $this->assertFalse($frontmatter['is_featured']);

        $content = $data['content'];
        $this->assertEquals('This is the course description.', trim($content));
    }

    #[Test]
    public function it_returns_null_when_mdx_file_not_found(): void
    {
        $service = $this->createTestService();
        $data = $service->importFormation('non-existent');

        $this->assertNull($data);
    }

    #[Test]
    public function it_performs_bidirectional_sync(): void
    {
        $formation = Formation::factory()->create([
            'title' => 'Sync Test',
            'slug' => 'sync-test',
            'summary' => 'Test sync',
            'price' => 29.99,
        ]);

        $service = $this->createTestService();
        $results = $service->syncBidirectional($formation);

        $this->assertArrayHasKey('exported', $results);
        $this->assertArrayHasKey('imported', $results);
        $this->assertStringContainsString('sync-test.mdx', $results['exported']);
        $this->assertTrue($results['imported']);
    }

    #[Test]
    public function it_maps_mdx_data_to_formation_attributes(): void
    {
        $mdxData = [
            'frontmatter' => [
                'title' => 'Test Formation',
                'slug' => 'test-formation',
                'summary' => 'Test summary',
                'pricing_tier' => 'basic',
                'price' => 19.99,
                'mode' => 'hybrid',
                'thumbnail' => '/images/test.jpg',
                'video_trailer' => 'https://youtube.com/watch?v=test',
                'tags' => ['test', 'demo'],
                'objectives' => ['Learn testing'],
                'requirements' => ['PHP knowledge'],
                'target_audience' => ['Beginners'],
                'language' => 'fr',
                'subtitles' => ['en', 'de'],
                'difficulty_level' => 'intermediate',
                'duration_hours' => 5,
                'duration_minutes' => 15,
                'instructor_name' => 'Test Instructor',
                'instructor_title' => 'Test Title',
                'instructor_avatar' => '/avatars/test.jpg',
                'instructor_bio' => 'Test bio',
                'is_published' => true,
                'is_featured' => false,
            ],
            'content' => 'Test content body',
        ];

        $reflection = new \ReflectionClass($this->createTestService());
        $method = $reflection->getMethod('mapMdxToFormation');
        $method->setAccessible(true);

        $mapped = $method->invoke($this->createTestService(), $mdxData);

        $this->assertEquals('Test Formation', $mapped['title']);
        $this->assertEquals('test-formation', $mapped['slug']);
        $this->assertEquals('Test summary', $mapped['summary']);
        $this->assertEquals('basic', $mapped['pricing_tier']);
        $this->assertEquals(19.99, $mapped['price']);
        $this->assertEquals('hybrid', $mapped['mode']);
        $this->assertEquals('/images/test.jpg', $mapped['thumbnail']);
        $this->assertEquals('https://youtube.com/watch?v=test', $mapped['video_trailer']);
        $this->assertEquals(['test', 'demo'], $mapped['tags']);
        $this->assertEquals(['Learn testing'], $mapped['objectives']);
        $this->assertEquals(['PHP knowledge'], $mapped['requirements']);
        $this->assertEquals(['Beginners'], $mapped['target_audience']);
        $this->assertEquals('fr', $mapped['language']);
        $this->assertEquals(['en', 'de'], $mapped['subtitles']);
        $this->assertEquals('intermediate', $mapped['difficulty_level']);
        $this->assertEquals(5, $mapped['duration_hours']);
        $this->assertEquals(15, $mapped['duration_minutes']);
        $this->assertEquals('Test Instructor', $mapped['instructor_name']);
        $this->assertEquals('Test Title', $mapped['instructor_title']);
        $this->assertEquals('/avatars/test.jpg', $mapped['instructor_avatar']);
        $this->assertEquals('Test bio', $mapped['instructor_bio']);
        $this->assertTrue($mapped['is_published']);
        $this->assertFalse($mapped['is_featured']);
        $this->assertEquals('Test content body', $mapped['description']);
    }

    #[Test]
    public function it_handles_optional_fields_in_mdx(): void
    {
        $mdxData = [
            'frontmatter' => [
                'title' => 'Minimal Formation',
                'slug' => 'minimal',
            ],
            'content' => '',
        ];

        $reflection = new \ReflectionClass($this->createTestService());
        $method = $reflection->getMethod('mapMdxToFormation');
        $method->setAccessible(true);

        $mapped = $method->invoke($this->createTestService(), $mdxData);

        $this->assertEquals('Minimal Formation', $mapped['title']);
        $this->assertEquals('minimal', $mapped['slug']);
        $this->assertEquals('free', $mapped['pricing_tier']); // Default value
        $this->assertEquals(0, $mapped['price']); // Default value
        $this->assertEquals('online', $mapped['mode']); // Default value
        $this->assertEquals('fr', $mapped['language']); // Default value
        $this->assertEquals('beginner', $mapped['difficulty_level']); // Default value
    }
}
