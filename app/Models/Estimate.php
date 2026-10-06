<?php

namespace App\Models;

use App\Enums\EstimateItemType;
use App\Enums\EstimateOrderStatus;
use App\Enums\EstimateWorkflow;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'number',
    'customer_id',
    'vehicle_id',
    'service_writer_id',
    'due_date',
    'customer_comments',
    'recommendations',
    'po_number',
    'completed_at',
    'payment_terms',
    'order_status',
    'workflow',
    'authorized_at',
    'labels',
])]
class Estimate extends Model
{
    public const DEFAULT_PAYMENT_TERMS = 'On Receipt';

    /**
     * @var list<string>
     */
    public const PAYMENT_TERMS = [
        'On Receipt',
        'Net 15',
        'Net 30',
        'Net 60',
    ];

    /** @use HasFactory<EstimateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['display_number', 'is_authorized', 'totals', 'workflow_label'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_terms' => self::DEFAULT_PAYMENT_TERMS,
        'order_status' => 'estimate',
        'workflow' => 'estimates',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function serviceWriter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'service_writer_id');
    }

    /**
     * @return HasMany<EstimateService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(EstimateService::class)->orderBy('sort_order');
    }

    public static function nextNumber(): int
    {
        $max = DB::table('estimates')->max('number');

        return $max === null ? 1000 : ((int) $max) + 1;
    }

    /**
     * Invoice numbers are the record ID plus a unique sequence that starts at 001.
     *
     * @return array{invoice_sequence: int, invoice_number: string}
     */
    public static function generateInvoiceNumber(int $recordId): array
    {
        $sequence = (int) static::query()->lockForUpdate()->max('invoice_sequence');
        $sequence = $sequence < 1 ? 1 : $sequence + 1;

        do {
            $invoiceNumber = $recordId.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $taken = static::query()
                ->where('invoice_number', $invoiceNumber)
                ->whereKeyNot($recordId)
                ->exists();

            if ($taken) {
                $sequence++;
            }
        } while ($taken);

        return [
            'invoice_sequence' => $sequence,
            'invoice_number' => $invoiceNumber,
        ];
    }

    /**
     * Order numbers use the same format as invoice numbers: record ID plus a sequence starting at 001.
     *
     * @return array{order_sequence: int, order_number: string}
     */
    public static function generateOrderNumber(int $recordId): array
    {
        $sequence = (int) static::query()->lockForUpdate()->max('order_sequence');
        $sequence = $sequence < 1 ? 1 : $sequence + 1;

        do {
            $orderNumber = $recordId.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $taken = static::query()
                ->where('order_number', $orderNumber)
                ->whereKeyNot($recordId)
                ->exists();

            if ($taken) {
                $sequence++;
            }
        } while ($taken);

        return [
            'order_sequence' => $sequence,
            'order_number' => $orderNumber,
        ];
    }

    /**
     * Format a PO number as PO + record ID + a unique number.
     *
     * The estimate number is the unique number. If that value is already
     * taken, the unique number increments until the PO number is free.
     */
    public static function generatePoNumber(int $recordId, int $uniqueNumber): string
    {
        $poNumber = 'PO'.$recordId.$uniqueNumber;

        while (
            static::query()
                ->where('po_number', $poNumber)
                ->whereKeyNot($recordId)
                ->exists()
        ) {
            $uniqueNumber++;
            $poNumber = 'PO'.$recordId.$uniqueNumber;
        }

        return $poNumber;
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayNumber(): Attribute
    {
        return Attribute::get(fn (): string => '#'.$this->number);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isAuthorized(): Attribute
    {
        return Attribute::get(fn (): bool => $this->authorized_at !== null);
    }

    /**
     * @return Attribute<array{parts: string, labor: string, tires: string, subcontract: string, fees: string, subtotal: string, discount: string, shop_supplies: string, epa: string, tax: string, grand_total: string, paid_to_date: string}, never>
     */
    protected function totals(): Attribute
    {
        return Attribute::get(function (): array {
            $parts = 0.0;
            $labor = 0.0;
            $tires = 0.0;
            $subcontract = 0.0;
            $fees = 0.0;
            $discount = 0.0;
            $shopSupplies = 0.0;
            $epa = 0.0;
            $tax = 0.0;

            foreach ($this->relationLoaded('services') ? $this->services : [] as $service) {
                $itemsTotal = 0.0;

                foreach ($service->relationLoaded('lineItems') ? $service->lineItems : [] as $item) {
                    $lineSubtotal = (float) $item->subtotal;
                    $itemsTotal += $lineSubtotal;

                    match ($item->type) {
                        EstimateItemType::Part => $parts += $lineSubtotal,
                        EstimateItemType::Labor => $labor += $lineSubtotal,
                        EstimateItemType::Tire => $tires += $lineSubtotal,
                        EstimateItemType::Subcontract => $subcontract += $lineSubtotal,
                        EstimateItemType::Fee => $fees += $lineSubtotal,
                    };
                }

                $serviceDiscount = $itemsTotal * ((float) $service->discount_percent / 100);
                $afterDiscount = $itemsTotal - $serviceDiscount;
                $serviceEpa = $afterDiscount * ((float) $service->epa_percent / 100);
                $serviceShop = $afterDiscount * ((float) $service->shop_supplies_percent / 100);
                $serviceTax = ($afterDiscount + $serviceEpa + $serviceShop) * ((float) $service->tax_percent / 100);

                $discount += $serviceDiscount;
                $epa += $serviceEpa;
                $shopSupplies += $serviceShop;
                $tax += $serviceTax;
            }

            $subtotal = $parts + $labor + $tires + $subcontract + $fees;
            $grand = $subtotal - $discount + $shopSupplies + $epa + $tax;

            return [
                'parts' => number_format($parts, 2, '.', ''),
                'labor' => number_format($labor, 2, '.', ''),
                'tires' => number_format($tires, 2, '.', ''),
                'subcontract' => number_format($subcontract, 2, '.', ''),
                'fees' => number_format($fees, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'shop_supplies' => number_format($shopSupplies, 2, '.', ''),
                'epa' => number_format($epa, 2, '.', ''),
                'tax' => number_format($tax, 2, '.', ''),
                'grand_total' => number_format($grand, 2, '.', ''),
                'paid_to_date' => number_format(0, 2, '.', ''),
            ];
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function workflowLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $workflow = (string) $this->workflow;
            $systemWorkflow = EstimateWorkflow::tryFrom($workflow);

            if ($systemWorkflow instanceof EstimateWorkflow) {
                return $systemWorkflow->label();
            }

            return CustomWorkflow::labelsByValue()[$workflow] ?? $workflow;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_sequence' => 'integer',
            'order_sequence' => 'integer',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'authorized_at' => 'datetime',
            'order_status' => EstimateOrderStatus::class,
            'labels' => 'array',
        ];
    }
}
