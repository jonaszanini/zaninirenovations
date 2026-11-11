<?php

namespace Tests\Unit\Support;

use App\Support\QuotationEditorFlash;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class QuotationEditorFlashTest extends TestCase
{
    public function test_compact_copy_shortens_linked_and_draft_messages(): void
    {
        $this->assertSame(
            'Saved and linked to this project',
            QuotationEditorFlash::compact('Standard quotation SQ-2026-0407 saved and linked to this project.')
        );
        $this->assertSame(
            'Saved and linked to this project',
            QuotationEditorFlash::compact('Basement quotation BR-1 published and linked to this project.')
        );
        $this->assertSame(
            'Saved as a draft',
            QuotationEditorFlash::compact('Standard quotation saved as draft.')
        );
        $this->assertSame(
            'Saved as a draft',
            QuotationEditorFlash::compact('Basement quotation BR-9 saved as a draft. The client\'s link is paused until you publish again.')
        );
        $this->assertSame(
            'Saved',
            QuotationEditorFlash::compact('Fence quotation FQ-9 updated (v3).')
        );
        $this->assertSame(
            'Width is required',
            QuotationEditorFlash::compact('Width is required', 'error')
        );
        $this->assertStringNotContainsString('—', QuotationEditorFlash::compact('Standard quotation SQ-1 saved and linked to this project.'));
        $this->assertStringNotContainsString('–', QuotationEditorFlash::compact('Saved as a draft'));
    }

    public function test_current_prefers_errors_then_error_warning_success(): void
    {
        $bag = new MessageBag(['client_name' => ['The client name field is required.']]);
        $fromErrors = QuotationEditorFlash::current($bag);
        $this->assertSame('error', $fromErrors['type']);
        $this->assertSame('Please fix the highlighted fields', $fromErrors['compact']);

        session()->put('error', 'Could not save the quotation.');
        $fromError = QuotationEditorFlash::current();
        $this->assertSame('error', $fromError['type']);
        $this->assertSame('Could not save the quotation.', $fromError['message']);

        session()->forget('error');
        session()->put('warning', 'Linked with a warning.');
        $fromWarning = QuotationEditorFlash::current();
        $this->assertSame('warning', $fromWarning['type']);

        session()->forget('warning');
        session()->put('success', 'Standard quotation SQ-2026-0407 saved and linked to this project.');
        $fromSuccess = QuotationEditorFlash::current();
        $this->assertSame('success', $fromSuccess['type']);
        $this->assertSame('Saved and linked to this project', $fromSuccess['compact']);
    }

    public function test_editor_request_matches_create_and_edit_not_shared_prices(): void
    {
        $create = Request::create('/admin/quotations/standard/create', 'GET');
        $create->setRouteResolver(function () use ($create) {
            $route = new Route(['GET'], 'quotations/standard/create', []);
            $route->name('admin.quotations.standard.create');
            $route->bind($create);

            return $route;
        });
        $this->assertTrue(QuotationEditorFlash::isEditorRequest($create));

        $shared = Request::create('/admin/quotations/shared-prices', 'GET');
        $shared->setRouteResolver(function () use ($shared) {
            $route = new Route(['GET'], 'quotations/shared-prices', []);
            $route->name('admin.quotations.shared-prices.edit');
            $route->bind($shared);

            return $route;
        });
        $this->assertFalse(QuotationEditorFlash::isEditorRequest($shared));
    }
}
