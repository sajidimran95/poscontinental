<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const NAME_NEEDLES = [
        'tobacco',
        'cigaret',
        'cigar',
        'snuff',
        'pipe',
        'nic pou',
        'nicotine',
        'vape',
        'disposable',
        'e-cig',
        'electronic',
        'ryo',
        'tube',
    ];

    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'msa_reporting')) {
                $table->boolean('msa_reporting')->default(false)->after('tobacco_stick_count');
            }
            if (! Schema::hasColumn('items', 'state_reporting')) {
                $table->boolean('state_reporting')->default(false)->after('msa_reporting');
            }
        });

        if (Schema::hasColumn('items', 'msa_reporting') && Schema::hasColumn('items', 'state_reporting')) {
            $this->backfillExistingTobaccoItems();
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            foreach (['state_reporting', 'msa_reporting'] as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfillExistingTobaccoItems(): void
    {
        $q = DB::table('items')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->leftJoin('subcategories', 'subcategories.id', '=', 'items.subcategory_id')
            ->where(function ($w) {
                $w->where(function ($x) {
                    $x->whereNotNull('items.tobacco_product_type')
                        ->where('items.tobacco_product_type', '!=', '');
                })
                    ->orWhere(function ($x) {
                        $x->whereNotNull('items.tobacco_brand_code')
                            ->where('items.tobacco_brand_code', '!=', '');
                    })
                    ->orWhere('items.tobacco_stick_count', '>', 0)
                    ->orWhere('items.tobacco_total_oz', '>', 0);
                foreach (self::NAME_NEEDLES as $needle) {
                    $like = '%'.$needle.'%';
                    $w->orWhereRaw('LOWER(categories.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(subcategories.name) LIKE ?', [$like]);
                }
            });

        $ids = $q->pluck('items.id')->all();
        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('items')->whereIn('id', $chunk)->update([
                'msa_reporting' => true,
                'state_reporting' => true,
            ]);
        }
    }
};
