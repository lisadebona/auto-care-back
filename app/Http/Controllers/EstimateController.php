<?php

namespace App\Http\Controllers;

use App\Enums\EstimateItemType;
use App\Enums\EstimateOrderStatus;
use App\Enums\EstimateWorkflow;
use App\Enums\FeeType;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateService;
use App\Models\FeeSetting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EstimateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estimates = Estimate::query()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,customer_id,year,make,model,sub_model',
                'serviceWriter:id,name',
                'services.lineItems',
            ])
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('po_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search): void {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('vehicle', function ($query) use ($search): void {
                            $query->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('vin', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->input('customer_id'), function ($query, mixed $customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->orderByDesc('number')
            ->get();

        return response()->json([
            'estimates' => $estimates,
            'options' => $this->options(),
        ]);
    }

    public function show(Estimate $estimate): JsonResponse
    {
        $estimate->load([
            'customer:id,first_name,last_name,phone,email',
            'vehicle:id,customer_id,year,make,model,sub_model,vin,mileage',
            'serviceWriter:id,name',
            'services.lineItems',
        ]);

        return response()->json([
            'estimate' => $estimate,
            'options' => $this->options($estimate->customer_id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $estimate = DB::transaction(function () use ($validated, $request): Estimate {
            $estimate = Estimate::query()->create([
                ...$this->estimateAttributes($validated),
                'number' => Estimate::nextNumber(),
                'service_writer_id' => $validated['service_writer_id'] ?? $request->user()?->id,
            ]);

            $this->syncServices($estimate, $validated['services'] ?? []);

            return $estimate;
        });

        return response()->json([
            'estimate' => $estimate->load([
                'customer:id,first_name,last_name,phone,email',
                'vehicle:id,customer_id,year,make,model,sub_model,vin,mileage',
                'serviceWriter:id,name',
                'services.lineItems',
            ]),
            'options' => $this->options($estimate->customer_id),
            'message' => 'Estimate created.',
        ], 201);
    }

    public function update(Request $request, Estimate $estimate): JsonResponse
    {
        $validated = $this->validated($request, $estimate);

        DB::transaction(function () use ($estimate, $validated): void {
            $estimate->update($this->estimateAttributes($validated, $estimate));
            $this->syncServices($estimate, $validated['services'] ?? []);
        });

        return response()->json([
            'estimate' => $estimate->refresh()->load([
                'customer:id,first_name,last_name,phone,email',
                'vehicle:id,customer_id,year,make,model,sub_model,vin,mileage',
                'serviceWriter:id,name',
                'services.lineItems',
            ]),
            'options' => $this->options($estimate->customer_id),
            'message' => 'Estimate saved.',
        ]);
    }

    public function destroy(Estimate $estimate): JsonResponse
    {
        $estimate->delete();

        return response()->json(['message' => 'Estimate deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Estimate $estimate = null): array
    {
        $request->merge([
            'vehicle_id' => $request->filled('vehicle_id') ? $request->input('vehicle_id') : null,
            'service_writer_id' => $request->filled('service_writer_id') ? $request->input('service_writer_id') : null,
            'due_date' => $request->filled('due_date') ? $request->input('due_date') : null,
            'po_number' => $request->filled('po_number') ? $request->input('po_number') : null,
            'completed_at' => $request->filled('completed_at') ? $request->input('completed_at') : null,
            'customer_comments' => $request->filled('customer_comments') ? $request->input('customer_comments') : null,
            'recommendations' => $request->filled('recommendations') ? $request->input('recommendations') : null,
            'authorized' => filter_var($request->input('authorized', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists(Vehicle::class, 'id')],
            'service_writer_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'due_date' => ['nullable', 'date'],
            'customer_comments' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', Rule::in(Estimate::PAYMENT_TERMS)],
            'order_status' => ['required', Rule::enum(EstimateOrderStatus::class)],
            'workflow' => ['required', Rule::enum(EstimateWorkflow::class)],
            'authorized' => ['sometimes', 'boolean'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string', 'max:100'],
            'services' => ['nullable', 'array'],
            'services.*.name' => ['nullable', 'string', 'max:255'],
            'services.*.notes' => ['nullable', 'string', 'max:5000'],
            'services.*.authorized' => ['sometimes', 'boolean'],
            'services.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.epa_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.shop_supplies_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'services.*.line_items' => ['nullable', 'array'],
            'services.*.line_items.*.type' => ['required', Rule::enum(EstimateItemType::class)],
            'services.*.line_items.*.description' => ['nullable', 'string', 'max:255'],
            'services.*.line_items.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.line_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'services.*.line_items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'services.*.line_items.*.status' => ['nullable', 'string', 'max:100'],
        ]);

        if (($validated['vehicle_id'] ?? null) !== null) {
            $vehicleBelongsToCustomer = Vehicle::query()
                ->whereKey($validated['vehicle_id'])
                ->where('customer_id', $validated['customer_id'])
                ->exists();

            if (! $vehicleBelongsToCustomer) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'The selected vehicle does not belong to this customer.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function estimateAttributes(array $validated, ?Estimate $estimate = null): array
    {
        $authorized = (bool) ($validated['authorized'] ?? false);

        return [
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'service_writer_id' => $validated['service_writer_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'customer_comments' => $validated['customer_comments'] ?? null,
            'recommendations' => $validated['recommendations'] ?? null,
            'po_number' => $validated['po_number'] ?? null,
            'completed_at' => $validated['completed_at'] ?? null,
            'payment_terms' => $validated['payment_terms'] ?? Estimate::DEFAULT_PAYMENT_TERMS,
            'order_status' => $validated['order_status'],
            'workflow' => $validated['workflow'],
            'authorized_at' => $authorized
                ? ($estimate?->authorized_at ?? now())
                : null,
            'labels' => $validated['labels'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function syncServices(Estimate $estimate, array $services): void
    {
        $estimate->services()->each(function (EstimateService $service): void {
            $service->lineItems()->delete();
            $service->delete();
        });

        foreach (array_values($services) as $index => $serviceData) {
            /** @var EstimateService $service */
            $service = $estimate->services()->create([
                'name' => $serviceData['name'] ?? null,
                'notes' => $serviceData['notes'] ?? null,
                'authorized' => (bool) ($serviceData['authorized'] ?? false),
                'discount_percent' => $serviceData['discount_percent'] ?? 0,
                'epa_percent' => $serviceData['epa_percent'] ?? 0,
                'shop_supplies_percent' => $serviceData['shop_supplies_percent'] ?? 0,
                'tax_percent' => $serviceData['tax_percent'] ?? 0,
                'sort_order' => $index,
            ]);

            foreach (array_values($serviceData['line_items'] ?? []) as $itemIndex => $itemData) {
                $service->lineItems()->create([
                    'type' => $itemData['type'],
                    'description' => $itemData['description'] ?? null,
                    'price' => $itemData['price'] ?? 0,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'discount' => $itemData['discount'] ?? null,
                    'status' => $itemData['status'] ?? null,
                    'sort_order' => $itemIndex,
                ]);
            }
        }
    }

    /**
     * @return array{
     *     payment_terms: list<string>,
     *     order_statuses: list<string>,
     *     workflows: list<array{value: string, label: string}>,
     *     item_types: list<array{value: string, label: string}>,
     *     fee_defaults: array{epa_percent: string, shop_supplies_percent: string, tax_percent: string},
     *     service_writers: list<array{id: int, name: string}>,
     *     customers: list<array{id: int, name: string}>,
     *     vehicles: list<array{id: int, customer_id: int, name: string}>
     * }
     */
    private function options(?int $customerId = null): array
    {
        $fees = FeeSetting::current();

        $customers = Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
            ])
            ->all();

        $vehiclesQuery = Vehicle::query()
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model');

        if ($customerId !== null) {
            $vehiclesQuery->where('customer_id', $customerId);
        }

        $vehicles = $vehiclesQuery
            ->get(['id', 'customer_id', 'year', 'make', 'model', 'sub_model'])
            ->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'name' => $vehicle->name,
            ])
            ->all();

        $serviceWriters = User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();

        return [
            'payment_terms' => Estimate::PAYMENT_TERMS,
            'order_statuses' => array_column(EstimateOrderStatus::cases(), 'value'),
            'workflows' => [
                ['value' => EstimateWorkflow::Estimates->value, 'label' => 'Estimates'],
                ['value' => EstimateWorkflow::InProgress->value, 'label' => 'In Progress'],
                ['value' => EstimateWorkflow::Completed->value, 'label' => 'Completed'],
            ],
            'item_types' => [
                ['value' => EstimateItemType::Part->value, 'label' => 'Part'],
                ['value' => EstimateItemType::Labor->value, 'label' => 'Labor'],
                ['value' => EstimateItemType::Tire->value, 'label' => 'Tire'],
                ['value' => EstimateItemType::Subcontract->value, 'label' => 'Subcontract'],
                ['value' => EstimateItemType::Fee->value, 'label' => 'Fee'],
            ],
            'fee_defaults' => [
                'epa_percent' => number_format((float) $fees->epa_rate, 3, '.', ''),
                'shop_supplies_percent' => number_format(
                    $fees->shop_supplies_fee_type === FeeType::Percent ? (float) $fees->shop_supplies_fee : 0,
                    3,
                    '.',
                    '',
                ),
                'tax_percent' => number_format((float) $fees->tax_rate, 3, '.', ''),
            ],
            'service_writers' => $serviceWriters,
            'customers' => $customers,
            'vehicles' => $vehicles,
        ];
    }
}
