<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Digits plus common phone punctuation, e.g. "+1 (555) 123-4567".
     *
     * @var array<int, string>
     */
    private const PHONE_RULES = ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]+$/'];

    public function index(Request $request): JsonResponse
    {
        $this->ensureAuthenticated($request);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'hourly_rate', 'flat_rate', 'created_at'])
            ->map(fn (User $user) => $this->userPayload($user));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAuthenticated($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => self::PHONE_RULES,
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
            ...$this->laborRateRules($this->requestImpliesTechnician($request)),
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
        ]);

        $this->syncRoles($request, $user, $validated);
        $this->syncLaborRates($user, $validated);

        return response()->json($this->userPayload($user->load('roles:id,name')), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureAuthenticated($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => self::PHONE_RULES,
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists(Role::class, 'name')],
            ...$this->laborRateRules($this->requestImpliesTechnician($request, $user)),
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            ...(filled($validated['password'] ?? null) ? ['password' => $validated['password']] : []),
        ]);

        $this->syncRoles($request, $user, $validated);
        $this->syncLaborRates($user, $validated);

        return response()->json($this->userPayload($user->refresh()->load('roles:id,name')));
    }

    private function ensureAuthenticated(Request $request): void
    {
        abort_unless($request->user('web'), 401, 'Unauthenticated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncRoles(Request $request, User $user, array $validated): void
    {
        if (! array_key_exists('roles', $validated) || ! $request->user('web')?->can('roles.edit')) {
            return;
        }

        $user->syncRoles($validated['roles']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function laborRateRules(bool $isTechnician): array
    {
        return [
            'hourly_rate' => [
                Rule::requiredIf($isTechnician),
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999.99',
            ],
            'flat_rate' => [
                Rule::requiredIf($isTechnician),
                'nullable',
                'boolean',
            ],
        ];
    }

    private function requestImpliesTechnician(Request $request, ?User $user = null): bool
    {
        if (! $request->user('web')?->can('roles.edit')) {
            return $user?->isTechnician() ?? false;
        }

        if (! $request->exists('roles')) {
            return $user?->isTechnician() ?? false;
        }

        $roles = $request->input('roles', []);

        if (! is_array($roles)) {
            return false;
        }

        return in_array(User::TECHNICIAN_ROLE, $roles, true);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncLaborRates(User $user, array $validated): void
    {
        $user->load('roles');

        if ($user->isTechnician()) {
            $user->update([
                'hourly_rate' => $validated['hourly_rate'],
                'flat_rate' => (bool) ($validated['flat_rate'] ?? false),
            ]);

            return;
        }

        if ($user->hourly_rate !== null || $user->flat_rate) {
            $user->update([
                'hourly_rate' => null,
                'flat_rate' => false,
            ]);
        }
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, hourly_rate: string|null, flat_rate: bool, roles: Collection<int, string>, created_at: mixed}
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('roles:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'hourly_rate' => $user->hourly_rate,
            'flat_rate' => $user->flat_rate,
            'roles' => $user->roles->pluck('name')->values(),
            'created_at' => $user->created_at,
        ];
    }
}
