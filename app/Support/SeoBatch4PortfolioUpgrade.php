<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectSlugRedirect;
use Illuminate\Support\Facades\DB;

/**
 * SEO batch 4: short towns, fence slug uniqueness, kitchen unit-renovation
 * scope, and the Barrhaven gate-with-child photo that was on fence-4.
 *
 * Idempotent. Keyed by current or former public slugs so production
 * `php artisan migrate` can apply it once and re-run safely.
 */
class SeoBatch4PortfolioUpgrade
{
    /** Barrhaven P07 photo that was published on the Carleton Place fence-4 page. */
    public const GATE_WITH_CHILD_FILENAME = '1d0038cb-1685-4acc-8ed2-9a15a8eda9fe.webp';

    public const KITCHEN_UNIT_SCOPE = 'This was a unit renovation covering the kitchen, bathroom vanity, closets, and doors.';

    /**
     * Short town labels from the approved CSV (first section page_url slugs).
     *
     * @var array<string, string>
     */
    public const TOWNS_BY_SLUG = [
        'floating-deck-with-gravel-around' => 'Kanata',
        'kitchen-renovations' => 'Nepean',
        'flagstones-patio-renovation' => 'Toronto',
        'small-deck-for-shed' => 'Toronto',
        'deck-for-gazebo' => 'Toronto',
        'custom-shed-doors' => 'West Carleton',
        'catio' => 'South Keys',
        'fireplace-renovation' => 'Kanata',
        'lattice-fence' => 'Alta Vista',
        'backsplash-installation' => 'Kanata',
        'three-fences-at-once' => 'Carleton Place',
        'engineering-hard-wood-flooring-installation' => 'Ashton',
        'basement-flood-restoration' => 'Carleton Place',
        'fence-4' => 'Carleton Place',
        'fence-2' => 'Barrhaven',
        'fence-3' => 'Stittsville',
        'gabled-porch-roof' => 'Almonte',
        'built-in-window-storage' => 'Barrhaven',
        'custom-classic-fireplace-mantel' => 'Perth',
        'basement-finishing' => 'Barrhaven',
        'outside-stairs' => 'Carleton Place',
        'fence' => 'Carleton Place',
        '2nd-floor-and-stairs-renovation' => 'South Keys',
    ];

    /**
     * Generic Fence pages → unique visual-detail slugs.
     *
     * @var array<string, array{slug: string, title: string, meta_title: string, meta_description: string, description: string}>
     */
    public const FENCE_RENAMES = [
        'fence' => [
            'slug' => 'privacy-gate-fence-carleton-place',
            'title' => 'Privacy fence with gate in Carleton Place',
            'meta_title' => 'Privacy Fence with Gate | Carleton Place',
            'meta_description' => 'Privacy fence with a gate in Carleton Place. Photos of this completed residential fence by Zanini Renovations.',
            'description' => 'Privacy fence with a gate in Carleton Place.',
        ],
        'fence-4' => [
            'slug' => 'backyard-privacy-gate-fence-carleton-place',
            'title' => 'Backyard privacy fence with gate in Carleton Place',
            'meta_title' => 'Backyard Privacy Fence with Gate | Carleton Place',
            'meta_description' => 'Backyard privacy fence with a gate in Carleton Place. Photos of this completed backyard fence by Zanini Renovations.',
            'description' => 'Backyard privacy fence with a gate in Carleton Place.',
        ],
        'fence-2' => [
            'slug' => 'backyard-privacy-gate-fence-barrhaven',
            'title' => 'Backyard privacy fence with gate in Barrhaven',
            'meta_title' => 'Backyard Privacy Fence with Gate | Barrhaven',
            'meta_description' => 'Backyard privacy fence with a gate in Barrhaven. Photos of this completed backyard fence by Zanini Renovations.',
            'description' => 'Backyard privacy fence with a gate in Barrhaven.',
        ],
        'fence-3' => [
            'slug' => 'backyard-gate-fence-stittsville',
            'title' => 'Backyard fence and gate in Stittsville',
            'meta_title' => 'Backyard Fence and Gate | Stittsville',
            'meta_description' => 'Backyard fence and gate in Stittsville. Photos of this completed backyard fence by Zanini Renovations.',
            'description' => 'Backyard fence and gate in Stittsville.',
        ],
    ];

    public static function apply(): void
    {
        DB::transaction(function () {
            self::moveGateWithChildPhoto();
            self::applyTowns();
            self::renameFencePages();
            self::applyKitchenUnitScope();
        });
    }

    /**
     * @return list<string>
     */
    public static function slugsForKey(string $key): array
    {
        $slugs = [$key];
        if (isset(self::FENCE_RENAMES[$key]['slug'])) {
            $slugs[] = self::FENCE_RENAMES[$key]['slug'];
        }

        return array_values(array_unique($slugs));
    }

    public static function findProjectIdByKey(string $key): ?int
    {
        $slugs = self::slugsForKey($key);
        $id = DB::table('projects')->whereIn('slug', $slugs)->value('id');
        if ($id) {
            return (int) $id;
        }

        $id = DB::table('project_slug_redirects')->whereIn('slug', $slugs)->value('project_id');

        return $id ? (int) $id : null;
    }

    private static function applyTowns(): void
    {
        foreach (self::TOWNS_BY_SLUG as $slug => $town) {
            $id = self::findProjectIdByKey($slug);
            if ($id === null) {
                continue;
            }

            $current = (string) DB::table('projects')->where('id', $id)->value('town');
            if ($current === $town) {
                continue;
            }

            DB::table('projects')->where('id', $id)->update([
                'town' => $town,
                'updated_at' => now(),
            ]);
        }
    }

    private static function renameFencePages(): void
    {
        foreach (self::FENCE_RENAMES as $oldSlug => $meta) {
            $id = self::findProjectIdByKey($oldSlug);
            if ($id === null) {
                continue;
            }

            $project = Project::query()->find($id);
            if (! $project) {
                continue;
            }

            $newSlug = $meta['slug'];
            $previousSlug = (string) $project->slug;

            if ($previousSlug !== $newSlug) {
                if (Project::slugIsTaken($newSlug, $project->id)) {
                    continue;
                }

                ProjectSlugRedirect::query()
                    ->where('project_id', $project->id)
                    ->where('slug', $newSlug)
                    ->delete();

                $project->slug = $newSlug;

                if ($previousSlug !== '' && ! ProjectSlugRedirect::query()->where('slug', $previousSlug)->exists()) {
                    ProjectSlugRedirect::query()->create([
                        'project_id' => $project->id,
                        'slug' => $previousSlug,
                    ]);
                }
            }

            if (! ProjectSlugRedirect::query()->where('slug', $oldSlug)->exists()
                && $project->slug !== $oldSlug) {
                ProjectSlugRedirect::query()->create([
                    'project_id' => $project->id,
                    'slug' => $oldSlug,
                ]);
            }

            $project->title = $meta['title'];
            $project->meta_title = $meta['meta_title'];
            $project->meta_description = $meta['meta_description'];
            $project->description = $meta['description'];
            $project->save();
        }
    }

    private static function applyKitchenUnitScope(): void
    {
        $id = self::findProjectIdByKey('kitchen-renovations');
        if ($id === null) {
            return;
        }

        $description = (string) DB::table('projects')->where('id', $id)->value('description');
        if (stripos($description, 'unit renovation') !== false) {
            return;
        }

        $lead = self::KITCHEN_UNIT_SCOPE;
        $next = trim($description) === ''
            ? $lead
            : $lead."\n\n".ltrim($description);

        DB::table('projects')->where('id', $id)->update([
            'description' => $next,
            'updated_at' => now(),
        ]);
    }

    private static function moveGateWithChildPhoto(): void
    {
        $sourceId = self::findProjectIdByKey('fence-4');
        $destId = self::findProjectIdByKey('fence-2');
        if ($sourceId === null || $destId === null) {
            return;
        }

        $photo = ProjectPhoto::query()
            ->where('path', 'like', '%'.self::GATE_WITH_CHILD_FILENAME)
            ->first();

        if (! $photo) {
            return;
        }

        if ((int) $photo->project_id === $destId) {
            return;
        }

        if ((int) $photo->project_id !== $sourceId) {
            return;
        }

        $destCount = ProjectPhoto::query()->where('project_id', $destId)->count();
        if ($destCount >= 15) {
            return;
        }

        $maxSort = (int) ProjectPhoto::query()->where('project_id', $destId)->max('sort_order');

        $photo->project_id = $destId;
        $photo->sort_order = $maxSort + 1;
        $photo->save();
    }
}
