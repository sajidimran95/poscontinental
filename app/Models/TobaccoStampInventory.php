<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TobaccoStampInventory extends Model
{
    public const STAMP_TYPES = ['r1', 'r2', 'r3', 'r4', 'r5', 'r6'];

    protected $fillable = [
        'company_id', 'period_start', 'period_end',
        'r1_beginning_unaffixed', 'r2_beginning_affixed', 'r3_purchased',
        'r4_affixed', 'r5_ending_unaffixed', 'r6_ending_affixed',
        'beginning_unaffixed_r1', 'beginning_unaffixed_r2', 'beginning_unaffixed_r3',
        'beginning_unaffixed_r4', 'beginning_unaffixed_r5', 'beginning_unaffixed_r6',
        'ending_unaffixed_r1', 'ending_unaffixed_r2', 'ending_unaffixed_r3',
        'ending_unaffixed_r4', 'ending_unaffixed_r5', 'ending_unaffixed_r6',
        'beginning_affixed_r1', 'beginning_affixed_r2', 'beginning_affixed_r3',
        'beginning_affixed_r4', 'beginning_affixed_r5', 'beginning_affixed_r6',
        'ending_affixed_r1', 'ending_affixed_r2', 'ending_affixed_r3',
        'ending_affixed_r4', 'ending_affixed_r5', 'ending_affixed_r6',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        $ints = [];
        foreach (self::STAMP_TYPES as $r) {
            $ints["beginning_unaffixed_{$r}"] = 'integer';
            $ints["ending_unaffixed_{$r}"] = 'integer';
            $ints["beginning_affixed_{$r}"] = 'integer';
            $ints["ending_affixed_{$r}"] = 'integer';
        }

        return array_merge([
            'period_start' => 'date',
            'period_end' => 'date',
            'r1_beginning_unaffixed' => 'decimal:2',
            'r2_beginning_affixed' => 'decimal:2',
            'r3_purchased' => 'decimal:2',
            'r4_affixed' => 'decimal:2',
            'r5_ending_unaffixed' => 'decimal:2',
            'r6_ending_affixed' => 'decimal:2',
        ], $ints);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return array{beginning_unaffixed: array<string,int>, ending_unaffixed: array<string,int>, beginning_affixed: array<string,int>, ending_affixed: array<string,int>} */
    public function matrix(): array
    {
        $out = [
            'beginning_unaffixed' => [],
            'ending_unaffixed' => [],
            'beginning_affixed' => [],
            'ending_affixed' => [],
        ];

        foreach (self::STAMP_TYPES as $i => $r) {
            $key = 'R'.($i + 1);
            $out['beginning_unaffixed'][$key] = (int) ($this->{"beginning_unaffixed_{$r}"} ?? 0);
            $out['ending_unaffixed'][$key] = (int) ($this->{"ending_unaffixed_{$r}"} ?? 0);
            $out['beginning_affixed'][$key] = (int) ($this->{"beginning_affixed_{$r}"} ?? 0);
            $out['ending_affixed'][$key] = (int) ($this->{"ending_affixed_{$r}"} ?? 0);
        }

        // Legacy fallback if matrix never filled but old totals exist.
        if (array_sum($out['beginning_unaffixed']) === 0 && (float) $this->r1_beginning_unaffixed > 0) {
            $out['beginning_unaffixed']['R1'] = (int) round((float) $this->r1_beginning_unaffixed);
        }
        if (array_sum($out['beginning_affixed']) === 0 && (float) $this->r2_beginning_affixed > 0) {
            $out['beginning_affixed']['R1'] = (int) round((float) $this->r2_beginning_affixed);
        }
        if (array_sum($out['ending_unaffixed']) === 0 && (float) $this->r5_ending_unaffixed > 0) {
            $out['ending_unaffixed']['R1'] = (int) round((float) $this->r5_ending_unaffixed);
        }
        if (array_sum($out['ending_affixed']) === 0 && (float) $this->r6_ending_affixed > 0) {
            $out['ending_affixed']['R1'] = (int) round((float) $this->r6_ending_affixed);
        }

        return $out;
    }
}
