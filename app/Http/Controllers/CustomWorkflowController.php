<?php

namespace App\Http\Controllers;

use App\Enums\EstimateWorkflow;
use App\Models\CustomWorkflow;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomWorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workflows = CustomWorkflow::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get(['id', 'name', 'value']);

        return response()->json($workflows);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $this->validatedName($request);

        $workflow = CustomWorkflow::query()->create([
            'name' => $name,
            'value' => CustomWorkflow::valueForNew(),
        ]);

        return response()->json($workflow, 201);
    }

    public function update(Request $request, CustomWorkflow $customWorkflow): JsonResponse
    {
        $customWorkflow->update([
            'name' => $this->validatedName($request, $customWorkflow),
        ]);

        return response()->json($customWorkflow);
    }

    public function destroy(CustomWorkflow $customWorkflow): JsonResponse
    {
        $isUsed = Estimate::query()->where('workflow', $customWorkflow->value)->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([
                'workflow' => 'This workflow is used on an estimate, order, or invoice.',
            ]);
        }

        $customWorkflow->delete();

        return response()->json(['message' => 'Workflow deleted successfully.']);
    }

    private function validatedName(Request $request, ?CustomWorkflow $workflow = null): string
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(CustomWorkflow::class, 'name')->ignore($workflow?->id),
            ],
        ]);

        $name = $validated['name'];

        foreach (EstimateWorkflow::cases() as $case) {
            if (strcasecmp($case->label(), $name) === 0) {
                throw ValidationException::withMessages([
                    'name' => 'That workflow already exists.',
                ]);
            }
        }

        return $name;
    }
}
