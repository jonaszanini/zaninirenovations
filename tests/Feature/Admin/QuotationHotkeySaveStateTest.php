<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationHotkeySaveStateTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @return array<string, array{0: string, 1: string, 2: class-string, 3: string, 4: string, 5: array<string, mixed>}>
     */
    public function quotationTypes(): array
    {
        return [
            'standard' => ['admin.quotations.standard.create', 'admin.quotations.standard.edit', StandardQuotation::class, 'st-btn-draft', 'sq-btn-save', [
                'display_title' => 'Kitchen',
                'materials' => [],
            ]],
            'basement' => ['admin.quotations.basement.create', 'admin.quotations.basement.edit', BasementQuotation::class, 'br-btn-draft', 'br-btn-publish', [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 0,
            ]],
            'millwork' => ['admin.quotations.millwork.create', 'admin.quotations.millwork.edit', MillworkQuotation::class, 'mw-btn-draft', 'mw-btn-publish', [
                'inputs' => ['pieces' => []],
                'calculation' => [],
            ]],
            'flooring' => ['admin.quotations.flooring.create', 'admin.quotations.flooring.edit', FlooringQuotation::class, 'fl-btn-draft', 'fl-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'trimming' => ['admin.quotations.trimming.create', 'admin.quotations.trimming.edit', TrimmingQuotation::class, 'tm-btn-draft', 'tm-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'painting' => ['admin.quotations.painting.create', 'admin.quotations.painting.edit', PaintingQuotation::class, 'pt-btn-draft', 'pt-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'shed' => ['admin.quotations.shed.create', 'admin.quotations.shed.edit', ShedQuotation::class, 'sh-btn-draft', 'sh-btn-publish', [
                'inputs' => [],
                'calculation' => [],
                'final_quote_total' => 0,
            ]],
            'tile' => ['admin.quotations.tile.create', 'admin.quotations.tile.edit', TileQuotation::class, 'tl-btn-draft', 'tl-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'fence' => ['admin.quotations.fence.create', 'admin.quotations.fence.edit', FenceQuotation::class, 'fq-btn-draft', 'fq-btn-save', [
                'sides' => [['length_ft' => 20]],
                'calculation' => ['materials' => [], 'totals' => []],
            ]],
            'paving' => ['admin.quotations.paving.create', 'admin.quotations.paving.edit', PavingQuotation::class, 'pq-btn-draft', 'pq-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'stairs' => ['admin.quotations.stairs.create', 'admin.quotations.stairs.edit', StairsQuotation::class, 'sq-btn-draft', 'sq-btn-publish', [
                'inputs' => [],
                'calculation' => [],
            ]],
            'low_deck' => ['admin.quotations.low-deck.create', 'admin.quotations.low-deck.edit', LowDeckQuotation::class, 'ld-btn-draft', 'ld-btn-save', [
                'width_ft' => 12,
                'length_ft' => 16,
                'calculation' => [],
            ]],
        ];
    }

    /**
     * @dataProvider quotationTypes
     *
     * @param  class-string  $model
     * @param  array<string, mixed>  $attrs
     */
    public function test_new_and_draft_quotes_hotkey_the_draft_button(
        string $createRoute,
        string $editRoute,
        string $model,
        string $draftId,
        string $saveId,
        array $attrs
    ): void {
        $this->actingAsAdmin();

        if ($createRoute !== 'admin.quotations.painting.create') {
            $createHtml = $this->get(route($createRoute))->assertOk()->getContent();
            $this->assertHotkeyOn($createHtml, $draftId, $saveId, $createRoute.' create (new)');
        }

        $draft = $this->makeQuote($model, $attrs, true);
        $draftHtml = $this->get(route($editRoute, $draft))->assertOk()->getContent();
        $this->assertHotkeyOn($draftHtml, $draftId, $saveId, $editRoute.' draft');
    }

    /**
     * @dataProvider quotationTypes
     *
     * @param  class-string  $model
     * @param  array<string, mixed>  $attrs
     */
    public function test_published_quotes_hotkey_the_save_button(
        string $createRoute,
        string $editRoute,
        string $model,
        string $draftId,
        string $saveId,
        array $attrs
    ): void {
        $this->actingAsAdmin();

        $published = $this->makeQuote($model, $attrs, false);
        $html = $this->get(route($editRoute, $published))->assertOk()->getContent();
        $this->assertHotkeyOn($html, $saveId, $draftId, $editRoute.' published');
    }

    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $attrs
     */
    private function makeQuote(string $model, array $attrs, bool $draft): object
    {
        $prefix = $draft ? 'DR' : 'PB';
        $quote = $model::query()->create(array_merge([
            'quote_number' => $prefix.'-HK-'.substr(sha1($model.($draft ? 'd' : 'p')), 0, 8),
            'client_name' => ($draft ? 'Draft' : 'Published').' Hotkey Client',
            'is_draft' => $draft,
        ], $attrs));
        $this->assertSame($draft, (bool) $quote->fresh()->is_draft);

        return $quote;
    }

    private function assertHotkeyOn(string $html, string $onId, string $offId, string $label): void
    {
        $this->assertMatchesRegularExpression(
            '/id="'.preg_quote($onId, '/').'"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="'.preg_quote($onId, '/').'"/',
            $html,
            $label.': Ctrl+S must target #'.$onId
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="'.preg_quote($offId, '/').'"[^>]*data-hotkey-save/',
            $html,
            $label.': #'.$offId.' must not have data-hotkey-save'
        );
    }
}
