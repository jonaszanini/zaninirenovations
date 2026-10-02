<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PortfolioCategoryAiService
{
    private const MAX_NEW_CATEGORIES = 6;

    public function __construct(
        protected GeminiService $gemini,
        protected PortfolioAiImageCollector $images,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $googlePickerRawItems
     * @param  list<string>  $draftPaths
     * @return array{categories: list<array{id: int, name: string, created: bool}>, image_count: int}
     */
    public function suggest(
        User $user,
        string $title,
        string $description,
        array $uploads,
        array $googlePickerRawItems,
        array $draftPaths = [],
        ?Project $project = null,
    ): array {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_PORTFOLIO_CATEGORIES);
        if (! $instruction) {
            throw new RuntimeException('Portfolio category AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException('Enter a project title first.');
        }

        $description = trim($description);
        $catalog = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $images = $this->images->collect($user, $uploads, $googlePickerRawItems, $project, $draftPaths);

        $raw = $this->gemini->generateStructuredJsonWithImages(
            $instruction->instruction,
            $this->userText($title, $description, $catalog, count($images)),
            $this->responseSchema(),
            $images,
        );

        $existingIds = $this->integerList($raw['existing_category_ids'] ?? []);
        $newNames = $this->nameList($raw['new_category_names'] ?? []);

        $categories = DB::transaction(function () use ($existingIds, $newNames) {
            return $this->resolveCategories($existingIds, $newNames);
        });

        if ($categories === []) {
            throw new RuntimeException('The AI did not return any categories. Try again with a clearer title or description.');
        }

        return [
            'categories' => $categories,
            'image_count' => count($images),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectCategory>  $catalog
     */
    private function userText(string $title, string $description, $catalog, int $imageCount): string
    {
        $lines = ["### Title ###\n{$title}\n"];

        if ($description === '') {
            $lines[] = "### Description ###\n(none provided)\n";
        } else {
            $lines[] = "### Description ###\n{$description}\n";
        }

        $lines[] = "### Existing categories ###\n";
        if ($catalog->isEmpty()) {
            $lines[] = "(none yet — propose short category names that fit this project)\n";
        } else {
            foreach ($catalog as $category) {
                $lines[] = '- id '.$category->id.': '.$category->name;
            }
            $lines[] = '';
        }

        if ($imageCount > 0) {
            $lines[] = "### Photos ###\n{$imageCount} project photo(s) are attached. Use them to see materials and the type of work.\n";
        } else {
            $lines[] = "### Photos ###\nNo photos were attached. Categorize from the title and description only.\n";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<int>  $existingIds
     * @param  list<string>  $newNames
     * @return list<array{id: int, name: string, created: bool}>
     */
    private function resolveCategories(array $existingIds, array $newNames): array
    {
        $catalog = ProjectCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        $byId = $catalog->keyBy(fn (ProjectCategory $category) => (int) $category->id);

        /** @var array<int, bool> $selected created flag */
        $selected = [];

        foreach ($existingIds as $id) {
            if ($byId->has($id)) {
                $selected[$id] = false;
            }
        }

        $createdCount = 0;
        foreach ($newNames as $name) {
            $match = $this->findExisting($catalog, $name);
            if ($match !== null) {
                $selected[(int) $match->id] = $selected[(int) $match->id] ?? false;

                continue;
            }

            if ($createdCount >= self::MAX_NEW_CATEGORIES) {
                continue;
            }

            $category = $this->createCategory($name);
            $catalog->push($category);
            $selected[(int) $category->id] = true;
            $createdCount++;
        }

        $out = [];
        foreach ($selected as $id => $created) {
            $category = $created
                ? $catalog->firstWhere('id', $id)
                : $byId->get($id);
            if (! $category instanceof ProjectCategory) {
                $category = $catalog->firstWhere('id', $id);
            }
            if (! $category instanceof ProjectCategory) {
                continue;
            }

            $out[] = [
                'id' => (int) $category->id,
                'name' => $category->name,
                'created' => $created,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProjectCategory>  $catalog
     */
    private function findExisting($catalog, string $name): ?ProjectCategory
    {
        $key = $this->nameKey($name);
        $slug = Str::slug($name);

        foreach ($catalog as $category) {
            $existingKey = $this->nameKey($category->name);
            if ($existingKey === $key) {
                return $category;
            }
            if ($slug !== '' && $category->slug === $slug) {
                return $category;
            }
            if ($this->samePlural($existingKey, $key)) {
                return $category;
            }
        }

        return null;
    }

    private function createCategory(string $name): ProjectCategory
    {
        $slug = $this->uniqueSlug($name);
        if ($slug === '') {
            throw new RuntimeException('Could not create a category named "'.$name.'".');
        }

        $nextOrder = ((int) ProjectCategory::query()->max('sort_order')) + 1;

        return ProjectCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => min(32767, $nextOrder),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(substr(Str::slug($name), 0, 80), '-');
        if ($base === '') {
            return '';
        }

        $slug = $base;
        $n = 2;
        while (ProjectCategory::query()->where('slug', $slug)->exists()) {
            $suffix = '-'.$n;
            $slug = substr($base, 0, 80 - strlen($suffix)).$suffix;
            $n++;
            if ($n > 50) {
                return '';
            }
        }

        return $slug;
    }

    private function samePlural(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right.'s' || $right === $left.'s';
    }

    private function nameKey(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_strtolower($name);
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_int($id) || (is_string($id) && preg_match('/^\d+$/', $id) === 1)) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function nameList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $names = [];
        $seen = [];
        foreach ($value as $name) {
            if (! is_string($name)) {
                continue;
            }
            $clean = $this->cleanName($name);
            if ($clean === null) {
                continue;
            }
            $key = $this->nameKey($clean);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $clean;
        }

        return $names;
    }

    private function cleanName(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = trim($name, " \t.");
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return null;
        }
        if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s&\'’\-\/,]{0,79}$/u', $name) !== 1) {
            return null;
        }
        if (preg_match('/\p{L}/u', $name) !== 1) {
            return null;
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'existing_category_ids' => [
                    'type' => 'ARRAY',
                    'description' => 'Ids copied from the existing category list that apply to this project. Empty if none fit.',
                    'items' => ['type' => 'INTEGER'],
                ],
                'new_category_names' => [
                    'type' => 'ARRAY',
                    'description' => 'Short new category labels only when no existing category covers that part of the work. Empty if the existing list is enough.',
                    'items' => ['type' => 'STRING'],
                ],
            ],
            'required' => ['existing_category_ids', 'new_category_names'],
        ];
    }
}
