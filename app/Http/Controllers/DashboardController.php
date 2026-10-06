<?php

namespace App\Http\Controllers;

use App\Enums\EstimateWorkflow;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Recent records for each workflow card on the dashboard.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'workflows' => collect($this->cards())->map(fn (array $card): array => [
                'value' => $card['value'],
                'label' => $card['label'],
                'items' => $this->recentItems($card['value']),
            ])->values(),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function cards(): array
    {
        return [
            ['value' => EstimateWorkflow::Estimates->value, 'label' => 'Estimates'],
            ['value' => EstimateWorkflow::DroppedOff->value, 'label' => 'Dropped Off'],
            ['value' => EstimateWorkflow::InProgress->value, 'label' => 'In Progress'],
            ['value' => EstimateWorkflow::Invoices->value, 'label' => 'Invoices'],
        ];
    }

    /**
     * @return list<array{id: int, reference: string, customer: string|null, vehicle: string|null, total: string, order_status: string, workflow: string}>
     */
    private function recentItems(string $workflow): array
    {
        return Estimate::query()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model,sub_model',
                'services.lineItems',
            ])
            ->where('workflow', $workflow)
            ->latest('updated_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Estimate $estimate): array => [
                'id' => $estimate->id,
                'reference' => $this->reference($estimate),
                'customer' => $estimate->customer?->name,
                'vehicle' => $estimate->vehicle?->name,
                'total' => $estimate->totals['grand_total'],
                'order_status' => $estimate->order_status->value,
                'workflow' => (string) $estimate->workflow,
            ])
            ->all();
    }

    private function reference(Estimate $estimate): string
    {
        if ($estimate->workflow === EstimateWorkflow::Invoices->value && filled($estimate->invoice_number)) {
            return (string) $estimate->invoice_number;
        }

        if ($estimate->workflow === EstimateWorkflow::InProgress->value && filled($estimate->order_number)) {
            return (string) $estimate->order_number;
        }

        return $estimate->display_number;
    }
}
