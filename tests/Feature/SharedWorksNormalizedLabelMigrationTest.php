<?php

namespace Tests\Feature;

use App\Models\SharedWork;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\IntegrationTestCase;

class SharedWorksNormalizedLabelMigrationTest extends IntegrationTestCase
{
    /** @var list<string> */
    protected $connectionsToTransact = [];

    public function test_migration_suffixes_accent_and_long_duplicates_then_restores_on_down(): void
    {
        $this->dropGroupLabelUnique();

        $cafe = SharedWork::query()->create([
            'key' => 'custom_mig_cafe',
            'group_key' => 'doors',
            'label' => 'Café door',
            'unit' => 'each',
            'labor_kind' => SharedWork::KIND_HOURS_EACH,
            'rate' => 1,
            'flat_rate' => 1,
            'consumers' => ['trimming'],
            'sort_order' => 9100,
        ]);
        $plain = SharedWork::query()->create([
            'key' => 'custom_mig_cafe_plain',
            'group_key' => 'doors',
            'label' => 'Cafe door',
            'unit' => 'each',
            'labor_kind' => SharedWork::KIND_HOURS_EACH,
            'rate' => 1,
            'flat_rate' => 1,
            'consumers' => ['trimming'],
            'sort_order' => 9101,
        ]);

        $long = str_repeat('b', SharedWork::LABEL_MAX);
        $longA = SharedWork::query()->create([
            'key' => 'custom_mig_long_a',
            'group_key' => 'tile',
            'label' => $long,
            'unit' => 'sf',
            'labor_kind' => SharedWork::KIND_FLAT,
            'rate' => 1,
            'flat_rate' => 1,
            'consumers' => ['tile'],
            'sort_order' => 9102,
        ]);
        $longB = SharedWork::query()->create([
            'key' => 'custom_mig_long_b',
            'group_key' => 'tile',
            'label' => $long,
            'unit' => 'sf',
            'labor_kind' => SharedWork::KIND_FLAT,
            'rate' => 1,
            'flat_rate' => 1,
            'consumers' => ['tile'],
            'sort_order' => 9103,
        ]);

        $migration = require database_path('migrations/2026_10_09_140000_add_shared_works_normalized_label_unique.php');
        $migration->up();

        $cafe->refresh();
        $plain->refresh();
        $this->assertTrue(
            $cafe->label === 'Café door' xor $plain->label === 'Cafe door'
        );
        $this->assertNotSame(
            mb_strtolower($cafe->label_normalized),
            mb_strtolower($plain->label_normalized)
        );

        $longB->refresh();
        $this->assertLessThanOrEqual(SharedWork::LABEL_MAX, mb_strlen((string) $longB->label));
        $this->assertNotSame($long, $longB->label);

        $this->assertTrue($this->indexExists('shared_works_group_label_unique'));
        $this->assertTrue(Schema::hasTable('shared_works_label_suffix_backup'));

        $suffixed = $cafe->label === 'Café door' ? $plain : $cafe;
        $kept = $cafe->label === 'Café door' ? $cafe : $plain;
        $migratedLabel = $suffixed->label;
        $suffixed->label = 'Renamed after migration';
        $suffixed->saveQuietly();

        $migration->down();
        $cafe->refresh();
        $plain->refresh();
        $longA->refresh();
        $longB->refresh();
        $suffixed->refresh();
        $kept->refresh();
        $this->assertSame('Renamed after migration', $suffixed->label);
        $this->assertContains($kept->label, ['Café door', 'Cafe door']);
        $this->assertNotSame($migratedLabel, $suffixed->label);
        $this->assertSame($long, $longA->label);
        $this->assertSame($long, $longB->label);
        $this->assertFalse($this->indexExists('shared_works_group_label_unique'));
        $this->assertFalse(Schema::hasTable('shared_works_label_suffix_backup'));

        $migration->up();
        $this->assertTrue($this->indexExists('shared_works_group_label_unique'));
    }

    private function dropGroupLabelUnique(): void
    {
        if ($this->indexExists('shared_works_group_label_unique')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->dropUnique('shared_works_group_label_unique');
            });
        }
    }

    private function indexExists(string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM `shared_works` WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
}
