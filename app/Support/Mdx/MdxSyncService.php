<?php

declare(strict_types=1);

namespace App\Support\Mdx;

use App\Models\Formation;
use App\Models\Lesson;

final class MdxSyncService
{
    private const DEFAULT_MDX_PATH = '../frontend-mokshaformations/app/formations/%s.mdx';

    private string $mdxPath;

    public function __construct(?string $mdxPath = null)
    {
        $this->mdxPath = $mdxPath ?? self::DEFAULT_MDX_PATH;
    }

    public function exportFormation(Formation $formation): string
    {
        $mdx = $this->generateFormationMdx($formation);
        $path = sprintf($this->mdxPath, $formation->slug);

        // Créer le dossier si nécessaire
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($path, $mdx);

        return $path;
    }

    public function exportLesson(Lesson $lesson): string
    {
        $mdx = $this->generateLessonMdx($lesson);
        $path = sprintf($this->mdxPath, $lesson->slug);

        file_put_contents($path, $mdx);

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function importFormation(string $slug): ?array
    {
        $path = sprintf($this->mdxPath, $slug);

        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $this->parseMdxFrontmatter($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncBidirectional(Formation $formation): array
    {
        $results = [];

        // Export Formation → MDX
        $exportPath = $this->exportFormation($formation);
        $results['exported'] = $exportPath;

        // Import MDX → Formation (si des changements existent)
        $imported = $this->importFormation($formation->slug);
        if ($imported) {
            $formation->update($this->mapMdxToFormation($imported));
            $results['imported'] = true;
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAllFormations(): array
    {
        $results = [];
        $formations = Formation::all();

        foreach ($formations as $formation) {
            $results[$formation->slug] = $this->syncBidirectional($formation);
        }

        return $results;
    }

    private function generateFormationMdx(Formation $formation): string
    {
        $frontmatter = $this->generateFormationFrontmatter($formation);
        $modules = $formation->modules()->with('lessons')->get();

        $content = "---\n";
        $content .= trim($frontmatter->toYaml())."\n";
        $content .= "---\n\n";
        $content .= $formation->description ?? '';

        foreach ($modules as $module) {
            $content .= "\n## {$module->title}\n\n";
            $content .= $module->description ?? '';

            foreach ($module->lessons as $lesson) {
                $content .= "\n### {$lesson->title}\n\n";
                $content .= $lesson->content ?? '';
            }
        }

        return $content;
    }

    private function generateLessonMdx(Lesson $lesson): string
    {
        $frontmatter = [
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'summary' => $lesson->summary,
            'formation_id' => $lesson->formation_id,
            'module_id' => $lesson->module_id,
            'duration_seconds' => $lesson->duration_seconds,
            'is_preview' => $lesson->is_preview,
            'order' => $lesson->order,
        ];

        $content = "---\n";
        $content .= trim($this->arrayToYaml($frontmatter))."\n";
        $content .= "---\n\n";
        $content .= $lesson->content ?? '';

        return $content;
    }

    private function generateFormationFrontmatter(Formation $formation): object
    {
        return new class($formation->title, $formation->slug, $formation->summary, $formation->pricing_tier->value, (float) $formation->price, $formation->mode, $formation->thumbnail, $formation->video_trailer, $formation->tags ?? [], $formation->objectives ?? [], $formation->requirements ?? [], $formation->target_audience ?? [], $formation->language, $formation->subtitles ?? [], $formation->difficulty_level, $formation->duration_hours, $formation->duration_minutes, $formation->instructor_name, $formation->instructor_title, $formation->instructor_avatar, $formation->instructor_bio, $formation->is_published, $formation->is_featured)
        {
            /**
             * @param array<int, string> $tags
             * @param array<int, string> $objectives
             * @param array<int, string> $requirements
             * @param array<int, string> $target_audience
             * @param array<int, string> $subtitles
             */
            public function __construct(
                public readonly string $title,
                public readonly string $slug,
                public readonly ?string $summary,
                public readonly string $pricing_tier,
                public readonly float $price,
                public readonly string $mode,
                public readonly ?string $thumbnail,
                public readonly ?string $video_trailer,
                public readonly array $tags,
                public readonly array $objectives,
                public readonly array $requirements,
                public readonly array $target_audience,
                public readonly string $language,
                public readonly array $subtitles,
                public readonly string $difficulty_level,
                public readonly ?int $duration_hours,
                public readonly ?int $duration_minutes,
                public readonly ?string $instructor_name,
                public readonly ?string $instructor_title,
                public readonly ?string $instructor_avatar,
                public readonly ?string $instructor_bio,
                public readonly bool $is_published,
                public readonly bool $is_featured,
            ) {}

            public function toYaml(): string
            {
                $yaml = "title: {$this->title}\n";
                $yaml .= "slug: {$this->slug}\n";

                if ($this->summary) {
                    $yaml .= "summary: {$this->summary}\n";
                }

                $yaml .= "pricing_tier: {$this->pricing_tier}\n";
                $yaml .= "price: {$this->price}\n";
                $yaml .= "mode: {$this->mode}\n";

                if ($this->thumbnail) {
                    $yaml .= "thumbnail: {$this->thumbnail}\n";
                }

                if ($this->video_trailer) {
                    $yaml .= "video_trailer: {$this->video_trailer}\n";
                }

                if (! empty($this->tags)) {
                    $yaml .= 'tags: ['.implode(', ', array_map(fn ($t) => "\"{$t}\"", $this->tags))."]\n";
                }

                if (! empty($this->objectives)) {
                    $yaml .= "objectives:\n";
                    foreach ($this->objectives as $obj) {
                        $yaml .= "  - {$obj}\n";
                    }
                }

                if (! empty($this->requirements)) {
                    $yaml .= "requirements:\n";
                    foreach ($this->requirements as $req) {
                        $yaml .= "  - {$req}\n";
                    }
                }

                if (! empty($this->target_audience)) {
                    $yaml .= "target_audience:\n";
                    foreach ($this->target_audience as $aud) {
                        $yaml .= "  - {$aud}\n";
                    }
                }

                $yaml .= "language: {$this->language}\n";

                if (! empty($this->subtitles)) {
                    $yaml .= 'subtitles: ['.implode(', ', $this->subtitles)."]\n";
                }

                $yaml .= "difficulty_level: {$this->difficulty_level}\n";

                if ($this->duration_hours) {
                    $yaml .= "duration_hours: {$this->duration_hours}\n";
                }

                if ($this->duration_minutes) {
                    $yaml .= "duration_minutes: {$this->duration_minutes}\n";
                }

                if ($this->instructor_name) {
                    $yaml .= "instructor_name: {$this->instructor_name}\n";
                    $yaml .= "instructor_title: {$this->instructor_title}\n";
                }

                if ($this->instructor_avatar) {
                    $yaml .= "instructor_avatar: {$this->instructor_avatar}\n";
                }

                if ($this->instructor_bio) {
                    $yaml .= "instructor_bio: {$this->instructor_bio}\n";
                }

                $yaml .= 'is_published: '.($this->is_published ? 'true' : 'false')."\n";
                $yaml .= 'is_featured: '.($this->is_featured ? 'true' : 'false')."\n";

                return $yaml;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMdxFrontmatter(string $content): array
    {
        preg_match('/^---(.*?)---(.*)$/s', $content, $matches);

        if (count($matches) < 3) {
            return [];
        }

        $yaml = trim($matches[1]);
        $body = trim($matches[2]);

        return [
            'frontmatter' => $this->yamlToArray($yaml),
            'content' => $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function yamlToArray(string $yaml): array
    {
        $lines = explode("\n", $yaml);
        $result = [];
        $currentKey = null;
        $listValues = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                continue;
            }

            // Check if it's a list item (starts with "- ")
            if (str_starts_with($trimmed, '- ')) {
                if ($currentKey !== null) {
                    $listValues[] = trim(substr($trimmed, 2));
                }

                continue;
            }

            // If we were collecting list items, save them
            if ($currentKey !== null && ! empty($listValues)) {
                $result[$currentKey] = $listValues;
                $listValues = [];
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Handle arrays with brackets
                if (str_starts_with($value, '[')) {
                    $value = trim($value, '[]');
                    $result[$key] = array_map(fn ($v) => trim(trim($v), '"\''), explode(',', $value));
                    $currentKey = null;
                }
                // Empty value might be followed by list items
                elseif (empty($value)) {
                    $currentKey = $key;
                    $listValues = [];
                }
                // Handle booleans
                elseif (in_array($value, ['true', 'false'])) {
                    $result[$key] = $value === 'true';
                    $currentKey = null;
                }
                // Handle numbers
                elseif (is_numeric($value)) {
                    $result[$key] = strpos($value, '.') !== false ? (float) $value : (int) $value;
                    $currentKey = null;
                } else {
                    $result[$key] = $value;
                    $currentKey = null;
                }
            }
        }

        // Don't forget the last list
        if ($currentKey !== null && ! empty($listValues)) {
            $result[$currentKey] = $listValues;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToYaml(array $data): string
    {
        $yaml = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$key}: [".implode(', ', array_map(fn ($v) => "\"{$v}\"", $value))."]\n";
            } elseif (is_bool($value)) {
                $yaml .= "{$key}: ".($value ? 'true' : 'false')."\n";
            } elseif (is_numeric($value)) {
                $yaml .= "{$key}: {$value}\n";
            } else {
                $yaml .= "{$key}: {$value}\n";
            }
        }

        return $yaml;
    }

    /**
     * @param array<string, mixed> $mdxData
     * @return array<string, mixed>
     */
    public function mapMdxToFormation(array $mdxData): array
    {
        $frontmatter = $mdxData['frontmatter'] ?? [];

        return [
            'title' => $frontmatter['title'] ?? null,
            'slug' => $frontmatter['slug'] ?? null,
            'summary' => $frontmatter['summary'] ?? null,
            'pricing_tier' => $frontmatter['pricing_tier'] ?? 'free',
            'price' => $frontmatter['price'] ?? 0,
            'mode' => $frontmatter['mode'] ?? 'online',
            'thumbnail' => $frontmatter['thumbnail'] ?? null,
            'video_trailer' => $frontmatter['video_trailer'] ?? null,
            'tags' => $frontmatter['tags'] ?? null,
            'objectives' => $frontmatter['objectives'] ?? null,
            'requirements' => $frontmatter['requirements'] ?? null,
            'target_audience' => $frontmatter['target_audience'] ?? null,
            'language' => $frontmatter['language'] ?? 'fr',
            'subtitles' => $frontmatter['subtitles'] ?? null,
            'difficulty_level' => $frontmatter['difficulty_level'] ?? 'beginner',
            'duration_hours' => $frontmatter['duration_hours'] ?? null,
            'duration_minutes' => $frontmatter['duration_minutes'] ?? null,
            'instructor_name' => $frontmatter['instructor_name'] ?? null,
            'instructor_title' => $frontmatter['instructor_title'] ?? null,
            'instructor_avatar' => $frontmatter['instructor_avatar'] ?? null,
            'instructor_bio' => $frontmatter['instructor_bio'] ?? null,
            'is_published' => $frontmatter['is_published'] ?? false,
            'is_featured' => $frontmatter['is_featured'] ?? false,
            'description' => $mdxData['content'] ?? null,
            'content_mdx' => $mdxData,
        ];
    }
}
