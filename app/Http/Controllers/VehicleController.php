<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::query()
            ->with('customer:id,first_name,last_name')
            ->when($request->input('customer_id'), function ($query, mixed $customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('sub_model', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%")
                        ->orWhere('year', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('year')
            ->orderBy('make')
            ->orderBy('model')
            ->get();

        return response()->json([
            'vehicles' => $vehicles,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $image = $validated['image'] ?? null;
        unset($validated['image'], $validated['remove_image']);

        if ($image instanceof UploadedFile) {
            $validated['image_path'] = $image->store('vehicles', 'public');
        }

        $vehicle = Vehicle::query()->create($validated);

        return response()->json($vehicle->load('customer:id,first_name,last_name'), 201);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $this->validated($request);
        $previousImagePath = $vehicle->image_path;
        $image = $validated['image'] ?? null;
        $removeImage = (bool) ($validated['remove_image'] ?? false);
        unset($validated['image'], $validated['remove_image']);

        $vehicle->fill($validated);

        if ($image instanceof UploadedFile) {
            $vehicle->image_path = $image->store('vehicles', 'public');
        } elseif ($removeImage) {
            $vehicle->image_path = null;
        }

        $vehicle->save();

        if ($previousImagePath !== null && $previousImagePath !== $vehicle->image_path) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return response()->json($vehicle->refresh()->load('customer:id,first_name,last_name'));
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $imagePath = $vehicle->image_path;
        $vehicle->delete();

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->json(['message' => 'Vehicle deleted successfully.']);
    }

    /**
     * @return array{
     *     customer_id: int,
     *     year: int,
     *     make: string,
     *     model: string,
     *     sub_model: string|null,
     *     transmission: string|null,
     *     engine_size: string|null,
     *     drivetrain: string|null,
     *     type: string,
     *     mileage: int|null,
     *     vin: string|null,
     *     image?: UploadedFile,
     *     remove_image?: bool
     * }
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'sub_model' => $request->filled('sub_model') ? $request->input('sub_model') : null,
            'transmission' => $request->filled('transmission') ? $request->input('transmission') : null,
            'engine_size' => $request->filled('engine_size') ? $request->input('engine_size') : null,
            'drivetrain' => $request->filled('drivetrain') ? $request->input('drivetrain') : null,
            'mileage' => $request->filled('mileage') ? $request->input('mileage') : null,
            'vin' => $request->filled('vin') ? $request->input('vin') : null,
        ]);

        return $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')],
            'year' => ['required', 'integer', 'min:1900', 'max:'.((int) date('Y') + 2)],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'sub_model' => ['nullable', 'string', 'max:255'],
            'transmission' => ['nullable', 'string', 'max:255', Rule::in(Vehicle::TRANSMISSIONS)],
            'engine_size' => ['nullable', 'string', 'max:255'],
            'drivetrain' => ['nullable', 'string', 'max:255', Rule::in(Vehicle::DRIVETRAINS)],
            'type' => ['required', 'string', 'max:255', Rule::in(Vehicle::TYPES)],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'vin' => ['nullable', 'string', 'max:32'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array{transmissions: list<string>, drivetrains: list<string>, types: list<string>}
     */
    private function options(): array
    {
        return [
            'transmissions' => Vehicle::TRANSMISSIONS,
            'drivetrains' => Vehicle::DRIVETRAINS,
            'types' => Vehicle::TYPES,
        ];
    }
}
