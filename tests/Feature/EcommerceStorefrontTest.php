<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\EcomContactMessage;
use App\Models\EcomOrderMeta;
use App\Models\EcomPromotion;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Store\WholesaleAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EcommerceStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::query()->create([
            'code' => 'TST',
            'name' => 'Test Co',
            'is_active' => true,
            'allow_negative_stock' => true,
            'ecommerce_enabled' => true,
            'ecommerce_store_name' => 'Test Store',
        ]);
        User::factory()->create(['company_id' => $this->company->id]);
        $this->item = Item::query()->create([
            'company_id' => $this->company->id,
            'item_code' => 'SKU-1',
            'description' => 'Test chips',
            'quantity_in_stock' => 50,
            'allocated_qty' => 0,
            'on_order_qty' => 0,
            'list_price' => 12.5,
            'can_sell' => true,
            'is_inactive' => false,
        ]);
    }

    public function test_store_is_hidden_when_disabled(): void
    {
        $this->company->update(['ecommerce_enabled' => false]);

        $this->get('/')->assertRedirect('/login');
        $this->get('/shop')->assertNotFound();
        $this->get('/shop/p/'.$this->item->id)->assertNotFound();
    }

    public function test_public_pages_render_when_enabled(): void
    {
        foreach (['/', '/shop', '/shop/promotions', '/shop/brands', '/shop/cart', '/shop/login', '/shop/register', '/shop/contact', '/shop/page/about-us', '/shop/track', '/shop/p/'.$this->item->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/shop?q=chips')->assertOk()->assertSee('Test chips')->assertSee('12.50');
    }

    public function test_unpriced_items_are_not_listed_or_orderable(): void
    {
        $free = Item::query()->create([
            'company_id' => $this->company->id,
            'item_code' => 'SKU-0',
            'description' => 'Unpriced thing',
            'quantity_in_stock' => 5,
            'list_price' => 0,
            'can_sell' => true,
            'is_inactive' => false,
        ]);

        $this->get('/shop')->assertOk()->assertDontSee('Unpriced thing');
        $this->get('/shop/p/'.$free->id)->assertNotFound();
    }

    public function test_register_approve_and_checkout_creates_ecommerce_sales_order(): void
    {
        Storage::fake('local');

        $this->post('/shop/cart/add', ['item_id' => $this->item->id, 'quantity' => 3])->assertRedirect();

        $this->post('/shop/register', [
            'business_name' => 'Corner Mart',
            'mobile' => '555-0100',
            'contact_name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'secret12',
            'password_confirmation' => 'secret12',
            'address_line_1' => '1 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip_code' => '75001',
            'licenses' => [['type' => 'tobacco', 'number' => 'TB-123', 'file' => UploadedFile::fake()->create('lic.pdf', 20, 'application/pdf')]],
            'agree_terms' => '1',
        ])->assertRedirect(route('ecommerce.account'));

        $customer = Customer::query()->where('portal_email', 'jane@example.com')->firstOrFail();
        $this->assertSame(WholesaleAccount::PENDING, $customer->web_status);
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertDatabaseCount('ecom_customer_licenses', 1);

        // Guest cart followed the customer into their account.
        $this->get('/shop/cart')->assertOk()->assertSee('Test chips');

        // Pending accounts cannot check out.
        $this->get('/shop/checkout')->assertRedirect(route('ecommerce.cart'));

        foreach (['/shop/account', '/shop/account/profile', '/shop/account/password', '/shop/account/addresses', '/shop/account/licenses', '/shop/wishlist', '/shop/quick-order'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/shop/account/addresses', [
            'first_name' => 'Jane', 'last_name' => 'Buyer', 'address_line_1' => '9 Side St',
            'city' => 'Dallas', 'state' => 'TX', 'zip_code' => '75002',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $customer->shippingAddresses()->count());

        $customer->update(['web_status' => WholesaleAccount::APPROVED, 'portal_active' => true]);
        $this->actingAs($customer->fresh(), 'customer');

        $this->get('/shop/checkout')->assertOk();
        $response = $this->post('/shop/checkout', ['email' => 'jane@example.com', 'phone' => '555-0100', 'ship_to_address_id' => '0']);

        $meta = EcomOrderMeta::query()->firstOrFail();
        $response->assertRedirect(route('ecommerce.thanks', $meta->tracking_token));

        $order = SalesOrder::query()->with('lines')->findOrFail($meta->sales_order_id);
        $this->assertSame(SalesOrder::SOURCE_ECOMMERCE, $order->order_source);
        $this->assertSame((int) $customer->id, (int) $order->customer_id);
        $this->assertEquals(3, (float) $order->lines->first()->qty_ordered);
        $this->assertEquals(12.5, (float) $order->lines->first()->price);

        $this->get(route('ecommerce.thanks', $meta->tracking_token))->assertOk()->assertSee($order->order_number);
        $this->get('/shop/track?token='.$meta->tracking_token)->assertOk();
        $this->get('/shop/account/orders')->assertOk();
        $this->assertDatabaseCount('ecom_cart_items', 0);

        $admin = User::query()->where('company_id', $this->company->id)->firstOrFail();
        $this->actingAs($admin);
        Volt::test('pages.sales.orders.index')
            ->set('dateFrom', '')
            ->set('dateTo', '')
            ->assertSee($order->order_number)
            ->assertSee('Ecommerce')
            ->assertSee('Corner Mart (jane@example.com)');
        $this->actingAs($admin)->get(route('sales.orders.show', $order))->assertOk()->assertSee('Ecommerce');    }

    public function test_admin_promotion_shows_on_store_and_discounts_the_order(): void
    {
        $dept = Department::query()->create(['company_id' => $this->company->id, 'code' => 'GR', 'name' => 'Grocery', 'is_active' => true]);
        $snacks = Category::query()->create(['company_id' => $this->company->id, 'department_id' => $dept->id, 'code' => 'SN', 'name' => 'Snacks', 'is_active' => true]);
        $this->item->update(['category_id' => $snacks->id]);
        $admin = User::query()->where('company_id', $this->company->id)->firstOrFail();

        $this->actingAs($admin)->get(route('admin.ecommerce-promotions'))->assertOk();
        Volt::test('pages.admin.ecommerce-promotions')
            ->call('create')
            ->set('title', 'Snack Attack')
            ->set('subtitle', 'All snacks 20% off')
            ->set('target', 'category')
            ->set('category_id', $snacks->id)
            ->set('discount_type', 'percent')
            ->set('discount_value', '20')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Snack Attack')
            ->assertSee('Running');
        $promo = EcomPromotion::query()->firstOrFail();

        $this->get('/')->assertOk()->assertSee('Current Promotions')->assertSee('Snack Attack');
        $this->get('/shop/promotions')->assertOk()->assertSee('Test chips')->assertSee('20% OFF')->assertSee('10.00')->assertSee('12.50');
        $this->get(route('ecommerce.promotion', $promo->id))->assertOk()->assertSee('All snacks 20% off')->assertSee('Test chips');

        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => 'W2',
            'company_name' => 'Deal Mart',
            'portal_email' => 'deal@example.com',
            'portal_password' => bcrypt('secret12'),
            'web_status' => WholesaleAccount::APPROVED,
            'web_registered_at' => now(),
        ]);
        $this->actingAs($customer, 'customer');
        $this->post('/shop/cart/add', ['item_id' => $this->item->id, 'quantity' => 2])->assertRedirect();
        $this->post('/shop/checkout', ['email' => 'deal@example.com', 'ship_to_address_id' => '0'])->assertRedirect();

        $line = SalesOrder::query()->where('customer_id', $customer->id)->firstOrFail()->lines()->firstOrFail();
        $this->assertEquals(10.0, (float) $line->price);

        // With no promotion running, the store falls back to automatic category promotions.
        $promo->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('Snack Attack')->assertSee('Current Promotions')->assertSee(route('ecommerce.category', $snacks->id));
        $this->get('/shop/promotions')->assertOk()->assertDontSee('20% OFF')->assertSee('Test chips')->assertSee(route('ecommerce.category', $snacks->id));
        $this->get('/shop/promotions/'.$promo->id)->assertNotFound();
    }

    public function test_admin_can_choose_navbar_categories_with_automatic_top_six_default(): void
    {
        $dept = Department::query()->create(['company_id' => $this->company->id, 'code' => 'GR', 'name' => 'Grocery', 'is_active' => true]);
        $cats = collect(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf'])->values()->map(function (string $name, int $i) use ($dept) {
            $cat = Category::query()->create(['company_id' => $this->company->id, 'department_id' => $dept->id, 'code' => strtoupper(substr($name, 0, 3)), 'name' => $name, 'is_active' => true]);
            foreach (range(0, 6 - $i) as $n) {
                Item::query()->create([
                    'company_id' => $this->company->id, 'item_code' => $name.'-'.$n, 'description' => $name.' item '.$n,
                    'quantity_in_stock' => 10, 'list_price' => 5, 'can_sell' => true, 'is_inactive' => false, 'category_id' => $cat->id,
                ]);
            }

            return $cat;
        });
        $top6 = $cats->take(6)->pluck('id')->all();

        $admin = User::query()->where('company_id', $this->company->id)->firstOrFail();
        $this->actingAs($admin);
        $page = Volt::test('pages.admin.ecommerce-settings')
            ->assertSet('nav_custom', false)
            ->assertSet('nav_category_ids', $top6)
            ->assertSee('Navbar categories');

        // Swap Alpha for Golf and move Golf up one place.
        $page->call('removeNavCategory', 0)
            ->set('nav_add_id', (string) $cats[6]->id)
            ->call('addNavCategory')
            ->call('moveNavCategory', 5, -1)
            ->call('save')
            ->assertHasNoErrors();
        $expected = [$cats[1]->id, $cats[2]->id, $cats[3]->id, $cats[4]->id, $cats[6]->id, $cats[5]->id];
        $this->assertSame($expected, $this->company->fresh()->ecommerce_nav_category_ids);
        $this->assertSame($expected, \App\Support\Store\StoreCatalog::headerCategories($this->company->id)->pluck('id')->all());

        $page->call('useAutomaticNav')->call('save')->assertSet('nav_custom', false);
        $this->assertNull($this->company->fresh()->ecommerce_nav_category_ids);
    }

    public function test_admin_settings_page_renders(): void
    {
        $admin = User::query()->where('company_id', $this->company->id)->firstOrFail();

        $this->actingAs($admin)->get('/admin/ecommerce-settings')
            ->assertOk()
            ->assertSee('Test Store')
            ->assertSee('Online store activity')
            ->assertDontSee('Recent online orders')
            ->assertDontSee('Ecommerce Orders')
            ->assertSee('Open Online Store');

        $applicant = Customer::query()->create([
            'company_id' => $this->company->id,
            'customer_id' => 'W1',
            'company_name' => 'Pending Mart',
            'portal_email' => 'pending@example.com',
            'web_status' => WholesaleAccount::PENDING,
            'web_registered_at' => now(),
        ]);
        $message = EcomContactMessage::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Sam Asker',
            'email' => 'sam@example.com',
            'message' => 'Do you deliver to Austin?',
        ]);

        $this->get(route('admin.ecommerce-applications'))->assertOk()->assertSee('Pending Mart');
        Volt::test('pages.admin.ecommerce-applications')
            ->call('setStatus', $applicant->id, WholesaleAccount::APPROVED)
            ->assertSee('Pending Mart is now');
        $this->assertSame(WholesaleAccount::APPROVED, $applicant->fresh()->web_status);

        $this->get(route('admin.ecommerce-messages'))->assertOk()->assertSee('Do you deliver to Austin?');
        Volt::test('pages.admin.ecommerce-messages')->call('markRead', $message->id)->assertSee('No unread messages.');
        $this->assertNotNull($message->fresh()->read_at);
    }
}
