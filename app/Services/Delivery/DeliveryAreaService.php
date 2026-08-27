<?php

namespace App\Services\Delivery;

use App\Models\DeliveryArea;
use App\Models\SalesOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DeliveryAreaService
{
    /**
     * Most specific area for this order's shipping snapshot wins.
     * Inactive area → not deliverable. No areas configured → no restriction.
     *
     * @return array{ok: bool, code: string, message: ?string, area: ?DeliveryArea}
     */
    public function evaluate(SalesOrder $order, int $companyId): array
    {
        $areas = DeliveryArea::query()->where('company_id', $companyId)->get();
        if ($areas->isEmpty()) {
            return ['ok' => true, 'code' => 'open', 'message' => null, 'area' => null];
        }

        $place = $this->shippingPlace($order);
        $match = $this->bestMatch($order, $areas);
        if (! $match) {
            $where = trim(collect([$place['city'], $place['state'], $place['zip']])->filter()->implode(', '));
            if ($where === '') {
                $where = trim((string) $order->ship_to_address);
            }

            return [
                'ok' => false,
                'code' => 'outside',
                'message' => 'This address is outside the current delivery area'.($where !== '' ? ' ('.$where.')' : '').'.',
                'area' => null,
            ];
        }

        if (! $match->is_active) {
            return [
                'ok' => false,
                'code' => 'inactive',
                'message' => 'Delivery area is not active: '.$match->label().'.',
                'area' => $match,
            ];
        }

        return ['ok' => true, 'code' => 'ok', 'message' => null, 'area' => $match];
    }

    public function isDeliverable(SalesOrder $order, int $companyId): bool
    {
        return $this->evaluate($order, $companyId)['ok'];
    }

    /**
     * ZIP (3) beats city (2) beats statewide (1). Same score: inactive wins so a turned-off row blocks delivery.
     *
     * @param  \Illuminate\Support\Collection<int, DeliveryArea>  $areas
     */
    protected function bestMatch(SalesOrder $order, $areas): ?DeliveryArea
    {
        $place = $this->shippingPlace($order);
        $state = strtoupper($place['state']);
        $city = strtoupper($place['city']);
        $zip = $place['zip'];

        $best = null;
        $bestScore = 0;

        foreach ($areas as $area) {
            $areaState = strtoupper(trim((string) $area->state_code));
            $areaCity = strtoupper(trim((string) $area->city));
            $areaZip = substr((string) preg_replace('/\D+/', '', (string) $area->zip_code), 0, 5);

            if ($areaState !== '' && $state !== '' && $areaState !== $state) {
                continue;
            }

            $score = 0;
            if ($areaZip !== '' && $zip !== '' && $areaZip === $zip) {
                $score = 3;
            } elseif ($areaZip === '' && $areaCity !== '' && $city !== '' && $areaCity === $city) {
                $score = 2;
            } elseif ($areaZip === '' && $areaCity === '' && $areaState !== '' && $areaState === $state) {
                $score = 1;
            }

            if ($score === 0) {
                continue;
            }

            if ($score > $bestScore) {
                $best = $area;
                $bestScore = $score;

                continue;
            }

            if ($score === $bestScore && $best && ! $area->is_active && $best->is_active) {
                $best = $area;
            }
        }

        return $best;
    }

    /**
     * City / state / ZIP from order columns, or parsed from a one-line street address
     * like "3650 SOUTH ST. STREET, ANN ARBOR MI 48108".
     *
     * @return array{city: string, state: string, zip: string}
     */
    public function shippingPlace(SalesOrder $order): array
    {
        $city = trim((string) $order->ship_to_city);
        $state = trim((string) $order->ship_to_state);
        $zip = substr((string) preg_replace('/\D+/', '', (string) $order->ship_to_zip), 0, 5);

        $blob = trim(implode(' ', array_filter([
            (string) $order->ship_to_address,
            $city,
            $state,
            (string) $order->ship_to_zip,
        ], fn ($v) => trim((string) $v) !== '')));

        if (($city === '' || $state === '' || $zip === '') && $blob !== '') {
            if (preg_match('/,\s*([^,]+?)\s+([A-Za-z]{2})\s+(\d{5})(?:-\d{4})?\s*$/', $blob, $m)) {
                $city = $city !== '' ? $city : trim($m[1]);
                $state = $state !== '' ? $state : strtoupper(trim($m[2]));
                $zip = $zip !== '' ? $zip : $m[3];
            } elseif (preg_match('/\b([A-Za-z]{2})\s+(\d{5})(?:-\d{4})?\s*$/', $blob, $m)) {
                $state = $state !== '' ? $state : strtoupper($m[1]);
                $zip = $zip !== '' ? $zip : $m[2];
                if ($city === '' && preg_match('/,\s*([^,]+)\s+'.preg_quote($m[1], '/').'\s+'.$m[2].'\s*$/i', $blob, $cm)) {
                    $city = trim($cm[1]);
                }
            } elseif (preg_match('/\b(\d{5})(?:-\d{4})?\s*$/', $blob, $m)) {
                $zip = $zip !== '' ? $zip : $m[1];
            }
        }

        return ['city' => $city, 'state' => $state, 'zip' => $zip];
    }

    /**
     * Create or reactivate the area for this ship-to (city + ZIP + state).
     */
    public function saveFromOrder(SalesOrder $order, int $companyId): DeliveryArea
    {
        $place = $this->shippingPlace($order);
        $city = $place['city'];
        $zip = $place['zip'];
        $rawState = $place['state'];
        if ($city === '' && $zip === '' && $rawState === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'orders' => 'Cannot save a delivery area: this invoice has no city, state, or ZIP.',
            ]);
        }

        $dirty = false;
        if (trim((string) $order->ship_to_city) === '' && $city !== '') {
            $order->ship_to_city = $city;
            $dirty = true;
        }
        if (trim((string) $order->ship_to_state) === '' && $rawState !== '') {
            $order->ship_to_state = $rawState;
            $dirty = true;
        }
        if (trim((string) $order->ship_to_zip) === '' && $zip !== '') {
            $order->ship_to_zip = $zip;
            $dirty = true;
        }
        if ($dirty) {
            $order->save();
        }

        $code = strtoupper($rawState);
        $name = $rawState !== '' ? $rawState : $code;
        if (strlen($code) !== 2) {
            $known = DeliveryArea::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($rawState) {
                    $q->where('state', $rawState)->orWhere('state_code', strtoupper($rawState));
                })
                ->first();
            if ($known) {
                $code = strtoupper((string) $known->state_code);
                $name = (string) $known->state;
            } else {
                $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $rawState));
                $code = substr($letters !== '' ? $letters : 'XX', 0, 2);
            }
        } else {
            $knownName = DeliveryArea::query()
                ->where('company_id', $companyId)
                ->where('state_code', $code)
                ->value('state');
            if ($knownName) {
                $name = (string) $knownName;
            }
        }

        return $this->savePlace($companyId, $name, $code, $city, $zip);
    }

    public function savePlace(int $companyId, string $state, string $stateCode, string $city, string $zip): DeliveryArea
    {
        $stateCode = strtoupper(trim($stateCode));
        $city = trim($city);
        $zip = trim($zip);
        $area = DeliveryArea::query()->firstOrNew([
            'company_id' => $companyId,
            'state_code' => $stateCode,
            'city' => $city,
            'zip_code' => $zip,
        ]);
        $area->state = $state !== '' ? $state : $stateCode;
        if (! $area->country) {
            $area->country = 'USA';
        }
        $area->is_active = true;
        $area->save();

        return $area;
    }

    /**
     * @return array{total: int, imported: int, skipped: int, duplicate: int, invalid: int}
     */
    public function importCsv(int $companyId, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['total' => 0, 'imported' => 0, 'skipped' => 0, 'duplicate' => 0, 'invalid' => 0];
        }

        $header = fgetcsv($handle);
        $map = [];
        foreach ((array) $header as $i => $col) {
            $map[strtolower(trim((string) $col))] = $i;
        }

        $stats = ['total' => 0, 'imported' => 0, 'skipped' => 0, 'duplicate' => 0, 'invalid' => 0];
        $chunk = [];

        while (($row = fgetcsv($handle)) !== false) {
            $stats['total']++;
            $state = $this->csvCell($row, $map, 'state');
            $code = strtoupper($this->csvCell($row, $map, 'state_code'));
            $city = $this->csvCell($row, $map, 'city');
            $zip = $this->csvCell($row, $map, 'zip_code');
            if ($state === '' || $code === '') {
                $stats['invalid']++;
                continue;
            }

            $latRaw = $this->csvCell($row, $map, 'latitude');
            $lngRaw = $this->csvCell($row, $map, 'longitude');

            $chunk[] = [
                'company_id' => $companyId,
                'state' => mb_substr($state, 0, 80),
                'state_code' => mb_substr($code, 0, 8),
                'city' => mb_substr($city, 0, 80),
                'zip_code' => mb_substr($zip, 0, 16),
                'country' => mb_substr($this->csvCell($row, $map, 'country') ?: 'USA', 0, 80),
                'county' => mb_substr($this->csvCell($row, $map, 'county'), 0, 80) ?: null,
                'latitude' => is_numeric($latRaw) ? (float) $latRaw : null,
                'longitude' => is_numeric($lngRaw) ? (float) $lngRaw : null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($chunk) >= 500) {
                $this->flushChunk($chunk, $stats);
                $chunk = [];
            }
        }
        fclose($handle);

        if ($chunk !== []) {
            $this->flushChunk($chunk, $stats);
        }

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     * @param  array{total: int, imported: int, skipped: int, duplicate: int, invalid: int}  $stats
     */
    protected function flushChunk(array $chunk, array &$stats): void
    {
        $keys = collect($chunk)->map(fn ($r) => $r['company_id'].'|'.$r['state_code'].'|'.strtoupper($r['city']).'|'.$r['zip_code']);
        $existing = DeliveryArea::query()
            ->where('company_id', $chunk[0]['company_id'])
            ->whereIn('state_code', collect($chunk)->pluck('state_code')->unique()->all())
            ->get()
            ->keyBy(fn (DeliveryArea $a) => $a->company_id.'|'.strtoupper((string) $a->state_code).'|'.strtoupper((string) $a->city).'|'.(string) $a->zip_code);

        $insert = [];
        foreach ($chunk as $i => $row) {
            $key = $keys[$i];
            if ($existing->has($key)) {
                $stats['duplicate']++;
                $stats['skipped']++;
                continue;
            }
            $insert[] = $row;
            $existing[$key] = true;
        }

        if ($insert !== []) {
            DB::table('delivery_areas')->insert($insert);
            $stats['imported'] += count($insert);
        }
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     */
    protected function csvCell(array $row, array $map, string $key): string
    {
        if (! isset($map[$key])) {
            return '';
        }

        return trim((string) ($row[$map[$key]] ?? ''));
    }
}
