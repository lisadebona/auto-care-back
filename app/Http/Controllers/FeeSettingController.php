<?php

namespace App\Http\Controllers;

use App\Enums\FeeType;
use App\Enums\ShopSuppliesCap;
use App\Models\FeeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->payload(FeeSetting::current()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $isOrderCap = $request->input('shop_supplies_cap') === ShopSuppliesCap::OrderCap->value;
        $isPercentFee = $request->input('shop_supplies_fee_type') !== FeeType::Fixed->value;
        $percentRules = ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:100'];
        $includeRules = ['required', 'boolean'];

        $validated = $request->validate([
            'shop_supplies_cap' => ['required', Rule::enum(ShopSuppliesCap::class)],
            'shop_supplies_cap_amount' => [
                Rule::requiredIf($isOrderCap),
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:99999999.99',
            ],
            'shop_supplies_fee_type' => ['required', Rule::enum(FeeType::class)],
            'shop_supplies_fee' => $isPercentFee
                ? $percentRules
                : ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'shop_supplies_on_parts' => $includeRules,
            'shop_supplies_on_labor' => $includeRules,
            'epa_rate' => $percentRules,
            'epa_on_parts' => $includeRules,
            'epa_on_labor' => $includeRules,
            'tax_rate' => $percentRules,
            'tax_on_parts' => $includeRules,
            'tax_on_labor' => $includeRules,
            'tax_on_epa' => $includeRules,
            'tax_on_shop_supplies' => $includeRules,
            'tax_on_subcontract' => $includeRules,
        ], [
            'shop_supplies_cap_amount.required' => 'Enter the maximum shop supplies amount per order.',
        ]);

        $settings = FeeSetting::current();
        $settings->fill([
            ...$validated,
            'shop_supplies_cap_amount' => $isOrderCap ? $validated['shop_supplies_cap_amount'] : null,
        ])->save();

        return response()->json([
            'settings' => $this->payload($settings),
            'message' => 'Fees & rates saved.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FeeSetting $settings): array
    {
        return [
            'shop_supplies_cap' => $settings->shop_supplies_cap->value,
            'shop_supplies_cap_amount' => $settings->shop_supplies_cap_amount,
            'shop_supplies_fee' => $settings->shop_supplies_fee,
            'shop_supplies_fee_type' => $settings->shop_supplies_fee_type->value,
            'shop_supplies_on_parts' => $settings->shop_supplies_on_parts,
            'shop_supplies_on_labor' => $settings->shop_supplies_on_labor,
            'epa_rate' => $settings->epa_rate,
            'epa_on_parts' => $settings->epa_on_parts,
            'epa_on_labor' => $settings->epa_on_labor,
            'tax_rate' => $settings->tax_rate,
            'tax_on_parts' => $settings->tax_on_parts,
            'tax_on_labor' => $settings->tax_on_labor,
            'tax_on_epa' => $settings->tax_on_epa,
            'tax_on_shop_supplies' => $settings->tax_on_shop_supplies,
            'tax_on_subcontract' => $settings->tax_on_subcontract,
        ];
    }
}
