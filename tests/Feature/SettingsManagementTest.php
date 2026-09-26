<?php

namespace Tests\Feature;

use App\Enums\FeeType;
use App\Enums\ShopSuppliesCap;
use App\Models\FeeSetting;
use App\Models\GeneralSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    public function test_guests_cannot_view_or_change_settings(): void
    {
        $this->getJson('/api/settings/general')->assertForbidden();
        $this->putJson('/api/settings/general', $this->generalSettings())->assertForbidden();
        $this->getJson('/api/settings/fees-and-rates')->assertForbidden();
        $this->putJson('/api/settings/fees-and-rates', $this->feeSettings())->assertForbidden();
    }

    public function test_viewers_see_default_general_settings_before_any_are_saved(): void
    {
        $this->actingAs($this->settingsUser(['general-settings.view']))
            ->getJson('/api/settings/general')
            ->assertOk()
            ->assertJsonPath('settings.company_name', null)
            ->assertJsonPath('settings.timezone', 'UTC')
            ->assertJsonFragment(['America/New_York']);

        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_editors_can_save_general_settings_and_upload_a_logo(): void
    {
        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->post('/api/settings/general', [
                ...$this->generalSettings(),
                'logo' => UploadedFile::fake()->image('logo.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('settings.company_name', 'Acme Auto Repair')
            ->assertJsonPath('message', 'General settings saved.');

        $settings = GeneralSetting::query()->sole();
        $this->assertSame('https://acme.test', $settings->website);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_saving_general_settings_again_updates_the_same_record(): void
    {
        $settings = GeneralSetting::factory()->create();

        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->putJson('/api/settings/general', $this->generalSettings(['company_name' => 'Renamed Garage']))
            ->assertOk();

        $this->assertTrue(GeneralSetting::query()->sole()->is($settings));
        $this->assertSame('Renamed Garage', $settings->refresh()->company_name);
    }

    public function test_a_website_without_a_scheme_is_saved_with_https(): void
    {
        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->putJson('/api/settings/general', $this->generalSettings(['website' => 'acme.test']))
            ->assertOk()
            ->assertJsonPath('settings.website', 'https://acme.test');
    }

    public function test_company_name_and_timezone_are_required(): void
    {
        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->putJson('/api/settings/general', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_name', 'timezone']);

        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_only_valid_timezones_and_image_logos_are_accepted(): void
    {
        $editor = $this->settingsUser(['general-settings.edit']);

        $this->actingAs($editor)
            ->putJson('/api/settings/general', $this->generalSettings(['timezone' => 'Mars/Olympus_Mons']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);

        $this->actingAs($editor)
            ->post('/api/settings/general', [
                ...$this->generalSettings(),
                'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_uploading_a_new_logo_replaces_the_previous_one(): void
    {
        $oldLogoPath = UploadedFile::fake()->image('old.png')->store('logos', 'public');
        $settings = GeneralSetting::factory()->create();
        $settings->logo_path = $oldLogoPath;
        $settings->save();

        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->post('/api/settings/general', [
                ...$this->generalSettings(),
                'logo' => UploadedFile::fake()->image('new.png'),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $newLogoPath = GeneralSetting::query()->sole()->logo_path;
        $this->assertNotSame($oldLogoPath, $newLogoPath);
        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertExists($newLogoPath);
    }

    public function test_the_logo_can_be_removed(): void
    {
        $logoPath = UploadedFile::fake()->image('logo.png')->store('logos', 'public');
        $settings = GeneralSetting::factory()->create();
        $settings->logo_path = $logoPath;
        $settings->save();

        $this->actingAs($this->settingsUser(['general-settings.edit']))
            ->putJson('/api/settings/general', $this->generalSettings(['remove_logo' => true]))
            ->assertOk()
            ->assertJsonPath('settings.logo_url', null);

        $this->assertNull(GeneralSetting::query()->sole()->logo_path);
        Storage::disk('public')->assertMissing($logoPath);
    }

    public function test_viewers_cannot_change_general_settings(): void
    {
        $this->actingAs($this->settingsUser(['general-settings.view']))
            ->putJson('/api/settings/general', $this->generalSettings())
            ->assertForbidden();

        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_viewers_see_zero_rates_before_any_are_saved(): void
    {
        $this->actingAs($this->settingsUser(['fees-and-rates.view']))
            ->getJson('/api/settings/fees-and-rates')
            ->assertOk()
            ->assertJsonPath('settings.shop_supplies_cap', 'none')
            ->assertJsonPath('settings.shop_supplies_fee', '0.000')
            ->assertJsonPath('settings.shop_supplies_fee_type', 'percent')
            ->assertJsonPath('settings.tax_rate', '0.000')
            ->assertJsonPath('settings.tax_on_parts', false);

        $this->assertDatabaseCount('fee_settings', 0);
    }

    public function test_editors_can_save_fees_and_rates(): void
    {
        $this->actingAs($this->settingsUser(['fees-and-rates.edit']))
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings())
            ->assertOk()
            ->assertJsonPath('settings.tax_rate', '7.125')
            ->assertJsonPath('message', 'Fees & rates saved.');

        $settings = FeeSetting::query()->sole();
        $this->assertSame(ShopSuppliesCap::NoCap, $settings->shop_supplies_cap);
        $this->assertNull($settings->shop_supplies_cap_amount);
        $this->assertSame('3.000', $settings->shop_supplies_fee);
        $this->assertSame(FeeType::Percent, $settings->shop_supplies_fee_type);
        $this->assertTrue($settings->shop_supplies_on_parts);
        $this->assertFalse($settings->tax_on_subcontract);
    }

    public function test_saving_fees_again_updates_the_same_record(): void
    {
        $settings = FeeSetting::factory()->create();

        $this->actingAs($this->settingsUser(['fees-and-rates.edit']))
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['tax_rate' => '8.25']))
            ->assertOk();

        $this->assertTrue(FeeSetting::query()->sole()->is($settings));
        $this->assertSame('8.250', $settings->refresh()->tax_rate);
    }

    public function test_an_order_cap_requires_and_stores_its_amount(): void
    {
        $editor = $this->settingsUser(['fees-and-rates.edit']);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['shop_supplies_cap' => 'order']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop_supplies_cap_amount']);

        $this->assertDatabaseCount('fee_settings', 0);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings([
                'shop_supplies_cap' => 'order',
                'shop_supplies_cap_amount' => '45.5',
            ]))
            ->assertOk();

        $settings = FeeSetting::query()->sole();
        $this->assertSame(ShopSuppliesCap::OrderCap, $settings->shop_supplies_cap);
        $this->assertSame('45.50', $settings->shop_supplies_cap_amount);
    }

    public function test_switching_to_no_cap_clears_the_saved_cap_amount(): void
    {
        $settings = FeeSetting::factory()->orderCap('50.00')->create();

        $this->actingAs($this->settingsUser(['fees-and-rates.edit']))
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['shop_supplies_cap_amount' => '50']))
            ->assertOk();

        $this->assertNull($settings->refresh()->shop_supplies_cap_amount);
    }

    public function test_a_percentage_fee_cannot_exceed_100_but_a_flat_fee_can(): void
    {
        $editor = $this->settingsUser(['fees-and-rates.edit']);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['shop_supplies_fee' => '150']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop_supplies_fee']);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings([
                'shop_supplies_fee' => '150',
                'shop_supplies_fee_type' => 'fixed',
            ]))
            ->assertOk();

        $settings = FeeSetting::query()->sole();
        $this->assertSame('150.000', $settings->shop_supplies_fee);
        $this->assertSame(FeeType::Fixed, $settings->shop_supplies_fee_type);
    }

    public function test_rates_must_stay_between_zero_and_100_with_at_most_three_decimals(): void
    {
        $editor = $this->settingsUser(['fees-and-rates.edit']);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['epa_rate' => '-1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['epa_rate']);

        $this->actingAs($editor)
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings(['tax_rate' => '7.1255']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_rate']);

        $this->assertDatabaseCount('fee_settings', 0);
    }

    public function test_viewers_cannot_change_fees_and_rates(): void
    {
        $this->actingAs($this->settingsUser(['fees-and-rates.view']))
            ->putJson('/api/settings/fees-and-rates', $this->feeSettings())
            ->assertForbidden();

        $this->assertDatabaseCount('fee_settings', 0);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function settingsUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function generalSettings(array $overrides = []): array
    {
        return [
            'company_name' => 'Acme Auto Repair',
            'website' => 'https://acme.test',
            'email' => 'hello@acme.test',
            'phone' => '(555) 123-4567',
            'timezone' => 'America/New_York',
            'address' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'zip_code' => '62704',
            'country' => 'United States',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function feeSettings(array $overrides = []): array
    {
        return [
            'shop_supplies_cap' => 'none',
            'shop_supplies_cap_amount' => '',
            'shop_supplies_fee' => '3',
            'shop_supplies_fee_type' => 'percent',
            'shop_supplies_on_parts' => true,
            'shop_supplies_on_labor' => false,
            'epa_rate' => '2',
            'epa_on_parts' => true,
            'epa_on_labor' => false,
            'tax_rate' => '7.125',
            'tax_on_parts' => true,
            'tax_on_labor' => true,
            'tax_on_epa' => false,
            'tax_on_shop_supplies' => false,
            'tax_on_subcontract' => false,
            ...$overrides,
        ];
    }
}
