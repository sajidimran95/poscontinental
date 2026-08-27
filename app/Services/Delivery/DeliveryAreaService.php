<?php

namespace App\Services\Delivery;

use App\Models\DeliveryArea;
use App\Models\SalesOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DeliveryAreaService
{
    /**
     * Match order shipping snapshot (not the customer's current address).
     */
    public function isDeliverable(SalesOrder $order, int $companyId): bool
    {
        $areas = DeliveryArea::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        if ($areas->isEmpty()) {
            return true;
        }

        $state = strtoupper(trim((string) $order->ship_to_state));
        $city = strtoupper(trim((string) $order->ship_to_city));
        $zip = preg_replace('/\D+/', '', (string) $order->ship_to_zip);
        $zip = substr((string) $zip, 0, 5);

        $hasDetail = $areas->contains(function (DeliveryArea $area) use ($state) {
            $sameState = strtoupper(trim((string) $area->state_code)) === $state || $state === '';
            $hasCity = trim((string) $area->city) !== '';
            $hasZip = trim((string) $area->zip_code) !== '';

            return $sameState && ($hasCity || $hasZip);
        });

        foreach ($areas as $area) {
            $areaState = strtoupper(trim((string) $area->state_code));
            $areaCity = strtoupper(trim((string) $area->city));
            $areaZip = preg_replace('/\D+/', '', (string) $area->zip_code);
            $areaZip = substr((string) $areaZip, 0, 5);

            if ($areaState !== '' && $state !== '' && $areaState !== $state) {
                continue;
            }

            if ($areaZip !== '' && $zip !== '' && $areaZip === $zip) {
                return true;
            }

            if ($areaZip === '' && $areaCity !== '' && $city !== '' && $areaCity === $city) {
                return true;
            }

            if (! $hasDetail && $areaZip === '' && $areaCity === '' && $areaState !== '' && $areaState === $state) {
                return true;
            }
        }

        return false;
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
            $state = trim((string) ($row[$map['state'] ?? -1] ?? ''));
            $code = strtoupper(trim((string) ($row[$map['state_code'] ?? -1] ?? '')));
            $city = trim((string) ($row[$map['city'] ?? -1] ?? ''));
            $zip = trim((string) ($row[$map['zip_code'] ?? -1] ?? ''));
            if ($state === '' || $code === '') {
                $stats['invalid']++;
                continue;
            }

            $chunk[] = [
                'company_id' => $companyId,
                'state' => mb_substr($state, 0, 80),
                'state_code' => mb_substr($code, 0, 8),
                'city' => mb_substr($city, 0, 80),
                'zip_code' => mb_substr($zip, 0, 16),
                'country' => mb_substr(trim((string) ($row[$map['country'] ?? -1] ?? 'USA')) ?: 'USA', 0, 80),
                'county' => mb_substr(trim((string) ($row[$map['county'] ?? -1] ?? '')), 0, 80) ?: null,
                'latitude' => is_numeric($row[$map['latitude'] ?? -1] ?? null) ? (float) $row[$map['latitude']] : null,
                'longitude' => is_numeric($row[$map['longitude'] ?? -1] ?? null) ? (float) $row[$map['longitude']] : null,
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
}
