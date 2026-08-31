<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Item;
use App\Models\Subcategory;
use App\Support\TobaccoItem;
use Tests\TestCase;

class TobaccoItemTest extends TestCase
{
    public function test_mi_cigarettes_category_is_cigarettes(): void
    {
        $item = $this->item(['state_reporting' => true]);
        $item->setRelation('category', new Category(['name' => 'MI Cigarettes']));
        $item->setRelation('subcategory', new Subcategory(['name' => 'Brands']));

        $this->assertTrue(TobaccoItem::isTobacco($item));
        $this->assertSame('cigarettes', TobaccoItem::kind($item));
        $this->assertTrue(TobaccoItem::matchesProduct($item, 'cigarettes'));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'otp'));
    }

    public function test_tobacco_cigars_are_otp_not_cigarettes(): void
    {
        $item = $this->item(['state_reporting' => true]);
        $item->setRelation('category', new Category(['name' => 'Tobacco']));
        $item->setRelation('subcategory', new Subcategory(['name' => 'Cigars']));

        $this->assertTrue(TobaccoItem::isTobacco($item));
        $this->assertSame('cigarillo', TobaccoItem::kind($item));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'cigarettes'));
        $this->assertTrue(TobaccoItem::matchesProduct($item, 'otp'));
    }

    public function test_candy_is_not_tobacco(): void
    {
        $item = $this->item();
        $item->setRelation('category', new Category(['name' => 'Candy']));
        $item->setRelation('subcategory', new Subcategory(['name' => '18 CT']));

        $this->assertFalse(TobaccoItem::isTobacco($item));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'cigarettes'));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'otp'));
    }

    public function test_unchecked_reporting_is_excluded_even_if_tobacco_category(): void
    {
        $item = $this->item(['msa_reporting' => false, 'state_reporting' => false]);
        $item->setRelation('category', new Category(['name' => 'MI Cigarettes']));

        $this->assertTrue(TobaccoItem::isTobacco($item));
        $this->assertFalse(TobaccoItem::reportsMsa($item));
        $this->assertFalse(TobaccoItem::reportsState($item));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'cigarettes', 'state'));
        $this->assertFalse(TobaccoItem::matchesProduct($item, 'cigarettes', 'msa'));
    }

    public function test_suggested_form_type_from_category(): void
    {
        $cig = $this->item();
        $cig->setRelation('category', new Category(['name' => 'MI Cigarettes']));
        $cig->setRelation('subcategory', new Subcategory(['name' => 'Brands']));
        $this->assertSame('cigarettes', TobaccoItem::suggestedFormType($cig));

        $otp = $this->item();
        $otp->setRelation('category', new Category(['name' => 'Tobacco']));
        $otp->setRelation('subcategory', new Subcategory(['name' => 'Cigars']));
        $this->assertSame('otp', TobaccoItem::suggestedFormType($otp));

        $candy = $this->item();
        $candy->setRelation('category', new Category(['name' => 'Candy']));
        $this->assertNull(TobaccoItem::suggestedFormType($candy));
    }

    /** @param  array<string, mixed>  $extra */
    private function item(array $extra = []): Item
    {
        return new Item(array_merge([
            'tobacco_product_type' => '',
            'msa_reporting' => false,
            'state_reporting' => false,
        ], $extra));
    }
}
