<?php

namespace App\Http\Controllers;

use App\Models\BasementQuotation;
use App\Services\BasementClientIntakeService;
use App\Services\BasementQuotationCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class BasementClientIntakeController extends Controller
{
    public function show(string $token): View
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
        $preset = BasementQuotationCalculator::normalizePreset($inputs['scope_preset'] ?? null);
        $previous = $quote->client_intake_answers;

        return view('site.basement_intake.show', [
            'quote' => $quote,
            'form' => is_array($previous) ? $previous : [],
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
        $preset = BasementQuotationCalculator::normalizePreset(
            is_array($quote->inputs) ? ($quote->inputs['scope_preset'] ?? null) : null
        );
        $isBathroomOnly = $preset === 'bathroom';
        $yesNo = ['required', 'in:0,1'];

        $rules = [
            'area_entry_mode' => ['required', 'in:sqft,dimensions'],
            'area_sqft' => ['exclude_unless:area_entry_mode,sqft', 'required', 'numeric', 'gt:0'],
            'area_length_ft' => ['exclude_unless:area_entry_mode,dimensions', 'required', 'numeric', 'gt:0'],
            'area_width_ft' => ['exclude_unless:area_entry_mode,dimensions', 'required', 'numeric', 'gt:0'],
            'include_cleaning' => $yesNo,
            'include_touchups' => $yesNo,
        ];

        if ($isBathroomOnly) {
            $rules['bath_rough_in'] = $yesNo;
            $rules = array_merge($rules, $this->bathroomQuestionRules($request));
        } else {
            $rules = array_merge($rules, [
                'ceiling_height_ft' => ['required', 'numeric', 'gt:0'],
                'room_count' => ['required', 'integer', 'min:1'],
                'include_demolition' => $yesNo,
                'partition_lf' => ['required', 'numeric', 'min:0'],
                'include_bathroom' => $yesNo,
                'outlet_qty' => ['required', 'integer', 'min:0'],
                'switch_qty' => ['required', 'integer', 'min:0'],
                'lighting_type' => ['required', 'in:spot,fixture,both'],
                'light_qty' => ['required', 'integer', 'min:0'],
                'fixture_qty' => ['exclude_unless:lighting_type,both', 'required', 'integer', 'min:0'],
                'panel_upgrade' => $yesNo,
                'bath_rough_in' => $yesNo,
                'sink_laundry' => $yesNo,
                'wet_bar' => $yesNo,
                'floor_drain' => $yesNo,
                'wh_reloc' => $yesNo,
                'insulate_walls' => $yesNo,
                'insulate_ceiling' => $yesNo,
                'underslab' => $yesNo,
                'ceiling_type' => ['required', 'in:drywall,drop,none,paint'],
                'flooring_type' => ['required', 'in:lvp,laminate,tile,carpet,epoxy,none'],
                'flooring_provide' => ['exclude_if:flooring_type,none', 'required', 'in:we,client'],
                'underlayment' => ['exclude_if:flooring_type,none', 'required', 'in:0,1'],
                'subfloor_prep' => ['exclude_if:flooring_type,none', 'required', 'in:0,1'],
                'door_qty' => ['required', 'integer', 'min:0'],
                'paint_colors' => ['required', 'in:1,2'],
            ]);

            if ($this->isYes($request, 'include_bathroom')) {
                $rules = array_merge($rules, $this->bathroomQuestionRules($request));
            }
        }

        $sizeMessage = $isBathroomOnly
            ? 'Please enter the bathroom size.'
            : 'Please enter the basement size.';

        $request->validate($rules, [
            'required' => 'Please answer every question before submitting.',
            'area_sqft.gt' => $sizeMessage,
            'area_length_ft.gt' => $sizeMessage,
            'area_width_ft.gt' => $sizeMessage,
            'ceiling_height_ft.gt' => 'Please enter the ceiling height.',
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function bathroomQuestionRules(Request $request): array
    {
        $yesNo = ['required', 'in:0,1'];
        $rules = [
            'bath_type' => ['required', 'in:full,half'],
            'sink' => ['required', 'in:vanity,pedestal,none'],
            'sink_provide' => ['exclude_if:sink,none', 'required', 'in:we,client'],
            'faucet_provide' => ['exclude_if:sink,none', 'required', 'in:we,client'],
            'include_toilet' => $yesNo,
            'toilet_provide' => ['exclude_unless:include_toilet,1', 'required', 'in:we,client'],
            'include_exhaust_fan' => $yesNo,
            'include_bath_fixture' => $yesNo,
            'bath_fixture_qty' => ['exclude_unless:include_bath_fixture,1', 'required', 'integer', 'min:1'],
            'include_bath_spot' => $yesNo,
            'bath_spot_qty' => ['exclude_unless:include_bath_spot,1', 'required', 'integer', 'min:1'],
            'tile_floor_mode' => ['required', 'in:sqft,dimensions'],
            'tile_floor_sqft' => ['exclude_unless:tile_floor_mode,sqft', 'required', 'numeric', 'min:0'],
            'tile_floor_length_ft' => ['exclude_unless:tile_floor_mode,dimensions', 'required', 'numeric', 'min:0'],
            'tile_floor_width_ft' => ['exclude_unless:tile_floor_mode,dimensions', 'required', 'numeric', 'min:0'],
            'tile_floor_finish' => ['required', 'in:tile,vinyl'],
        ];

        if ($request->input('bath_type') === 'full') {
            $rules['include_shower'] = $yesNo;
            $rules['include_tub'] = $yesNo;
            $rules['shower_provide'] = ['exclude_unless:include_shower,1', 'required', 'in:we,client'];
            $rules['showerhead_provide'] = ['exclude_unless:include_shower,1', 'required', 'in:we,client'];
            $rules['tub_provide'] = ['exclude_unless:include_tub,1', 'required', 'in:we,client'];
            $rules['tile_shower_mode'] = ['required', 'in:sqft,dimensions'];
            $rules['tile_shower_sqft'] = ['exclude_unless:tile_shower_mode,sqft', 'required', 'numeric', 'min:0'];
            $rules['tile_shower_length_ft'] = ['exclude_unless:tile_shower_mode,dimensions', 'required', 'numeric', 'min:0'];
            $rules['tile_shower_width_ft'] = ['exclude_unless:tile_shower_mode,dimensions', 'required', 'numeric', 'min:0'];
            $rules['tile_shower_finish'] = ['required', 'in:tile,vinyl'];
        }

        return $rules;
    }

    private function isYes(Request $request, string $key): bool
    {
        $value = $request->input($key);

        return $value === true || $value === 1 || $value === '1' || $value === 'yes';
    }
}
