<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_manage_vehicles(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->getJson('/api/vehicles')->assertForbidden();
        $this->postJson('/api/vehicles', [
            'customer_id' => $vehicle->customer_id,
            'year' => 2011,
            'make' => 'Nissan',
            'model' => 'Pathfinder',
            'type' => 'SUV',
        ])->assertForbidden();
        $this->putJson("/api/vehicles/{$vehicle->id}", [
            'customer_id' => $vehicle->customer_id,
            'year' => 2012,
            'make' => 'Nissan',
            'model' => 'Pathfinder',
            'type' => 'SUV',
        ])->assertForbidden();
        $this->deleteJson("/api/vehicles/{$vehicle->id}")->assertForbidden();
    }

    public function test_authorized_user_can_create_update_and_delete_a_vehicle(): void
    {
        Storage::fake('public');

        $actor = $this->vehicleUser([
            'vehicles.view',
            'vehicles.create',
            'vehicles.edit',
            'vehicles.delete',
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($actor)->post('/api/vehicles', [
            'customer_id' => $customer->id,
            'year' => 2011,
            'make' => 'Nissan',
            'model' => 'Pathfinder',
            'sub_model' => 'LE',
            'transmission' => 'Automatic',
            'engine_size' => '4.0L V6 (VQ40DE) GAS FI',
            'drivetrain' => '4WD',
            'type' => 'SUV',
            'mileage' => 100000,
            'vin' => '1N4AA5AP1BN123456',
            'image' => UploadedFile::fake()->image('pathfinder.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('customer_id', $customer->id)
            ->assertJsonPath('year', 2011)
            ->assertJsonPath('make', 'Nissan')
            ->assertJsonPath('model', 'Pathfinder')
            ->assertJsonPath('sub_model', 'LE')
            ->assertJsonPath('transmission', 'Automatic')
            ->assertJsonPath('drivetrain', '4WD')
            ->assertJsonPath('type', 'SUV')
            ->assertJsonPath('mileage', 100000)
            ->assertJsonPath('vin', '1N4AA5AP1BN123456')
            ->assertJsonPath('name', '2011 Nissan Pathfinder LE');

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertNotNull($vehicle->image_path);
        Storage::disk('public')->assertExists($vehicle->image_path);

        $this->actingAs($actor)->putJson("/api/vehicles/{$vehicle->id}", [
            'customer_id' => $customer->id,
            'year' => 2012,
            'make' => 'Nissan',
            'model' => 'Pathfinder',
            'sub_model' => 'SL',
            'transmission' => 'Automatic',
            'engine_size' => '4.0L V6',
            'drivetrain' => 'AWD',
            'type' => 'SUV',
            'mileage' => 105000,
            'vin' => '1N4AA5AP1BN123456',
        ])->assertOk()
            ->assertJsonPath('year', 2012)
            ->assertJsonPath('sub_model', 'SL')
            ->assertJsonPath('drivetrain', 'AWD')
            ->assertJsonPath('mileage', 105000);

        $this->actingAs($actor)->deleteJson("/api/vehicles/{$vehicle->id}")->assertOk();
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_create_requires_customer_year_make_model_and_type(): void
    {
        $actor = $this->vehicleUser(['vehicles.create']);

        $this->actingAs($actor)
            ->postJson('/api/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'year', 'make', 'model', 'type']);
    }

    public function test_vehicles_can_be_filtered_by_customer(): void
    {
        $actor = $this->vehicleUser(['vehicles.view']);
        $customer = Customer::factory()->create();
        $matched = Vehicle::factory()->create(['customer_id' => $customer->id]);
        Vehicle::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/vehicles?customer_id='.$customer->id)
            ->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.id', $matched->id)
            ->assertJsonPath('vehicles.0.customer_id', $customer->id);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function vehicleUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
