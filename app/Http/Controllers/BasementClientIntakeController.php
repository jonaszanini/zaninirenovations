<?php

namespace App\Http\Controllers;

use App\Models\BasementQuotation;
use App\Services\BasementClientIntakeMapper;
use App\Services\BasementClientIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BasementClientIntakeController extends Controller
{
    public function show(string $token, BasementClientIntakeMapper $mapper): View
    {
        $quote = $this->findQuote($token);
        if ($quote === null) {
            return view('site.basement_intake.unavailable', [
                'reason' => 'missing',
            ]);
        }
        if (! $quote->supportsClientIntake()) {
            return view('site.basement_intake.unavailable', [
                'reason' => 'preset',
            ]);
        }
        if ($quote->isClientIntakeSubmitted()) {
            return view('site.basement_intake.thanks', [
                'quote' => $quote,
                'already' => ! session('intake_submitted'),
            ]);
        }

        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $defaults = $mapper->formDefaults($inputs);
        $preset = (string) $defaults['scope_preset'];

        return view('site.basement_intake.show', [
            'quote' => $quote,
            'form' => $defaults,
            'preset' => $preset,
            'isBathroomOnly' => $preset === 'bathroom',
        ]);
    }

    public function store(
        Request $request,
        string $token,
        BasementClientIntakeService $intake
    ): RedirectResponse|View {
        $quote = $this->findQuote($token);
        if ($quote === null) {
            return view('site.basement_intake.unavailable', [
                'reason' => 'missing',
            ]);
        }
        if ($quote->isClientIntakeSubmitted()) {
            return redirect()->route('public.basement-intake.show', ['token' => $token]);
        }

        $this->validateAnswers($request, $quote);

        try {
            $intake->submit($quote, $request->all());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('public.basement-intake.show', ['token' => $token])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('public.basement-intake.show', ['token' => $token])
            ->with('intake_submitted', true);
    }

    private function findQuote(string $token): ?BasementQuotation
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return BasementQuotation::query()->where('client_intake_token', $token)->first();
    }

    private function validateAnswers(Request $request, BasementQuotation $quote): void
    {
        $preset = \App\Services\BasementQuotationCalculator::normalizePreset(
            is_array($quote->inputs) ? ($quote->inputs['scope_preset'] ?? null) : null
        );
        $rules = [
            'area_entry_mode' => ['required', 'in:sqft,dimensions'],
            'area_sqft' => ['nullable', 'numeric', 'min:0'],
            'area_length_ft' => ['nullable', 'numeric', 'min:0'],
            'area_width_ft' => ['nullable', 'numeric', 'min:0'],
            'include_bathroom' => ['nullable'],
            'bath_type' => ['nullable', 'in:full,half'],
            'sink' => ['nullable', 'in:vanity,pedestal,none'],
            'tile_floor_finish' => ['nullable', 'in:tile,vinyl'],
            'tile_shower_finish' => ['nullable', 'in:tile,vinyl'],
            'flooring_type' => ['nullable', 'in:lvp,laminate,tile,carpet,epoxy,none'],
            'lighting_type' => ['nullable', 'in:spot,fixture,both'],
            'ceiling_type' => ['nullable', 'in:drywall,drop,none,paint'],
            'paint_colors' => ['nullable', 'in:1,2'],
        ];
        $request->validate($rules);

        $mode = $request->input('area_entry_mode');
        $sqft = (float) $request->input('area_sqft', 0);
        $len = (float) $request->input('area_length_ft', 0);
        $wid = (float) $request->input('area_width_ft', 0);
        $hasArea = $mode === 'dimensions' ? ($len > 0 && $wid > 0) : $sqft > 0;
        if (! $hasArea) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'area_sqft' => $preset === 'bathroom'
                    ? 'Please enter the bathroom size.'
                    : 'Please enter the basement size.',
            ]);
        }
    }
}
