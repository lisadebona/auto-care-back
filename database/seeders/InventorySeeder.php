<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Seed the default inventory data.
     */
    public function run(): void
    {
        Category::query()->firstOrCreate(['name' => 'Uncategorized']);

        $categories = collect([
            'Brakes',
            'Filters',
            'Fluids',
            'Ignition',
            'Wipers',
            'Electrical',
            'Belts',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Category::query()->firstOrCreate(['name' => $name]),
        ]);

        $brands = collect([
            'Bosch',
            'Fram',
            'Mobil',
            'Wix',
            'NGK',
            'Interstate',
            'Gates',
            'Prestone',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Brand::query()->firstOrCreate(['name' => $name]),
        ]);

        $products = [
            [
                'name' => 'Ceramic Brake Pad Set',
                'description' => 'Front ceramic brake pads for common passenger cars. Low dust and quiet stopping.',
                'unit_price' => 28.50,
                'margin' => 45,
                'stock_quantity' => 24,
                'brand' => 'Bosch',
                'category' => 'Brakes',
                'upc_code' => '028851234001',
                'part_number' => 'BP-1042',
            ],
            [
                'name' => 'Oil Filter',
                'description' => 'Spin-on engine oil filter. Fits many domestic and import applications.',
                'unit_price' => 6.25,
                'margin' => 50,
                'stock_quantity' => 40,
                'brand' => 'Fram',
                'category' => 'Filters',
                'upc_code' => '041652338701',
                'part_number' => 'OF-3387A',
            ],
            [
                'name' => '5W-30 Synthetic Motor Oil, 5 qt',
                'description' => 'Full synthetic 5W-30 motor oil jug for oil changes.',
                'unit_price' => 24.00,
                'margin' => 35,
                'stock_quantity' => 30,
                'brand' => 'Mobil',
                'category' => 'Fluids',
                'upc_code' => '071924453001',
                'part_number' => 'MO-5W30-5',
            ],
            [
                'name' => 'Engine Air Filter',
                'description' => 'Panel engine air filter that keeps dirt out of the intake.',
                'unit_price' => 11.40,
                'margin' => 55,
                'stock_quantity' => 18,
                'brand' => 'Wix',
                'category' => 'Filters',
                'upc_code' => '765809491562',
                'part_number' => 'AF-49156',
            ],
            [
                'name' => 'Iridium Spark Plug',
                'description' => 'Long-life iridium spark plug. Sold individually.',
                'unit_price' => 8.75,
                'margin' => 60,
                'stock_quantity' => 48,
                'brand' => 'NGK',
                'category' => 'Ignition',
                'upc_code' => '087295546511',
                'part_number' => 'SP-LFR5AIX',
            ],
            [
                'name' => 'Beam Wiper Blade, 22 in',
                'description' => '22-inch beam wiper blade for rain and road spray.',
                'unit_price' => 9.50,
                'margin' => 70,
                'stock_quantity' => 16,
                'brand' => 'Bosch',
                'category' => 'Wipers',
                'upc_code' => '028851339221',
                'part_number' => 'WB-22A',
            ],
            [
                'name' => 'Group 35 Car Battery',
                'description' => 'Group 35 automotive battery, 650 CCA, for many sedans and crossovers.',
                'unit_price' => 89.00,
                'margin' => 40,
                'stock_quantity' => 6,
                'brand' => 'Interstate',
                'category' => 'Electrical',
                'upc_code' => '041333356501',
                'part_number' => 'BAT-35-650',
            ],
            [
                'name' => 'Serpentine Belt',
                'description' => 'Multi-rib serpentine belt for the alternator, power steering, and A/C.',
                'unit_price' => 18.20,
                'margin' => 50,
                'stock_quantity' => 12,
                'brand' => 'Gates',
                'category' => 'Belts',
                'upc_code' => '072053608251',
                'part_number' => 'SB-K060825',
            ],
            [
                'name' => 'Cabin Air Filter',
                'description' => 'Cabin filter that reduces dust and pollen in the passenger compartment.',
                'unit_price' => 12.80,
                'margin' => 55,
                'stock_quantity' => 20,
                'brand' => 'Wix',
                'category' => 'Filters',
                'upc_code' => '765809293901',
                'part_number' => 'CF-2939',
            ],
            [
                'name' => 'Prediluted Coolant, 1 gal',
                'description' => '50/50 prediluted antifreeze and coolant, ready to pour.',
                'unit_price' => 14.60,
                'margin' => 45,
                'stock_quantity' => 22,
                'brand' => 'Prestone',
                'category' => 'Fluids',
                'upc_code' => '079340002100',
                'part_number' => 'CL-AF2100',
            ],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['part_number' => $product['part_number']],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'unit_price' => $product['unit_price'],
                    'margin' => $product['margin'],
                    'stock_quantity' => $product['stock_quantity'],
                    'brand_id' => $brands[$product['brand']]->id,
                    'category_id' => $categories[$product['category']]->id,
                    'upc_code' => $product['upc_code'],
                    'is_taxable' => true,
                ],
            );
        }
    }
}
