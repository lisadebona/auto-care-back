<?php

namespace App\Http\Controllers;

use App\Enums\EstimateItemType;
use App\Models\CannedJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CannedJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cannedJobs = CannedJob::query()
            ->with('lineItems')
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('lineItems', function ($query) use ($search): void {
                            $query->where('description', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'canned_jobs' => $cannedJobs,
        ]);
    }

    public function show(CannedJob $cannedJob): JsonResponse
    {
        $cannedJob->load('lineItems');

        return response()->json([
            'canned_job' => $cannedJob,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $cannedJob = DB::transaction(function () use ($validated): CannedJob {
            $cannedJob = CannedJob::query()->create([
                'name' => $validated['name'],
            ]);

            $this->syncLineItems($cannedJob, $validated['line_items'] ?? []);

            return $cannedJob;
        });

        return response()->json([
            'canned_job' => $cannedJob->load('lineItems'),
            'message' => 'Canned job created.',
        ], 201);
    }

    public function update(Request $request, CannedJob $cannedJob): JsonResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($cannedJob, $validated): void {
            $cannedJob->update([
                'name' => $validated['name'],
            ]);

            $this->syncLineItems($cannedJob, $validated['line_items'] ?? []);
        });

        return response()->json([
            'canned_job' => $cannedJob->refresh()->load('lineItems'),
            'message' => 'Canned job saved.',
        ]);
    }

    public function destroy(CannedJob $cannedJob): JsonResponse
    {
        $cannedJob->delete();

        return response()->json(['message' => 'Canned job deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'line_items' => ['nullable', 'array'],
            'line_items.*.type' => ['required', Rule::enum(EstimateItemType::class)],
            'line_items.*.description' => ['nullable', 'string', 'max:255'],
            'line_items.*.price' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.remarks' => ['nullable', 'array', 'max:20'],
            'line_items.*.remarks.*' => ['string', 'max:100'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     */
    private function syncLineItems(CannedJob $cannedJob, array $lineItems): void
    {
        $cannedJob->lineItems()->delete();

        foreach (array_values($lineItems) as $index => $itemData) {
            $description = trim((string) ($itemData['description'] ?? ''));

            $cannedJob->lineItems()->create([
                'type' => $itemData['type'],
                'description' => $description === '' ? null : $description,
                'price' => $itemData['price'] ?? 0,
                'quantity' => $itemData['quantity'] ?? 1,
                'discount' => $itemData['discount'] ?? null,
                'remarks' => $this->remarks($itemData),
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @return list<string>|null
     */
    private function remarks(array $itemData): ?array
    {
        $remarks = $itemData['remarks'] ?? null;

        if (! is_array($remarks)) {
            return null;
        }

        $cleaned = [];

        foreach ($remarks as $remark) {
            $remark = trim((string) $remark);

            if ($remark !== '' && ! in_array($remark, $cleaned, true)) {
                $cleaned[] = $remark;
            }
        }

        return $cleaned === [] ? null : $cleaned;
    }
}
