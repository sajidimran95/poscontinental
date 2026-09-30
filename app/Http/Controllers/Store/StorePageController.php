<?php

namespace App\Http\Controllers\Store;

use App\Models\EcomContactMessage;
use App\Support\Store\StoreContext;
use Illuminate\Http\Request;

class StorePageController extends StoreController
{
    public function show(string $slug)
    {
        $pages = $this->pages();
        abort_unless(isset($pages[$slug]), 404);

        return view('store.home.page', ['page' => $pages[$slug]]);
    }

    public function contact()
    {
        return view('store.home.contact');
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:40',
            'message' => 'required|string|max:5000',
        ]);

        EcomContactMessage::query()->create($data + ['company_id' => $this->companyId()]);

        return back()->with('success', 'Thanks — our sales team will get back to you shortly.');
    }

    /** @return array<string, array{title:string, body:string}> */
    private function pages(): array
    {
        $shop = StoreContext::shopInfo();
        $name = $shop['name'];
        $contact = implode(' · ', array_filter([$shop['phone'], $shop['email']])) ?: 'our sales team';
        $where = $shop['address_line'] ? ' We are based at '.$shop['address_line'].'.' : '';

        return [
            'about-us' => [
                'title' => 'About Us',
                'body' => "{$name} is a wholesale distributor serving licensed retailers, convenience stores and smoke shops with tobacco, cigarettes, candy, snacks, drinks, grocery and general merchandise.{$where}\n\nWe offer competitive wholesale pricing, route delivery and live inventory straight from our warehouse system. Contact us: {$contact}.",
            ],
            'wholesale-faq' => [
                'title' => 'Wholesale FAQ',
                'body' => "Who can open an account?\nLicensed retailers only. You will need a business certificate, resale certificate, and (if applicable) tobacco license.\n\nHow do I see my prices?\nPrices shown to guests are list prices. After your account is approved, log in to see your account pricing.\n\nWhen will my order be delivered?\nOnline orders go straight into our order system and are delivered on your route day or prepared for pickup.\n\nPayment terms?\nPay on delivery, or use the payment terms on your approved account.",
            ],
            'shipping-policy' => [
                'title' => 'Shipping Policy',
                'body' => "Online orders are reviewed and prepared by our warehouse team. Most orders are delivered on your regular route day. Age-restricted products are delivered only to licensed wholesale accounts. Contact {$contact} for delivery questions.",
            ],
            'return-policy' => [
                'title' => 'Return Policy',
                'body' => "Damaged or incorrect items must be reported at delivery or within 7 days. Contact us first for a return authorization. Opened tobacco, nicotine, food or hygiene-sensitive products cannot be returned unless defective. Credits are applied to your account after inspection.",
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'body' => "We collect business contact details, license information, and order history to operate wholesale accounts. We do not sell personal information. Contact {$contact} for privacy requests.",
            ],
            'terms-of-service' => [
                'title' => 'Terms of Service',
                'body' => "By using this site you confirm you are 21+ and a licensed retailer. Product images are for illustration. Prices and stock change without notice. We may refuse or cancel orders that fail age or license verification.",
            ],
            'age-compliance' => [
                'title' => 'Age Compliance',
                'body' => "You must be 21 years of age or older to enter this site. This store sells tobacco, nicotine and age-restricted products for licensed retailers only. We verify licenses before fulfillment. Under-21 access is prohibited.",
            ],
        ];
    }
}
