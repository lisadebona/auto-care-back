<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\User;
use Database\Seeders\CurrentDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrentDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_data_seeder_restores_the_shop_snapshot(): void
    {
        $this->seed(CurrentDataSeeder::class);

        $this->assertSame(5, User::query()->count());
        $this->assertSame(2, Customer::query()->count());
        $this->assertSame(3, Estimate::query()->count());
        $this->assertSame(9, DB::table('brands')->count());
        $this->assertSame(12, DB::table('products')->count());
        $this->assertSame(4, DB::table('vehicles')->count());
        $this->assertSame(1, DB::table('custom_workflows')->count());
        $this->assertSame(9, DB::table('estimate_line_items')->count());
        $this->assertDatabaseHas('users', [
            'email' => 'testuser3@example.com',
        ]);
        $this->assertDatabaseHas('estimates', [
            'number' => 1000,
            'invoice_number' => '1001',
            'po_number' => 'PO11000',
            'workflow' => 'invoices',
        ]);
        $this->assertDatabaseHas('custom_workflows', [
            'name' => 'Sprinter Van',
        ]);
        $this->assertTrue(User::query()->where('email', 'testuser3@example.com')->first()->hasRole('super-admin'));
    }
}
