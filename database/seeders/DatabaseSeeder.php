<?php
// database/seeders/DatabaseSeeder.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: Seeders
//
// Seeders fill your database with initial data after running
// migrations. Run them with: php artisan db:seed
//
// Or do migrations + seeding in one command:
//   php artisan migrate:fresh --seed
//   (migrate:fresh drops ALL tables first, then rebuilds)
//
// Use seeders for:
//   - Required data (roles, default admin user, categories)
//   - Development test data (sample products)
//
// Never put production passwords in seeders — use environment
// variables or an interactive prompt instead.
// ─────────────────────────────────────────────────────────────

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────────
        $roles = [
            ['name' => 'super_admin', 'description' => 'Full system access'],
            ['name' => 'admin',       'description' => 'Store management access'],
            ['name' => 'customer',    'description' => 'Standard customer account'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        // ── Admin user ───────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@knlatelier.com'],
            [
                'role_id'    => 1,  // super_admin
                'first_name' => 'KNL',
                'last_name'  => 'Admin',
                'password'   => Hash::make('Admin@12345'), // change in production!
                'email_verified_at' => now(),
            ]
        );

        // ── Categories ───────────────────────────────────────
        $categories = [
            ['name' => 'Watches',     'slug' => 'watches',     'sort_order' => 1],
            ['name' => 'Sole',        'slug' => 'sole',        'sort_order' => 2],
            ['name' => 'Fragrance',   'slug' => 'fragrance',   'sort_order' => 3],
            ['name' => 'Gadgets',     'slug' => 'gadgets',     'sort_order' => 4],
            ['name' => 'Accessories', 'slug' => 'accessories', 'sort_order' => 5],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // ── Brands ───────────────────────────────────────────
        $brands = [
            ['name' => 'Seiko',        'slug' => 'seiko'],
            ['name' => 'Calvin Klein', 'slug' => 'calvin-klein'],
            ['name' => 'Reebok',       'slug' => 'reebok'],
            ['name' => 'Adidas',       'slug' => 'adidas'],
            ['name' => 'Guess',        'slug' => 'guess'],
            ['name' => 'Michael Kors', 'slug' => 'michael-kors'],
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(['slug' => $brand['slug']], $brand);
        }

        // ── Sample Products (from the PDF catalog) ────────────
        // Skip if products already exist (prevents SoftDeletes conflict on re-deploy)
        if (Product::count() === 0) {
            $watchCategoryId = Category::where('slug', 'watches')->value('id');
            $seikoId         = Brand::where('slug', 'seiko')->value('id');

            $products = [
                [
                    'name'          => 'SSK001',
                    'slug'          => 'seiko-ssk001-bruce-wayne',
                    'sku'           => 'SSK001',
                    'ref_number'    => 'SSK001',
                    'caliber_number'=> '4R34',
                    'short_desc'    => 'Brand new, rotated bezel, 100% authentic, and automatic.',
                    'price'         => 22999.00,
                    'is_featured'   => true,
                    'is_bestseller' => false,
                    'specifications'=> json_encode([
                        'nickname'   => 'Bruce Wayne',
                        'diameter'   => '42.5mm',
                        'bezel'      => 'Rotated',
                        'movement'   => 'Automatic',
                        'crystal'    => 'Hardlex',
                        'condition'  => 'New',
                        'inclusions' => 'Box, manuals, & warranty card',
                    ]),
                    'stock_quantity'  => 10,
                    'category_id'     => $watchCategoryId,
                    'brand_id'        => $seikoId,
                ],
                [
                    'name'          => 'SSK003',
                    'slug'          => 'seiko-ssk003-batman',
                    'sku'           => 'SSK003',
                    'ref_number'    => 'SSK003',
                    'caliber_number'=> '4R34',
                    'short_desc'    => 'Brand new, rotated bezel, 100% authentic, and automatic.',
                    'price'         => 21499.00,
                    'is_featured'   => true,
                    'is_bestseller' => true,
                    'specifications'=> json_encode([
                        'nickname'   => 'Batman',
                        'diameter'   => '42.5mm',
                        'bezel'      => 'Rotated',
                        'movement'   => 'Automatic',
                        'crystal'    => 'Hardlex',
                        'condition'  => 'New',
                        'inclusions' => 'Box, manuals, & warranty card',
                    ]),
                    'stock_quantity'  => 8,
                    'category_id'     => $watchCategoryId,
                    'brand_id'        => $seikoId,
                ],
                [
                    'name'          => 'SSK033',
                    'slug'          => 'seiko-ssk033-white-polar',
                    'sku'           => 'SSK033',
                    'ref_number'    => 'SSK033',
                    'caliber_number'=> '4R34',
                    'short_desc'    => 'Brand new, rotated bezel, 100% authentic, and automatic.',
                    'price'         => 22499.00,
                    'is_featured'   => true,
                    'is_bestseller' => false,
                    'specifications'=> json_encode([
                        'nickname'   => 'White Polar',
                        'diameter'   => '42.5mm',
                        'bezel'      => 'Rotated',
                        'movement'   => 'Automatic',
                        'crystal'    => 'Hardlex',
                        'condition'  => 'New',
                        'inclusions' => 'Box, manuals, & warranty card',
                    ]),
                    'stock_quantity'  => 5,
                    'category_id'     => $watchCategoryId,
                    'brand_id'        => $seikoId,
                ],
                [
                    'name'          => 'SRPD63',
                    'slug'          => 'seiko-srpd63-hulk',
                    'sku'           => 'SRPD63',
                    'ref_number'    => 'SRPD63',
                    'caliber_number'=> '4R36',
                    'short_desc'    => 'Brand new, rotated bezel, 100% authentic, and automatic.',
                    'price'         => 14499.00,
                    'is_featured'   => true,
                    'is_bestseller' => true,
                    'specifications'=> json_encode([
                        'nickname'   => 'Hulk',
                        'diameter'   => '42.5mm',
                        'bezel'      => 'Rotated',
                        'movement'   => 'Automatic',
                        'crystal'    => 'Hardlex',
                        'condition'  => 'New',
                        'inclusions' => 'Box, manuals, & warranty card',
                    ]),
                    'stock_quantity'  => 15,
                    'category_id'     => $watchCategoryId,
                    'brand_id'        => $seikoId,
                ],
            ];

            foreach ($products as $product) {
                Product::create($product);
            }
        }

        // ── Coupons ──────────────────────────────────────────
        // Matches the demo codes referenced in the Phase 3 checkout UI
        $coupons = [
            [
                'code'             => 'WELCOME10',
                'description'      => '10% off your first order',
                'type'             => 'percentage',
                'value'            => 10,
                'min_order_amount' => 0,
                'is_active'        => true,
            ],
            [
                'code'             => 'KNL500',
                'description'      => '₱500 off orders over ₱5,000',
                'type'             => 'fixed',
                'value'            => 500,
                'min_order_amount' => 5000,
                'is_active'        => true,
            ],
            [
                'code'                 => 'SEIKO20',
                'description'          => '20% off Seiko watches (max ₱5,000 discount)',
                'type'                 => 'percentage',
                'value'                => 20,
                'min_order_amount'     => 0,
                'max_discount_amount'  => 5000,
                'is_active'            => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            \App\Models\Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('   Admin: admin@knlatelier.com / Admin@12345');
    }
}
