<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id', 'payment_date', 'payment_method', 'check_number', 'amount', 'comments', 'user_id',
    ];

    public const RETURNED_CHECK_METHOD = 'Returned Check';

    public static function isCheckMethod(?string $method): bool
    {
        return strcasecmp(trim((string) $method), 'Check') === 0;
    }

    public static function isReturnedCheckMethod(?string $method): bool
    {
        return strcasecmp(trim((string) $method), self::RETURNED_CHECK_METHOD) === 0;
    }

    /** Customer fee charged on a returned (bounced) check, booked as invoice Miscellaneous. */
    public const RETURNED_CHECK_FEE = 25.00;

    public static function returnedCheckMarker(int $paymentId): string
    {
        return 'Returned check for payment #'.$paymentId;
    }

    public static function returnedCheckComment(int $paymentId, float $fee): string
    {
        $text = self::returnedCheckMarker($paymentId);

        return $fee > 0.0001 ? $text.' · Fee $'.number_format($fee, 2, '.', '') : $text;
    }

    public static function returnedCheckFee(?string $comments): float
    {
        return preg_match('/Fee \$([0-9]+(?:\.[0-9]+)?)/', (string) $comments, $m) ? (float) $m[1] : 0.0;
    }

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
