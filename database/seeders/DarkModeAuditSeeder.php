<?php

namespace Database\Seeders;

use App\Models\BasementQuotation;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use App\Models\ClientProjectTimeEntry;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\Helper;
use App\Models\LowDeckQuotation;
use App\Models\Material;
use App\Models\MillworkQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\Project;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\SupplierSite;
use App\Models\Task;
use App\Models\TileQuotation;
use App\Models\TrimmingQuotation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic records so dark-mode screenshots and the Puppeteer audit
 * show values, not only empty states.
 */
class DarkModeAuditSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@zanini.ca';

    public const ADMIN_PASSWORD = 'password';

    /** @var array<string, mixed> */
    public array $ids = [];

    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Jonas Zanini',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => User::ROLE_ADMIN,
            ]
        );

        $client = Client::query()->firstOrCreate(
            ['email' => 'maria.darkmode@example.test'],
            [
                'name' => 'Maria Costa',
                'phone' => '4165550199',
                'address' => '12 Maple Ave, Toronto, ON',
            ]
        );

        $hourly = ClientProject::query()->firstOrCreate(
            ['name' => 'Hourly finish carpentry — dark mode'],
            [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
                'client_phone' => $client->phone,
                'client_address' => $client->address,
                'status' => ClientProject::STATUS_IN_PROGRESS,
                'billing_type' => ClientProject::BILLING_HOURLY,
                'hourly_rate' => 70,
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->addDays(4)->toDateString(),
            ]
        );

        $fixed = ClientProject::query()->firstOrCreate(
            ['name' => 'Kitchen renovation — dark mode'],
            [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
                'client_phone' => $client->phone,
                'client_address' => $client->address,
                'status' => ClientProject::STATUS_IN_PROGRESS,
                'billing_type' => ClientProject::BILLING_FIXED,
                'payment_plan' => 2,
                'total_amount' => 18500,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(21)->toDateString(),
            ]
        );

        $quotation = ClientProjectQuotation::query()->firstOrCreate(
            ['quotation_number' => 'QUO-DM-0001'],
            [
                'client_project_id' => $fixed->id,
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'status' => ClientProjectQuotation::STATUS_ACCEPTED,
                'labour' => true,
                'material' => true,
                'items' => [
                    ['description' => 'Kitchen labour and material', 'amount' => 18500],
                ],
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            ]
        );

        $fixed->installments()->firstOrCreate(
            ['sort_order' => 1],
            ['label' => 'Deposit', 'percentage' => 50, 'amount' => 9250, 'is_paid' => true, 'paid_on' => now()->subDays(3)]
        );
        $fixed->installments()->firstOrCreate(
            ['sort_order' => 2],
            ['label' => 'Final', 'percentage' => 50, 'amount' => 9250, 'is_paid' => false]
        );

        $billedInvoice = ClientProjectInvoice::query()->firstOrCreate(
            ['invoice_number' => 'INV-DM-1001'],
            [
                'client_project_id' => $hourly->id,
                'installment_description' => 'Week of Sept 22 — carpentry',
                'items' => [
                    ['description' => 'Labour 8.00 h @ CA$70.00/h', 'amount' => 560, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
                ],
                'subtotal' => 560,
                'hst' => 72.80,
                'total' => 632.80,
                'status' => ClientProjectInvoice::STATUS_SENT,
                'sent_at' => now()->subDays(2),
            ]
        );

        $draftInvoice = ClientProjectInvoice::query()->firstOrCreate(
            ['invoice_number' => 'INV-DM-1002'],
            [
                'client_project_id' => $hourly->id,
                'installment_description' => 'Materials — stain and fasteners',
                'items' => [
                    ['description' => 'Minwax stain + fasteners', 'amount' => 186.40, 'line_kind' => 'material'],
                ],
                'subtotal' => 186.40,
                'hst' => 24.23,
                'total' => 210.63,
                'status' => ClientProjectInvoice::STATUS_DRAFT,
            ]
        );

        ClientProjectTimeEntry::query()->firstOrCreate(
            [
                'client_project_id' => $hourly->id,
                'occurred_on' => now()->subDays(5)->toDateString(),
                'note' => 'Trim and casing',
            ],
            [
                'hours' => 8,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'client_project_invoice_id' => $billedInvoice->id,
            ]
        );
        ClientProjectTimeEntry::query()->firstOrCreate(
            [
                'client_project_id' => $hourly->id,
                'occurred_on' => now()->subDays(1)->toDateString(),
                'note' => 'Doors and hardware',
            ],
            [
                'hours' => 6.5,
                'start_time' => '08:00',
                'end_time' => '14:30',
                'client_project_invoice_id' => null,
            ]
        );

        ClientProjectExpense::query()->firstOrCreate(
            [
                'client_project_id' => $hourly->id,
                'description' => 'Home Depot — stain',
            ],
            [
                'occurred_on' => now()->subDays(2)->toDateString(),
                'amount' => 86.42,
            ]
        );
        ClientProjectExpense::query()->firstOrCreate(
            [
                'client_project_id' => $fixed->id,
                'description' => 'Cabinet hardware',
            ],
            [
                'occurred_on' => now()->subDays(1)->toDateString(),
                'amount' => 214.00,
            ]
        );

        ClientProjectContract::query()->firstOrCreate(
            ['client_project_id' => $fixed->id],
            [
                'html_content' => '<p>Dark-mode audit contract for the kitchen renovation.</p>',
                'version' => 1,
            ]
        );

        $helper = Helper::query()->firstOrCreate(
            ['email' => 'helper.darkmode@example.test'],
            ['name' => 'Alex Helper', 'hourly_rate' => 35, 'is_active' => true]
        );
        $hourly->helpers()->syncWithoutDetaching([$helper->id]);

        $vehicle = Vehicle::query()->firstOrCreate(
            ['name' => 'Work van'],
            ['make' => 'Ford', 'model' => 'Transit', 'year' => 2021, 'is_active' => true]
        );

        $material = Material::query()->firstOrCreate(
            ['name' => 'Dark Mode 2x4 SPF'],
            ['is_active' => true, 'store_sku' => 'DM-2X4']
        );

        SupplierSite::query()->firstOrCreate(
            ['name' => 'Home Depot — Dark Mode'],
            ['url' => 'https://www.homedepot.ca', 'is_favorite' => true]
        );

        $blogCat = BlogCategory::query()->firstOrCreate(
            ['slug' => 'dark-mode-tips'],
            ['name' => 'Dark Mode Tips']
        );
        $post = BlogPost::query()->firstOrCreate(
            ['slug' => 'dark-mode-audit-post'],
            [
                'title' => 'Dark mode audit post',
                'html_content' => '<p>Sample published post for the admin blog editor.</p>',
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => now(),
            ]
        );
        $placeholderPath = $this->blogListPlaceholderPath();
        if ($placeholderPath !== null) {
            $post->forceFill([
                'featured_image_path' => $placeholderPath,
                'featured_image_alt' => 'Dark mode audit placeholder',
            ])->save();
        }
        if (method_exists($post, 'categories')) {
            $post->categories()->syncWithoutDetaching([$blogCat->id]);
        }

        $portfolio = Project::query()->firstOrCreate(
            ['slug' => 'dark-mode-portfolio'],
            [
                'title' => 'Dark Mode Portfolio Kitchen',
                'description' => 'Sample portfolio project used by the dark-mode audit.',
            ]
        );

        Task::query()->firstOrCreate(
            ['title' => 'Call Maria about stain colour'],
            [
                'notes' => 'Follow up tomorrow morning.',
                'due_at' => now()->addDay(),
                'client_project_id' => $hourly->id,
                'created_by' => $admin->id,
            ]
        );

        $fence = FenceQuotation::query()->firstOrCreate(
            ['quote_number' => 'FENCE-DM-1'],
            [
                'client_name' => $client->name,
                'sides' => [['length_ft' => 24, 'height_ft' => 6]],
                'calculation' => ['materials' => [], 'totals' => ['grand_total' => 4200]],
                'grand_total' => 4200,
                'work_description' => 'Backyard cedar fence.',
            ]
        );

        $lowDeck = LowDeckQuotation::query()->firstOrCreate(
            ['quote_number' => 'DECK-DM-1'],
            [
                'client_name' => $client->name,
                'width_ft' => 12,
                'length_ft' => 16,
                'calculation' => ['totals' => ['grand_total' => 6800]],
                'grand_total' => 6800,
            ]
        );

        $standard = StandardQuotation::query()->firstOrCreate(
            ['quote_number' => 'STD-DM-1'],
            [
                'client_name' => $client->name,
                'materials' => [
                    ['description' => '2x4 SPF', 'qty' => 20, 'unit' => 'pcs', 'price' => 4.5],
                ],
                'grand_total' => 18500,
                'display_title' => 'Kitchen carpentry',
            ]
        );

        // Link the project quotation so /quotations/{id}/edit redirects to the
        // specialised editor instead of aborting 404 (SOURCE_STANDARD with no source_id).
        if (! $quotation->source_id) {
            $quotation->source_id = $standard->id;
            $quotation->source_type = ClientProjectQuotation::SOURCE_STANDARD;
            $quotation->save();
        }

        $calcQuotes = [
            'paving' => [PavingQuotation::class, 'PAV-DM-1'],
            'stairs' => [StairsQuotation::class, 'STAIR-DM-1'],
            'painting' => [PaintingQuotation::class, 'PAINT-DM-1'],
            'flooring' => [FlooringQuotation::class, 'FL-DM-1'],
            'tile' => [TileQuotation::class, 'TILE-DM-1'],
            'basement' => [BasementQuotation::class, 'BASE-DM-1'],
            'shed' => [ShedQuotation::class, 'SHED-DM-1'],
            'millwork' => [MillworkQuotation::class, 'MW-DM-1'],
            'trimming' => [TrimmingQuotation::class, 'TRIM-DM-1'],
        ];

        $calcIds = [];
        foreach ($calcQuotes as $key => [$class, $number]) {
            $row = $class::query()->firstOrCreate(
                ['quote_number' => $number],
                [
                    'client_name' => $client->name,
                    'inputs' => ['sample' => true],
                    'calculation' => ['grand_total' => 2500],
                    'final_quote_total' => 2500,
                ]
            );
            $calcIds[$key] = $row->id;
        }

        $this->ids = [
            'admin_email' => self::ADMIN_EMAIL,
            'admin_password' => self::ADMIN_PASSWORD,
            'admin_user_id' => $admin->id,
            'client_id' => $client->id,
            'hourly_project_id' => $hourly->id,
            'fixed_project_id' => $fixed->id,
            'quotation_id' => $quotation->id,
            'invoice_id' => $draftInvoice->id,
            'sent_invoice_id' => $billedInvoice->id,
            'helper_id' => $helper->id,
            'vehicle_id' => $vehicle->id,
            'material_id' => $material->id,
            'portfolio_project_id' => $portfolio->id,
            'fence_quotation_id' => $fence->id,
            'low_deck_quotation_id' => $lowDeck->id,
            'standard_quotation_id' => $standard->id,
            'blog_post_id' => $post->id,
            'blog_category_id' => $blogCat->id,
        ] + collect($calcIds)->mapWithKeys(fn ($id, $key) => ["{$key}_quotation_id" => $id])->all();
    }

    private function blogListPlaceholderPath(): ?string
    {
        $relative = 'blog/list-placeholder.webp';
        $dest = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($dest)) && ! mkdir(dirname($dest), 0775, true) && ! is_dir(dirname($dest))) {
            return null;
        }

        foreach ([
            public_path('images/services/deck.webp'),
            public_path('images/services/kitchen-remodel.webp'),
            public_path('images/about-us/jonas.webp'),
        ] as $src) {
            if (is_file($src) && copy($src, $dest)) {
                return $relative;
            }
        }

        return is_file($dest) ? $relative : null;
    }
}
