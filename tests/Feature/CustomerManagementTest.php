<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->getJson('/api/customers')->assertForbidden();
        $this->postJson('/api/customers', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone' => '555-0100',
        ])->assertForbidden();
        $this->putJson("/api/customers/{$customer->id}", [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone' => '555-0100',
        ])->assertForbidden();
        $this->deleteJson("/api/customers/{$customer->id}")->assertForbidden();
    }

    public function test_authorized_user_can_create_update_and_delete_a_customer(): void
    {
        $actor = $this->customerUser([
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
        ]);

        $this->actingAs($actor)->postJson('/api/customers', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'home_address' => '12 Analytical Engine Way',
            'home_city' => 'London',
            'home_state' => 'England',
            'home_zip_code' => 'SW1A 1AA',
            'home_country' => 'United Kingdom',
            'office_address' => '1 Computing Lab',
            'office_city' => 'Cambridge',
            'office_state' => 'MA',
            'office_zip_code' => '02139',
            'phone' => '(555) 123-4567',
            'phone_2' => '(555) 987-6543',
            'email' => 'ada@example.com',
            'notes' => 'Prefers morning appointments.',
        ])->assertCreated()
            ->assertJsonPath('first_name', 'Ada')
            ->assertJsonPath('last_name', 'Lovelace')
            ->assertJsonPath('home_city', 'London')
            ->assertJsonPath('home_country', 'United Kingdom')
            ->assertJsonPath('office_country', 'United States')
            ->assertJsonPath('phone', '(555) 123-4567')
            ->assertJsonPath('phone_2', '(555) 987-6543')
            ->assertJsonPath('email', 'ada@example.com')
            ->assertJsonPath('notes', 'Prefers morning appointments.')
            ->assertJsonPath('name', 'Ada Lovelace');

        $customer = Customer::query()->where('email', 'ada@example.com')->firstOrFail();

        $this->actingAs($actor)->putJson("/api/customers/{$customer->id}", [
            'first_name' => 'Augusta',
            'last_name' => 'Lovelace',
            'home_address' => '12 Analytical Engine Way',
            'home_city' => 'London',
            'home_state' => 'England',
            'home_zip_code' => 'SW1A 1AA',
            'home_country' => 'United Kingdom',
            'office_address' => null,
            'office_city' => null,
            'office_state' => null,
            'office_zip_code' => null,
            'office_country' => null,
            'phone' => '(555) 555-0199',
            'phone_2' => null,
            'email' => 'augusta@example.com',
        ])->assertOk()
            ->assertJsonPath('first_name', 'Augusta')
            ->assertJsonPath('phone', '(555) 555-0199')
            ->assertJsonPath('phone_2', null)
            ->assertJsonPath('email', 'augusta@example.com')
            ->assertJsonPath('office_address', null)
            ->assertJsonPath('office_country', 'United States');

        $this->actingAs($actor)->deleteJson("/api/customers/{$customer->id}")->assertOk();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_create_requires_first_name_last_name_and_phone(): void
    {
        $actor = $this->customerUser(['customers.create']);

        $this->actingAs($actor)
            ->postJson('/api/customers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'phone']);
    }

    public function test_email_must_be_unique_when_provided(): void
    {
        $actor = $this->customerUser(['customers.create']);
        Customer::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($actor)
            ->postJson('/api/customers', [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'phone' => '(555) 555-0111',
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_can_be_created_without_email(): void
    {
        $actor = $this->customerUser(['customers.create']);

        $this->actingAs($actor)->postJson('/api/customers', [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'phone' => '(555) 555-0111',
        ])->assertCreated()
            ->assertJsonPath('email', null)
            ->assertJsonPath('home_country', 'United States')
            ->assertJsonPath('office_country', 'United States');
    }

    public function test_index_includes_countries_list(): void
    {
        $actor = $this->customerUser(['customers.view']);
        Customer::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        $this->actingAs($actor)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.first_name', 'Ada')
            ->assertJsonFragment(['United States'])
            ->assertJsonFragment(['United Kingdom']);
    }

    public function test_country_must_be_a_known_country_name(): void
    {
        $actor = $this->customerUser(['customers.create']);

        $this->actingAs($actor)
            ->postJson('/api/customers', [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'phone' => '(555) 555-0111',
                'home_country' => 'Not A Real Country',
                'office_country' => 'Also Fake',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['home_country', 'office_country']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function customerUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
