<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use App\Support\Countries;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class GeneralSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->payload(GeneralSetting::current()),
            'timezones' => DateTimeZone::listIdentifiers(),
            'countries' => Countries::names(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $website = $request->string('website')->trim()->toString();

        if ($website !== '' && ! preg_match('~^https?://~i', $website)) {
            $request->merge(['website' => "https://{$website}"]);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+().\-\s]+$/'],
            'timezone' => ['required', 'timezone:all'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255', Rule::in(Countries::names())],
        ]);

        $settings = GeneralSetting::current();
        $previousLogoPath = $settings->logo_path;
        $settings->fill(Arr::except($validated, ['logo', 'remove_logo']));

        $newLogoPath = null;

        if (($validated['logo'] ?? null) instanceof UploadedFile) {
            $newLogoPath = $validated['logo']->store('logos', 'public');

            if ($newLogoPath === false) {
                throw ValidationException::withMessages([
                    'logo' => 'The logo could not be uploaded. Please try again.',
                ]);
            }
        }

        if ($newLogoPath !== null) {
            $settings->logo_path = $newLogoPath;
        } elseif ($request->boolean('remove_logo')) {
            $settings->logo_path = null;
        }

        try {
            $settings->save();
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($previousLogoPath !== null && $previousLogoPath !== $settings->logo_path) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return response()->json([
            'settings' => $this->payload($settings),
            'timezones' => DateTimeZone::listIdentifiers(),
            'countries' => Countries::names(),
            'message' => 'General settings saved.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(GeneralSetting $settings): array
    {
        return [
            'company_name' => $settings->company_name,
            'website' => $settings->website,
            'logo_url' => $settings->logo_url,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'timezone' => $settings->timezone,
            'address' => $settings->address,
            'city' => $settings->city,
            'state' => $settings->state,
            'zip_code' => $settings->zip_code,
            'country' => $settings->country ?? 'United States',
        ];
    }
}
