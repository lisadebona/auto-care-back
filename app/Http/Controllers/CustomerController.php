<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * US phone format, e.g. "(555) 123-4567".
     *
     * @var array<int, string>
     */
    private const PHONE_RULES = ['required', 'string', 'max:30', 'regex:/^\(\d{3}\) \d{3}-\d{4}$/'];

    /**
     * Optional US phone format, e.g. "(555) 123-4567".
     *
     * @var array<int, string>
     */
    private const OPTIONAL_PHONE_RULES = ['nullable', 'string', 'max:30', 'regex:/^\(\d{3}\) \d{3}-\d{4}$/'];

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('phone_2', 'like', "%{$search}%")
                        ->orWhere('home_city', 'like', "%{$search}%")
                        ->orWhere('office_city', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'customers' => $customers,
            'countries' => Countries::names(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::query()->create($this->validated($request));

        return response()->json($customer, 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request, $customer));

        return response()->json($customer->refresh());
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully.']);
    }

    /**
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     home_address: string|null,
     *     home_city: string|null,
     *     home_state: string|null,
     *     home_zip_code: string|null,
     *     home_country: string|null,
     *     office_address: string|null,
     *     office_city: string|null,
     *     office_state: string|null,
     *     office_zip_code: string|null,
     *     office_country: string|null,
     *     phone: string,
     *     phone_2: string|null,
     *     email: string|null,
     *     notes: string|null
     * }
     */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge([
            'phone_2' => $request->filled('phone_2') ? $request->input('phone_2') : null,
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'home_address' => ['nullable', 'string', 'max:255'],
            'home_city' => ['nullable', 'string', 'max:255'],
            'home_state' => ['nullable', 'string', 'max:255'],
            'home_zip_code' => ['nullable', 'string', 'max:20'],
            'home_country' => ['nullable', 'string', 'max:255', Rule::in(Countries::names())],
            'office_address' => ['nullable', 'string', 'max:255'],
            'office_city' => ['nullable', 'string', 'max:255'],
            'office_state' => ['nullable', 'string', 'max:255'],
            'office_zip_code' => ['nullable', 'string', 'max:20'],
            'office_country' => ['nullable', 'string', 'max:255', Rule::in(Countries::names())],
            'phone' => self::PHONE_RULES,
            'phone_2' => self::OPTIONAL_PHONE_RULES,
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique(Customer::class, 'email')->ignore($customer?->id),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'home_address' => $validated['home_address'] ?? null,
            'home_city' => $validated['home_city'] ?? null,
            'home_state' => $validated['home_state'] ?? null,
            'home_zip_code' => $validated['home_zip_code'] ?? null,
            'home_country' => filled($validated['home_country'] ?? null)
                ? $validated['home_country']
                : Customer::DEFAULT_COUNTRY,
            'office_address' => $validated['office_address'] ?? null,
            'office_city' => $validated['office_city'] ?? null,
            'office_state' => $validated['office_state'] ?? null,
            'office_zip_code' => $validated['office_zip_code'] ?? null,
            'office_country' => filled($validated['office_country'] ?? null)
                ? $validated['office_country']
                : Customer::DEFAULT_COUNTRY,
            'phone' => $validated['phone'],
            'phone_2' => $validated['phone_2'] ?? null,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
