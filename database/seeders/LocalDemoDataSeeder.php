<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\Tag\Models\Tag;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;
use Webkul\Warehouse\Models\Location;
use Webkul\Warehouse\Models\Warehouse;

/**
 * Fills the local CRM with a demo dataset for every demo account: contacts,
 * a populated pipeline, quotes, activities, products, warehouses, tags, user
 * groups and a marketing event.
 *
 * Every record is matched on a natural key (email, sku, subject, ...), so the
 * seeder is safe to re-run and acts as a "reset this demo data" command.
 *
 * Usage:
 *   php artisan db:seed --class=Database\Seeders\LocalDemoDataSeeder
 */
class LocalDemoDataSeeder extends Seeder
{
    /**
     * Pipeline stage ids keyed by stage code (new, won, lost, ...).
     */
    protected Collection $stages;

    /**
     * Demo users keyed by e-mail address.
     *
     * @var array<string, User>
     */
    protected array $users = [];

    /**
     * Products keyed by sku.
     *
     * @var array<string, array{id: int, price: float}>
     */
    protected array $products = [];

    /**
     * Persons keyed by name.
     *
     * @var array<string, Person>
     */
    protected array $persons = [];

    /**
     * Organizations keyed by name.
     *
     * @var array<string, Organization>
     */
    protected array $organizations = [];

    /**
     * Tags keyed by name.
     *
     * @var array<string, Tag>
     */
    protected array $tags = [];

    /**
     * Leads keyed by title.
     *
     * @var array<string, Lead>
     */
    protected array $leads = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->warn('LocalDemoDataSeeder only runs in the local environment; nothing was seeded.');

            return;
        }

        $this->call(LocalDemoUsersSeeder::class);

        $this->users = $this->demoUsers();

        if (count($this->users) !== count(config('demo.accounts', []))) {
            $this->warn('Demo users are missing. Run: php artisan db:seed --class=Database\\Seeders\\LocalDemoUsersSeeder');

            return;
        }

        $pipeline = Pipeline::query()->where('is_default', 1)->first()
            ?: Pipeline::query()->orderBy('id')->first();

        if (! $pipeline) {
            $this->warn('No lead pipeline found - complete the Krayin installer first.');

            return;
        }

        $this->stages = $pipeline->stages()->pluck('id', 'code');

        $warehouses = $this->seedWarehouses();

        $this->seedGroups();
        $this->seedTags();
        $this->seedProducts($warehouses);
        $this->seedContacts();
        $this->seedLeads($pipeline);
        $this->seedQuotes();
        $this->seedActivities();
        $this->seedMarketingEvents();

        $this->info(sprintf(
            'Demo data ready: %d leads, %d quotes, %d persons, %d organizations, %d products.',
            Lead::query()->whereIn('user_id', $this->userIds())->count(),
            Quote::query()->whereIn('user_id', $this->userIds())->count(),
            Person::query()->whereIn('user_id', $this->userIds())->count(),
            Organization::query()->whereIn('user_id', $this->userIds())->count(),
            Product::query()->count()
        ));
    }

    /**
     * Resolve the demo accounts from config/demo.php to user models.
     *
     * @return array<string, User>
     */
    protected function demoUsers(): array
    {
        $users = [];

        foreach (config('demo.accounts', []) as $account) {
            $user = User::query()->where('email', $account['email'])->first();

            if ($user) {
                $users[$account['email']] = $user;
            }
        }

        return $users;
    }

    /**
     * Ids of every demo user.
     *
     * @return array<int, int>
     */
    protected function userIds(): array
    {
        return array_values(array_map(fn ($user) => $user->id, $this->users));
    }

    /**
     * Seed the user groups shown under Settings > Users > Groups.
     */
    protected function seedGroups(): void
    {
        $groups = [
            'Revenue Team' => [
                'description' => 'Quota-carrying sales team working the shared pipeline.',
                'users' => ['manager@krayin.local', 'agent@krayin.local'],
            ],
            'Customer Success' => [
                'description' => 'Post-sale support, renewals and customer health.',
                'users' => ['support@krayin.local'],
            ],
            'Leadership' => [
                'description' => 'Administrators and sales leadership with company-wide visibility.',
                'users' => ['admin@krayin.local', 'manager@krayin.local'],
            ],
        ];

        foreach ($groups as $name => $attributes) {
            $group = Group::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $attributes['description']]
            );

            $group->users()->syncWithoutDetaching(array_values(array_filter(array_map(
                fn (string $email) => isset($this->users[$email]) ? $this->users[$email]->id : null,
                $attributes['users']
            ))));
        }
    }

    /**
     * Seed two warehouses with two stock locations each.
     *
     * @return array<int, Warehouse>
     */
    protected function seedWarehouses(): array
    {
        $rows = [
            [
                'name' => 'Lagos Fulfilment Centre',
                'description' => 'Primary stock point for West African orders and hardware shipments.',
                'contact_name' => 'Tunde Bakare',
                'contact_emails' => [['value' => 'lagos@krayin.example', 'label' => 'work']],
                'contact_numbers' => [['value' => '+234 802 555 0110', 'label' => 'work']],
                'contact_address' => [
                    'address' => '18 Adeola Odeku Street, Victoria Island',
                    'postcode' => '101241',
                    'city' => 'Lagos',
                    'state' => 'LA',
                    'country' => 'NG',
                ],
                'locations' => ['Main Floor', 'Returns Bay'],
            ],
            [
                'name' => 'London Distribution Hub',
                'description' => 'European distribution hub for licences and training materials.',
                'contact_name' => 'Eleanor Whitmore',
                'contact_emails' => [['value' => 'london@krayin.example', 'label' => 'work']],
                'contact_numbers' => [['value' => '+44 20 7946 0311', 'label' => 'work']],
                'contact_address' => [
                    'address' => '42 Southwark Bridge Road',
                    'postcode' => 'SE1 9HH',
                    'city' => 'London',
                    'state' => 'ENG',
                    'country' => 'GB',
                ],
                'locations' => ['Dock A', 'Overflow Store'],
            ],
        ];

        $warehouses = [];

        foreach ($rows as $row) {
            $locations = $row['locations'];
            unset($row['locations']);

            $warehouse = Warehouse::query()->updateOrCreate(['name' => $row['name']], $row);

            foreach ($locations as $locationName) {
                Location::query()->updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'name' => $locationName],
                    ['name' => $locationName]
                );
            }

            $warehouses[] = $warehouse;
        }

        return $warehouses;
    }

    /**
     * Seed the tags used by record filters, kanban cards and the tag settings page.
     */
    protected function seedTags(): void
    {
        $admin = $this->users['admin@krayin.local'] ?? reset($this->users);

        $tags = [
            'enterprise' => '#FEE2E2',
            'smb' => '#DCFCE7',
            'renewal' => '#FEF3C7',
            'high-value' => '#FFEDD5',
            'at-risk' => '#FEF9C3',
            'partner' => '#ECFCCB',
        ];

        foreach ($tags as $name => $color) {
            $this->tags[$name] = Tag::query()->updateOrCreate(
                ['name' => $name],
                ['color' => $color, 'user_id' => $admin->id]
            );
        }
    }

    /**
     * Seed the product catalogue and the stock levels for both warehouses.
     *
     * @param  array<int, Warehouse>  $warehouses
     */
    protected function seedProducts(array $warehouses): void
    {
        $locations = Location::query()
            ->whereIn('warehouse_id', collect($warehouses)->pluck('id')->all())
            ->orderBy('id')
            ->get();

        if ($locations->isEmpty()) {
            $this->warn('No warehouse locations found; product stock was skipped.');

            return;
        }

        $rows = [
            ['sku' => 'KRAY-CRM-PRO', 'name' => 'Krayin CRM Professional (annual)', 'price' => 1200, 'description' => 'Up to 25 users with leads, quotes and activity management.', 'stock' => [24, 18, 9, 6], 'tags' => ['smb']],
            ['sku' => 'KRAY-CRM-ENT', 'name' => 'Krayin CRM Enterprise (annual)', 'price' => 4800, 'description' => 'Unlimited users, workflows, webhooks and priority support.', 'stock' => [12, 8, 5, 3], 'tags' => ['enterprise', 'high-value']],
            ['sku' => 'KRAY-SEAT', 'name' => 'Additional User Seat', 'price' => 240, 'description' => 'One extra named user for an existing subscription.', 'stock' => [60, 45, 30, 20], 'tags' => ['smb']],
            ['sku' => 'KRAY-IMPL-STD', 'name' => 'Standard Implementation Package', 'price' => 3500, 'description' => 'Configuration, data import and two remote enablement sessions.', 'stock' => [10, 8, 6, 4], 'tags' => ['renewal']],
            ['sku' => 'KRAY-IMPL-ENT', 'name' => 'Enterprise Onboarding & Migration', 'price' => 9500, 'description' => 'Dedicated architect, legacy CRM migration and on-site kickoff.', 'stock' => [5, 4, 3, 2], 'tags' => ['enterprise', 'high-value']],
            ['sku' => 'KRAY-SUPP-PRI', 'name' => 'Priority Support (monthly)', 'price' => 600, 'description' => 'Four-hour response SLA with a named support engineer.', 'stock' => [40, 32, 21, 14], 'tags' => ['renewal']],
            ['sku' => 'KRAY-SUPP-247', 'name' => '24/7 Support Add-on', 'price' => 1400, 'description' => 'Round-the-clock coverage for global sales teams.', 'stock' => [18, 12, 8, 5], 'tags' => ['enterprise']],
            ['sku' => 'KRAY-TRAIN-DAY', 'name' => 'Onsite Training Day', 'price' => 1800, 'description' => 'Full-day instructor-led training for up to fifteen users.', 'stock' => [8, 6, 4, 2], 'tags' => ['smb']],
            ['sku' => 'KRAY-INT-ERP', 'name' => 'ERP Integration Connector', 'price' => 2600, 'description' => 'Bi-directional sync with SAP, NetSuite or Odoo.', 'stock' => [14, 10, 7, 4], 'tags' => ['partner']],
            ['sku' => 'KRAY-INT-WA', 'name' => 'WhatsApp Channel Integration', 'price' => 900, 'description' => 'Capture and reply to WhatsApp conversations inside Krayin.', 'stock' => [22, 16, 11, 7], 'tags' => ['at-risk']],
        ];

        foreach ($rows as $row) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                    'quantity' => array_sum($row['stock']),
                ]
            );

            ProductInventory::query()->where('product_id', $product->id)->delete();

            foreach (array_values($row['stock']) as $index => $inStock) {
                $location = $locations[$index % $locations->count()];

                ProductInventory::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $location->warehouse_id,
                    'warehouse_location_id' => $location->id,
                    'in_stock' => $inStock,
                    'allocated' => intdiv($inStock, 6),
                ]);
            }

            $product->tags()->sync(array_values(array_filter(array_map(
                fn (string $tag) => isset($this->tags[$tag]) ? $this->tags[$tag]->id : null,
                $row['tags']
            ))));

            $this->products[$row['sku']] = ['id' => $product->id, 'name' => $row['name'], 'price' => (float) $row['price']];
        }
    }

    /**
     * Seed organizations, then the people that belong to them.
     */
    protected function seedContacts(): void
    {
        $organizations = [
            [
                'name' => 'Northwind Trading',
                'owner' => 'admin@krayin.local',
                'address' => ['address' => '1420 Harbor Boulevard', 'postcode' => '94107', 'city' => 'San Francisco', 'state' => 'CA', 'country' => 'US'],
            ],
            [
                'name' => 'Helios Manufacturing',
                'owner' => 'admin@krayin.local',
                'address' => ['address' => 'Industriestrasse 44', 'postcode' => '80339', 'city' => 'Munich', 'state' => 'BY', 'country' => 'DE'],
            ],
            [
                'name' => 'Bluewave Logistics',
                'owner' => 'manager@krayin.local',
                'address' => ['address' => '7 Trafford Wharf Road', 'postcode' => 'M17 1HH', 'city' => 'Manchester', 'state' => 'ENG', 'country' => 'GB'],
            ],
            [
                'name' => 'Sahara Retail Group',
                'owner' => 'manager@krayin.local',
                'address' => ['address' => '12 Ozumba Mbadiwe Avenue', 'postcode' => '106104', 'city' => 'Lagos', 'state' => 'LA', 'country' => 'NG'],
            ],
            [
                'name' => 'Cobalt Software',
                'owner' => 'agent@krayin.local',
                'address' => ['address' => '600 Congress Avenue, Suite 1400', 'postcode' => '78701', 'city' => 'Austin', 'state' => 'TX', 'country' => 'US'],
            ],
            [
                'name' => 'Meadow Foods',
                'owner' => 'agent@krayin.local',
                'address' => ['address' => '8 Queen Square', 'postcode' => 'BS1 4NH', 'city' => 'Bristol', 'state' => 'ENG', 'country' => 'GB'],
            ],
            [
                'name' => 'Orchid Health',
                'owner' => 'support@krayin.local',
                'address' => ['address' => '55 Hudson Yards, Floor 21', 'postcode' => '10001', 'city' => 'New York', 'state' => 'NY', 'country' => 'US'],
            ],
            [
                'name' => 'Halcyon Media',
                'owner' => 'support@krayin.local',
                'address' => ['address' => '90 King Street West', 'postcode' => 'M5H 1J9', 'city' => 'Toronto', 'state' => 'ON', 'country' => 'CA'],
            ],
        ];

        foreach ($organizations as $row) {
            $owner = $this->users[$row['owner']] ?? null;

            if (! $owner) {
                continue;
            }

            $this->organizations[$row['name']] = Organization::query()->updateOrCreate(
                ['name' => $row['name'], 'user_id' => $owner->id],
                ['address' => $row['address']]
            );
        }

        $this->seedPersons();
    }

    /**
     * Seed the people (contact persons) and their tags.
     */
    protected function seedPersons(): void
    {
        $persons = [
            ['name' => 'Grace Adeyemi', 'organization' => 'Northwind Trading', 'owner' => 'admin@krayin.local', 'email' => 'grace.adeyemi@northwind.example', 'phone' => '+1 415 555 0142', 'job_title' => 'VP Operations', 'tags' => ['enterprise', 'high-value']],
            ['name' => 'Marcus Vogel', 'organization' => 'Helios Manufacturing', 'owner' => 'admin@krayin.local', 'email' => 'marcus.vogel@helios.example', 'phone' => '+49 89 5550 118', 'job_title' => 'Plant Director', 'tags' => ['enterprise']],
            ['name' => 'Lena Fischer', 'organization' => 'Helios Manufacturing', 'owner' => 'admin@krayin.local', 'email' => 'lena.fischer@helios.example', 'phone' => '+49 89 5550 204', 'job_title' => 'Procurement Lead', 'tags' => ['renewal']],
            ['name' => 'Amara Bello', 'organization' => 'Sahara Retail Group', 'owner' => 'manager@krayin.local', 'email' => 'amara.bello@sahararetail.example', 'phone' => '+234 803 555 0187', 'job_title' => 'Head of Retail', 'tags' => ['smb']],
            ['name' => 'Tom Whitfield', 'organization' => 'Bluewave Logistics', 'owner' => 'manager@krayin.local', 'email' => 'tom.whitfield@bluewave.example', 'phone' => '+44 161 555 0134', 'job_title' => 'Chief Operating Officer', 'tags' => ['enterprise', 'partner']],
            ['name' => 'Ifeoma Nwosu', 'organization' => 'Bluewave Logistics', 'owner' => 'manager@krayin.local', 'email' => 'ifeoma.nwosu@bluewave.example', 'phone' => '+44 161 555 0177', 'job_title' => 'Fleet Manager', 'tags' => ['smb']],
            ['name' => 'Sofia Martinez', 'organization' => 'Cobalt Software', 'owner' => 'agent@krayin.local', 'email' => 'sofia.martinez@cobalt.example', 'phone' => '+1 512 555 0166', 'job_title' => 'Revenue Operations Manager', 'tags' => ['high-value']],
            ['name' => 'James Carter', 'organization' => 'Meadow Foods', 'owner' => 'agent@krayin.local', 'email' => 'james.carter@meadowfoods.example', 'phone' => '+44 117 555 0198', 'job_title' => 'Commercial Director', 'tags' => ['smb', 'renewal']],
            ['name' => 'Hana Sato', 'organization' => 'Orchid Health', 'owner' => 'support@krayin.local', 'email' => 'hana.sato@orchidhealth.example', 'phone' => '+1 212 555 0149', 'job_title' => 'Support Lead', 'tags' => ['enterprise']],
            ['name' => 'Ravi Menon', 'organization' => 'Halcyon Media', 'owner' => 'support@krayin.local', 'email' => 'ravi.menon@halcyonmedia.example', 'phone' => '+1 416 555 0121', 'job_title' => 'Managing Editor', 'tags' => ['at-risk']],
        ];

        foreach ($persons as $row) {
            $owner = $this->users[$row['owner']] ?? null;
            $organization = $this->organizations[$row['organization']] ?? null;

            if (! $owner || ! $organization) {
                continue;
            }

            $person = Person::query()->updateOrCreate(
                ['name' => $row['name'], 'user_id' => $owner->id],
                [
                    'emails' => [['value' => $row['email'], 'label' => 'work']],
                    'contact_numbers' => [['value' => $row['phone'], 'label' => 'work']],
                    'job_title' => $row['job_title'],
                    'organization_id' => $organization->id,
                    'unique_id' => implode('|', [$owner->id, $organization->id, $row['email'], $row['phone']]),
                ]
            );

            $person->tags()->sync(array_values(array_filter(array_map(
                fn (string $tag) => isset($this->tags[$tag]) ? $this->tags[$tag]->id : null,
                $row['tags']
            ))));

            $this->persons[$row['name']] = $person;
        }
    }

    /**
     * Seed the pipeline: six leads per demo owner covering every stage.
     */
    protected function seedLeads(Pipeline $pipeline): void
    {
        $sources = DB::table('lead_sources')->pluck('id', 'name');
        $types = DB::table('lead_types')->pluck('id', 'name');

        foreach ($this->leadRows() as $row) {
            $owner = $this->users[$row['owner']] ?? null;
            $person = $this->persons[$row['person']] ?? null;
            $stage = $this->stages[$row['stage']] ?? null;

            if (! $owner || ! $person || ! $stage) {
                continue;
            }

            $closed = in_array($row['stage'], ['won', 'lost'], true);
            $createdAt = Carbon::now()->subDays($row['created']);

            $lead = Lead::query()->updateOrCreate(
                ['title' => $row['title'], 'user_id' => $owner->id],
                [
                    'description' => $row['description'],
                    'lead_value' => $row['value'],
                    'status' => $closed ? 0 : 1,
                    'lost_reason' => $row['stage'] === 'lost'
                        ? 'Budget deferred to the next fiscal year; the buyer renewed with the incumbent vendor.'
                        : null,
                    'expected_close_date' => Carbon::now()->addDays($row['expected'])->toDateString(),
                    'closed_at' => $closed ? Carbon::now()->subDays($row['closed'])->toDateTimeString() : null,
                    'person_id' => $person->id,
                    'lead_source_id' => $sources[$row['source']] ?? null,
                    'lead_type_id' => $types[$row['type']] ?? null,
                    'lead_pipeline_id' => $pipeline->id,
                    'lead_pipeline_stage_id' => $stage,
                ]
            );

            DB::table('leads')->where('id', $lead->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('lead_products')->where('lead_id', $lead->id)->delete();

            foreach ($row['products'] ?? [] as $sku => $quantity) {
                if (! isset($this->products[$sku])) {
                    continue;
                }

                DB::table('lead_products')->insert([
                    'lead_id' => $lead->id,
                    'product_id' => $this->products[$sku]['id'],
                    'quantity' => $quantity,
                    'price' => $this->products[$sku]['price'],
                    'amount' => $this->products[$sku]['price'] * $quantity,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $lead->tags()->sync(array_values(array_filter(array_map(
                fn (string $tag) => isset($this->tags[$tag]) ? $this->tags[$tag]->id : null,
                $row['tags']
            ))));

            $this->leads[$row['title']] = $lead;
        }
    }

    /**
     * Demo lead rows: six per owner, one per pipeline stage.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function leadRows(): array
    {
        return [
            ['title' => 'Northwind Trading - Enterprise licence renewal', 'owner' => 'admin@krayin.local', 'person' => 'Grace Adeyemi', 'value' => 42000, 'stage' => 'negotiation', 'source' => 'Email', 'type' => 'Existing Business', 'tags' => ['enterprise', 'high-value'], 'products' => ['KRAY-CRM-ENT' => 2, 'KRAY-SUPP-PRI' => 1], 'created' => 42, 'expected' => 18, 'description' => 'Renewal of the 200-seat enterprise plan plus priority support for the next fiscal year.'],
            ['title' => 'Helios Manufacturing - Plant maintenance rollout', 'owner' => 'admin@krayin.local', 'person' => 'Marcus Vogel', 'value' => 78500, 'stage' => 'new', 'source' => 'Web Form', 'type' => 'New Business', 'tags' => ['enterprise'], 'products' => [], 'created' => 6, 'expected' => 45, 'description' => 'Inbound request to replace spreadsheets with a maintenance request pipeline across three plants.'],
            ['title' => 'Helios Manufacturing - Spare parts storefront', 'owner' => 'admin@krayin.local', 'person' => 'Lena Fischer', 'value' => 18500, 'stage' => 'follow-up', 'source' => 'Phone', 'type' => 'New Business', 'tags' => ['renewal'], 'products' => [], 'created' => 19, 'expected' => 25, 'description' => 'Procurement wants spare parts ordering tracked alongside customer quotes and stock levels.'],
            ['title' => 'Northwind Trading - Field sales mobile seats', 'owner' => 'admin@krayin.local', 'person' => 'Grace Adeyemi', 'value' => 9600, 'stage' => 'prospect', 'source' => 'Direct', 'type' => 'Existing Business', 'tags' => ['smb'], 'products' => ['KRAY-SEAT' => 15], 'created' => 27, 'expected' => 30, 'description' => 'Fifteen additional mobile seats for the West Coast field team, starting next quarter.'],
            ['title' => 'Helios Manufacturing - ERP integration phase 2', 'owner' => 'admin@krayin.local', 'person' => 'Lena Fischer', 'value' => 31800, 'stage' => 'won', 'source' => 'Email', 'type' => 'Existing Business', 'tags' => ['partner', 'high-value'], 'products' => ['KRAY-INT-ERP' => 1, 'KRAY-IMPL-STD' => 1], 'created' => 24, 'expected' => 0, 'closed' => 9, 'description' => 'Second phase of the SAP sync project, signed after a successful pilot on the sales team.'],
            ['title' => 'Northwind Trading - Warehouse automation pilot', 'owner' => 'admin@krayin.local', 'person' => 'Grace Adeyemi', 'value' => 26000, 'stage' => 'lost', 'source' => 'Web', 'type' => 'New Business', 'tags' => ['at-risk'], 'products' => [], 'created' => 25, 'expected' => 0, 'closed' => 16, 'description' => 'Pilot deferred after the buyer committed to an incumbent warehouse automation vendor.'],
            ['title' => 'Bluewave Logistics - Fleet tracking CRM rollout', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'value' => 54000, 'stage' => 'negotiation', 'source' => 'Email', 'type' => 'New Business', 'tags' => ['enterprise'], 'products' => ['KRAY-CRM-PRO' => 1, 'KRAY-TRAIN-DAY' => 2], 'created' => 38, 'expected' => 14, 'description' => 'Rolling Krayin out to 120 depot staff, with driver follow-ups tracked as activities.'],
            ['title' => 'Sahara Retail Group - Store associate enablement', 'owner' => 'manager@krayin.local', 'person' => 'Amara Bello', 'value' => 22800, 'stage' => 'new', 'source' => 'Web Form', 'type' => 'New Business', 'tags' => ['smb'], 'products' => [], 'created' => 4, 'expected' => 40, 'description' => 'Associates use web forms to capture in-store leads; head office wants one pipeline view.'],
            ['title' => 'Bluewave Logistics - Customer portal refresh', 'owner' => 'manager@krayin.local', 'person' => 'Ifeoma Nwosu', 'value' => 15600, 'stage' => 'follow-up', 'source' => 'Phone', 'type' => 'Existing Business', 'tags' => ['renewal'], 'products' => [], 'created' => 22, 'expected' => 28, 'description' => 'Follow-up call booked to scope a self-service portal for shipment quotes.'],
            ['title' => 'Sahara Retail Group - Loyalty programme integration', 'owner' => 'manager@krayin.local', 'person' => 'Amara Bello', 'value' => 18750, 'stage' => 'prospect', 'source' => 'Direct', 'type' => 'New Business', 'tags' => ['high-value'], 'products' => ['KRAY-INT-WA' => 1], 'created' => 29, 'expected' => 33, 'description' => 'Loyalty sign-ups routed into CRM via the WhatsApp channel integration.'],
            ['title' => 'Bluewave Logistics - Annual support renewal', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'value' => 24600, 'stage' => 'won', 'source' => 'Email', 'type' => 'Existing Business', 'tags' => ['renewal'], 'products' => ['KRAY-SUPP-PRI' => 2, 'KRAY-SEAT' => 10], 'created' => 27, 'expected' => 0, 'closed' => 6, 'description' => 'Support renewal plus ten extra seats, closed after the quarterly business review.'],
            ['title' => 'Sahara Retail Group - POS migration', 'owner' => 'manager@krayin.local', 'person' => 'Amara Bello', 'value' => 62000, 'stage' => 'lost', 'source' => 'Web', 'type' => 'New Business', 'tags' => ['at-risk'], 'products' => [], 'created' => 29, 'expected' => 0, 'closed' => 20, 'description' => 'Lost on price to a POS vendor that bundles a lightweight CRM for free.'],
            ['title' => 'Cobalt Software - RevOps workspace', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'value' => 14400, 'stage' => 'negotiation', 'source' => 'Web', 'type' => 'New Business', 'tags' => ['high-value'], 'products' => ['KRAY-CRM-PRO' => 1, 'KRAY-INT-ERP' => 1], 'created' => 24, 'expected' => 12, 'description' => 'RevOps wants leads, quotes and renewals in one workspace for a 30-person team.'],
            ['title' => 'Meadow Foods - Field sales app pilot', 'owner' => 'agent@krayin.local', 'person' => 'James Carter', 'value' => 8900, 'stage' => 'new', 'source' => 'Phone', 'type' => 'New Business', 'tags' => ['smb'], 'products' => [], 'created' => 2, 'expected' => 35, 'description' => 'Inbound call from the commercial director asking for a two-week field sales pilot.'],
            ['title' => 'Cobalt Software - Additional 25 seats', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'value' => 6000, 'stage' => 'follow-up', 'source' => 'Email', 'type' => 'Existing Business', 'tags' => ['smb'], 'products' => ['KRAY-SEAT' => 25], 'created' => 16, 'expected' => 21, 'description' => 'Seat expansion quote sent; waiting on procurement approval before month end.'],
            ['title' => 'Meadow Foods - Distributor onboarding portal', 'owner' => 'agent@krayin.local', 'person' => 'James Carter', 'value' => 21500, 'stage' => 'prospect', 'source' => 'Web Form', 'type' => 'New Business', 'tags' => ['renewal'], 'products' => [], 'created' => 31, 'expected' => 38, 'description' => 'Distributor onboarding currently runs on spreadsheets; scoping a web form workflow.'],
            ['title' => 'Cobalt Software - Enterprise upgrade', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'value' => 38000, 'stage' => 'won', 'source' => 'Direct', 'type' => 'Existing Business', 'tags' => ['enterprise'], 'products' => ['KRAY-CRM-ENT' => 1, 'KRAY-TRAIN-DAY' => 2], 'created' => 22, 'expected' => 0, 'closed' => 4, 'description' => 'Upgraded to Enterprise after outgrowing the Professional plan seat cap.'],
            ['title' => 'Meadow Foods - Seasonal campaign module', 'owner' => 'agent@krayin.local', 'person' => 'James Carter', 'value' => 7400, 'stage' => 'lost', 'source' => 'Email', 'type' => 'New Business', 'tags' => ['at-risk'], 'products' => [], 'created' => 21, 'expected' => 0, 'closed' => 14, 'description' => 'Buyer decided to run seasonal campaigns directly from their e-commerce platform.'],
            ['title' => 'Orchid Health - Patient referral CRM', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'value' => 27500, 'stage' => 'negotiation', 'source' => 'Email', 'type' => 'New Business', 'tags' => ['enterprise'], 'products' => ['KRAY-CRM-ENT' => 1, 'KRAY-SUPP-247' => 1], 'created' => 26, 'expected' => 16, 'description' => 'Referral follow-ups from six clinics need a single, auditable pipeline.'],
            ['title' => 'Halcyon Media - Editorial pipeline workspace', 'owner' => 'support@krayin.local', 'person' => 'Ravi Menon', 'value' => 11200, 'stage' => 'new', 'source' => 'Web', 'type' => 'New Business', 'tags' => ['smb'], 'products' => [], 'created' => 1, 'expected' => 32, 'description' => 'Editorial team asked for a lightweight workspace to track pitches and sponsorships.'],
            ['title' => 'Orchid Health - Support desk integration', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'value' => 16400, 'stage' => 'follow-up', 'source' => 'Phone', 'type' => 'Existing Business', 'tags' => ['renewal'], 'products' => [], 'created' => 20, 'expected' => 24, 'description' => 'Follow-up on connecting the support desk so patient tickets appear on the timeline.'],
            ['title' => 'Halcyon Media - Subscription renewals module', 'owner' => 'support@krayin.local', 'person' => 'Ravi Menon', 'value' => 9300, 'stage' => 'prospect', 'source' => 'Direct', 'type' => 'Existing Business', 'tags' => ['smb'], 'products' => ['KRAY-CRM-PRO' => 1], 'created' => 30, 'expected' => 36, 'description' => 'Subscriber renewals currently handled by e-mail; wants quotes and reminders in CRM.'],
            ['title' => 'Orchid Health - Multi-clinic rollout', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'value' => 46500, 'stage' => 'won', 'source' => 'Web Form', 'type' => 'Existing Business', 'tags' => ['enterprise'], 'products' => ['KRAY-CRM-ENT' => 1, 'KRAY-SUPP-247' => 1], 'created' => 26, 'expected' => 0, 'closed' => 11, 'description' => 'Signed after a successful pilot in two clinics; rolling out to the remaining four.'],
            ['title' => 'Halcyon Media - Ad sales forecasting', 'owner' => 'support@krayin.local', 'person' => 'Ravi Menon', 'value' => 12800, 'stage' => 'lost', 'source' => 'Web', 'type' => 'New Business', 'tags' => ['at-risk'], 'products' => [], 'created' => 19, 'expected' => 0, 'closed' => 8, 'description' => 'Forecasting initiative shelved while the advertising team reorganises.'],
        ];
    }

    /**
     * Seed quotes (with line items) for every demo owner and link them to leads.
     */
    protected function seedQuotes(): void
    {
        $rows = [
            ['subject' => 'Northwind Trading - Enterprise renewal proposal', 'owner' => 'admin@krayin.local', 'person' => 'Grace Adeyemi', 'lead' => 'Northwind Trading - Enterprise licence renewal', 'items' => ['KRAY-CRM-ENT' => 2, 'KRAY-SUPP-PRI' => 1], 'created' => 18, 'expires_in' => 22],
            ['subject' => 'Helios Manufacturing - ERP phase 2 statement of work', 'owner' => 'admin@krayin.local', 'person' => 'Lena Fischer', 'lead' => 'Helios Manufacturing - ERP integration phase 2', 'items' => ['KRAY-INT-ERP' => 1, 'KRAY-IMPL-STD' => 1], 'created' => 12, 'expires_in' => 30],
            ['subject' => 'Bluewave Logistics - Fleet rollout proposal', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'lead' => 'Bluewave Logistics - Fleet tracking CRM rollout', 'items' => ['KRAY-CRM-PRO' => 1, 'KRAY-TRAIN-DAY' => 2], 'created' => 16, 'expires_in' => 24],
            ['subject' => 'Bluewave Logistics - Support renewal order form', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'lead' => 'Bluewave Logistics - Annual support renewal', 'items' => ['KRAY-SUPP-PRI' => 2, 'KRAY-SEAT' => 10], 'created' => 8, 'expires_in' => 20],
            ['subject' => 'Cobalt Software - RevOps bundle quote', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'lead' => 'Cobalt Software - RevOps workspace', 'items' => ['KRAY-CRM-PRO' => 1, 'KRAY-INT-ERP' => 1], 'created' => 10, 'expires_in' => 18],
            ['subject' => 'Cobalt Software - Seat expansion quote', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'lead' => 'Cobalt Software - Additional 25 seats', 'items' => ['KRAY-SEAT' => 25], 'created' => 6, 'expires_in' => 15],
            ['subject' => 'Orchid Health - Multi-clinic rollout proposal', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'lead' => 'Orchid Health - Multi-clinic rollout', 'items' => ['KRAY-CRM-ENT' => 1, 'KRAY-SUPP-247' => 1], 'created' => 14, 'expires_in' => 26],
            ['subject' => 'Halcyon Media - Renewals workspace quote', 'owner' => 'support@krayin.local', 'person' => 'Ravi Menon', 'lead' => 'Halcyon Media - Editorial pipeline workspace', 'items' => ['KRAY-CRM-PRO' => 1], 'created' => 9, 'expires_in' => 19],
        ];

        foreach ($rows as $row) {
            $owner = $this->users[$row['owner']] ?? null;
            $person = $this->persons[$row['person']] ?? null;

            if (! $owner || ! $person) {
                continue;
            }

            $subTotal = 0.0;
            $lines = [];

            foreach ($row['items'] as $sku => $quantity) {
                if (! isset($this->products[$sku])) {
                    continue;
                }

                $total = $this->products[$sku]['price'] * $quantity;
                $subTotal += $total;

                $lines[] = [
                    'sku' => $sku,
                    'name' => $this->products[$sku]['name'],
                    'quantity' => $quantity,
                    'price' => $this->products[$sku]['price'],
                    'total' => $total,
                ];
            }

            if ($lines === []) {
                continue;
            }

            $address = $person->organization?->address ?? [];
            $createdAt = Carbon::now()->subDays($row['created']);

            $quote = Quote::query()->updateOrCreate(
                ['subject' => $row['subject'], 'user_id' => $owner->id],
                [
                    'description' => 'Demo proposal covering the products listed below.',
                    'billing_address' => $address,
                    'shipping_address' => $address,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'adjustment_amount' => 0,
                    'sub_total' => $subTotal,
                    'grand_total' => $subTotal,
                    'expired_at' => Carbon::now()->addDays($row['expires_in'])->toDateTimeString(),
                    'person_id' => $person->id,
                ]
            );

            DB::table('quotes')->where('id', $quote->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            QuoteItem::query()->where('quote_id', $quote->id)->delete();

            foreach ($lines as $line) {
                QuoteItem::query()->create([
                    'sku' => $line['sku'],
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                    'coupon_code' => null,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_percent' => 0,
                    'tax_amount' => 0,
                    'total' => $line['total'],
                    'product_id' => $this->products[$line['sku']]['id'],
                    'quote_id' => $quote->id,
                ]);
            }

            if (isset($this->leads[$row['lead']])) {
                $quote->leads()->syncWithoutDetaching([$this->leads[$row['lead']]->id]);
            }
        }
    }

    /**
     * Seed calls, meetings and lunches so the activity calendar and timelines
     * are populated for every demo owner.
     */
    protected function seedActivities(): void
    {
        foreach ($this->activityRows() as $row) {
            $owner = $this->users[$row['owner']] ?? null;
            $person = $this->persons[$row['person']] ?? null;

            if (! $owner || ! $person) {
                continue;
            }

            $lead = $this->leads[$row['lead']] ?? null;
            $from = Carbon::now()->addDays($row['days'])->setTime(10, 0);

            $activity = Activity::query()->updateOrCreate(
                ['title' => $row['title'], 'user_id' => $owner->id],
                [
                    'type' => $row['type'],
                    'comment' => $row['comment'],
                    'schedule_from' => $from,
                    'schedule_to' => $from->copy()->addHour(),
                    'is_done' => $row['done'],
                ]
            );

            $activity->persons()->sync([$person->id]);

            if ($lead) {
                $activity->leads()->sync([$lead->id]);
            }

            DB::table('activity_participants')->updateOrInsert(
                [
                    'activity_id' => $activity->id,
                    'user_id' => $owner->id,
                    'person_id' => $person->id,
                ],
                []
            );
        }
    }

    /**
     * Seed marketing events used by campaigns and the events settings screen.
     */
    protected function seedMarketingEvents(): void
    {
        $events = [
            ['name' => 'Q4 Enterprise Webinar', 'description' => 'Roadmap session for enterprise accounts and partners.', 'date' => Carbon::now()->addDays(14)->toDateString()],
            ['name' => 'SMB Growth Summit', 'description' => 'Regional event for small business prospects.', 'date' => Carbon::now()->addDays(35)->toDateString()],
        ];

        foreach ($events as $event) {
            DB::table('marketing_events')->updateOrInsert(
                ['name' => $event['name']],
                [
                    'description' => $event['description'],
                    'date' => $event['date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Demo activity rows: three per owner, mixing completed and upcoming work.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function activityRows(): array
    {
        return [
            ['title' => 'Discovery call with Grace Adeyemi', 'owner' => 'admin@krayin.local', 'person' => 'Grace Adeyemi', 'lead' => 'Northwind Trading - Enterprise licence renewal', 'type' => 'call', 'days' => -3, 'done' => 1, 'comment' => 'Confirmed the renewal scope; procurement still needs the updated order form.'],
            ['title' => 'Proposal review with Lena Fischer', 'owner' => 'admin@krayin.local', 'person' => 'Lena Fischer', 'lead' => 'Helios Manufacturing - ERP integration phase 2', 'type' => 'meeting', 'days' => 2, 'done' => 0, 'comment' => 'Walk through the phase 2 statement of work and the integration timeline.'],
            ['title' => 'Quarterly check-in lunch with Marcus Vogel', 'owner' => 'admin@krayin.local', 'person' => 'Marcus Vogel', 'lead' => 'Helios Manufacturing - Plant maintenance rollout', 'type' => 'lunch', 'days' => 5, 'done' => 0, 'comment' => 'Relationship check-in ahead of the maintenance rollout decision.'],
            ['title' => 'Fleet rollout scoping call', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'lead' => 'Bluewave Logistics - Fleet tracking CRM rollout', 'type' => 'call', 'days' => -2, 'done' => 1, 'comment' => 'Depot managers will need a simplified mobile view during the pilot.'],
            ['title' => 'Renewal negotiation with Tom Whitfield', 'owner' => 'manager@krayin.local', 'person' => 'Tom Whitfield', 'lead' => 'Bluewave Logistics - Annual support renewal', 'type' => 'meeting', 'days' => 3, 'done' => 0, 'comment' => 'Agree the multi-year discount and the additional ten seats.'],
            ['title' => 'Store visit with Amara Bello', 'owner' => 'manager@krayin.local', 'person' => 'Amara Bello', 'lead' => 'Sahara Retail Group - Store associate enablement', 'type' => 'lunch', 'days' => 7, 'done' => 0, 'comment' => 'Observe in-store lead capture before pitching the enablement programme.'],
            ['title' => 'RevOps demo for Sofia Martinez', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'lead' => 'Cobalt Software - RevOps workspace', 'type' => 'meeting', 'days' => -1, 'done' => 1, 'comment' => 'Demoed the lead board, quote builder and activity timeline to the RevOps team.'],
            ['title' => 'Follow-up call with James Carter', 'owner' => 'agent@krayin.local', 'person' => 'James Carter', 'lead' => 'Meadow Foods - Field sales app pilot', 'type' => 'call', 'days' => 1, 'done' => 0, 'comment' => 'Confirm pilot users and the data they want imported before kickoff.'],
            ['title' => 'Seat expansion review with Sofia Martinez', 'owner' => 'agent@krayin.local', 'person' => 'Sofia Martinez', 'lead' => 'Cobalt Software - Additional 25 seats', 'type' => 'meeting', 'days' => 4, 'done' => 0, 'comment' => 'Review the seat usage report and confirm the expansion order.'],
            ['title' => 'Support handover call with Hana Sato', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'lead' => 'Orchid Health - Patient referral CRM', 'type' => 'call', 'days' => -4, 'done' => 1, 'comment' => 'Documented referral follow-up rules and the escalation path for clinics.'],
            ['title' => 'Clinic rollout planning with Hana Sato', 'owner' => 'support@krayin.local', 'person' => 'Hana Sato', 'lead' => 'Orchid Health - Multi-clinic rollout', 'type' => 'meeting', 'days' => 2, 'done' => 0, 'comment' => 'Sequence the remaining four clinics and agree the training dates.'],
            ['title' => 'Editorial workshop with Ravi Menon', 'owner' => 'support@krayin.local', 'person' => 'Ravi Menon', 'lead' => 'Halcyon Media - Editorial pipeline workspace', 'type' => 'lunch', 'days' => 6, 'done' => 0, 'comment' => 'Map the pitch and sponsorship stages with the editorial leads.'],
        ];
    }

    /**
     * Write a line to the console when the seeder runs from artisan.
     */
    protected function warn(string $message): void
    {
        if ($this->command) {
            $this->command->warn($message);
        }
    }

    /**
     * Write a line to the console when the seeder runs from artisan.
     */
    protected function info(string $message): void
    {
        if ($this->command) {
            $this->command->info($message);
        }
    }
}
