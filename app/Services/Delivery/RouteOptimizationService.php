<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteOptimizationService
{
    /**
     * @param  array{lat: float, lng: float}  $origin
     * @param  array<int|string, array{lat: float, lng: float}>  $stops  keyed by stop id
     * @return array{order: list<int|string>, distances: array<int|string, int>, durations: array<int|string, int>, total_distance: int, total_duration: int}
     */
    public function optimize(array $origin, array $stops): array
    {
        $ids = array_keys($stops);
        if ($ids === []) {
            return ['order' => [], 'distances' => [], 'durations' => [], 'total_distance' => 0, 'total_duration' => 0];
        }

        $points = [$origin, ...array_values($stops)];
        $matrix = $this->durationMatrix($points) ?? $this->haversineMatrix($points);
        $orderIdx = $this->nearestNeighbor($matrix);
        $orderIdx = $this->twoOpt($matrix, $orderIdx);

        $order = [];
        $distances = [];
        $durations = [];
        $totalDist = 0;
        $totalDur = 0;
        $prev = 0;
        foreach ($orderIdx as $idx) {
            $id = $ids[$idx - 1];
            $order[] = $id;
            $dur = (int) round($matrix[$prev][$idx] ?? 0);
            $dist = $this->metersBetween($points[$prev], $points[$idx]);
            $durations[$id] = $dur;
            $distances[$id] = $dist;
            $totalDur += $dur;
            $totalDist += $dist;
            $prev = $idx;
        }

        return [
            'order' => $order,
            'distances' => $distances,
            'durations' => $durations,
            'total_distance' => $totalDist,
            'total_duration' => $totalDur,
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>|null seconds
     */
    protected function durationMatrix(array $points): ?array
    {
        $provider = (string) config('delivery.provider', 'osrm');

        try {
            return match ($provider) {
                'google' => $this->googleMatrix($points),
                'mapbox' => $this->mapboxMatrix($points),
                'openrouteservice' => $this->orsMatrix($points),
                'haversine' => $this->haversineMatrix($points),
                default => $this->osrmMatrix($points),
            };
        } catch (\Throwable $e) {
            Log::warning('Delivery routing provider failed; using road-distance fallback.', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->haversineMatrix($points);
        }
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>
     */
    protected function osrmMatrix(array $points): array
    {
        $coords = collect($points)
            ->map(fn ($p) => number_format((float) $p['lng'], 6, '.', '').','.number_format((float) $p['lat'], 6, '.', ''))
            ->implode(';');

        $base = rtrim((string) config('delivery.osrm.base_url'), '/');
        $response = Http::timeout(20)
            ->acceptJson()
            ->get($base.'/table/v1/driving/'.$coords, [
                'annotations' => 'duration,distance',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OSRM table request failed: '.$response->status());
        }

        $durations = $response->json('durations');
        if (! is_array($durations) || $durations === []) {
            throw new \RuntimeException('OSRM returned no durations.');
        }

        return $durations;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>
     */
    protected function googleMatrix(array $points): array
    {
        $key = (string) config('delivery.google.key');
        if ($key === '') {
            throw new \RuntimeException('GOOGLE_MAPS_API_KEY is not set.');
        }

        $fmt = fn ($p) => $p['lat'].','.$p['lng'];
        $origins = implode('|', array_map($fmt, $points));
        $response = Http::timeout(20)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => $origins,
            'destinations' => $origins,
            'key' => $key,
            'units' => 'imperial',
        ]);

        $rows = $response->json('rows');
        if (! is_array($rows)) {
            throw new \RuntimeException('Google Distance Matrix failed.');
        }

        $matrix = [];
        foreach ($rows as $r => $row) {
            $matrix[$r] = [];
            foreach (($row['elements'] ?? []) as $c => $el) {
                $matrix[$r][$c] = (float) ($el['duration']['value'] ?? 0);
            }
        }

        return $matrix;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>
     */
    protected function mapboxMatrix(array $points): array
    {
        $token = (string) config('delivery.mapbox.token');
        if ($token === '') {
            throw new \RuntimeException('MAPBOX_ACCESS_TOKEN is not set.');
        }

        $coords = collect($points)
            ->map(fn ($p) => $p['lng'].','.$p['lat'])
            ->implode(';');

        $response = Http::timeout(20)->get('https://api.mapbox.com/directions-matrix/v1/mapbox/driving/'.$coords, [
            'access_token' => $token,
            'annotations' => 'duration,distance',
        ]);

        $durations = $response->json('durations');
        if (! is_array($durations)) {
            throw new \RuntimeException('Mapbox matrix failed.');
        }

        return $durations;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>
     */
    protected function orsMatrix(array $points): array
    {
        $key = (string) config('delivery.openrouteservice.key');
        if ($key === '') {
            throw new \RuntimeException('OPENROUTESERVICE_API_KEY is not set.');
        }

        $locations = array_map(fn ($p) => [(float) $p['lng'], (float) $p['lat']], $points);
        $base = rtrim((string) config('delivery.openrouteservice.base_url'), '/');
        $response = Http::timeout(20)
            ->withHeaders(['Authorization' => $key])
            ->post($base.'/v2/matrix/driving-car', [
                'locations' => $locations,
                'metrics' => ['duration', 'distance'],
            ]);

        $durations = $response->json('durations');
        if (! is_array($durations)) {
            throw new \RuntimeException('OpenRouteService matrix failed.');
        }

        return $durations;
    }

    /**
     * Approximate driving time from great-circle distance (not used as the final order when a road API works).
     *
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<list<float>>
     */
    protected function haversineMatrix(array $points): array
    {
        $n = count($points);
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $meters = $this->metersBetween($points[$i], $points[$j]);
                $matrix[$i][$j] = $meters / 12.0;
            }
        }

        return $matrix;
    }

    /**
     * @param  list<list<float>>  $matrix
     * @return list<int> indices into $points excluding origin (0)
     */
    protected function nearestNeighbor(array $matrix): array
    {
        $n = count($matrix);
        $unvisited = range(1, $n - 1);
        $order = [];
        $current = 0;
        while ($unvisited !== []) {
            $best = null;
            $bestCost = PHP_FLOAT_MAX;
            foreach ($unvisited as $idx) {
                $cost = (float) ($matrix[$current][$idx] ?? PHP_FLOAT_MAX);
                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $best = $idx;
                }
            }
            $order[] = $best;
            $unvisited = array_values(array_filter($unvisited, fn ($i) => $i !== $best));
            $current = $best;
        }

        return $order;
    }

    /**
     * @param  list<list<float>>  $matrix
     * @param  list<int>  $order
     * @return list<int>
     */
    protected function twoOpt(array $matrix, array $order): array
    {
        $n = count($order);
        if ($n < 4) {
            return $order;
        }

        $improved = true;
        $guard = 0;
        while ($improved && $guard < 40) {
            $improved = false;
            $guard++;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    $candidate = $order;
                    $slice = array_reverse(array_slice($candidate, $i, $k - $i + 1));
                    array_splice($candidate, $i, $k - $i + 1, $slice);
                    if ($this->pathCost($matrix, $candidate) + 0.01 < $this->pathCost($matrix, $order)) {
                        $order = $candidate;
                        $improved = true;
                    }
                }
            }
        }

        return $order;
    }

    /**
     * @param  list<list<float>>  $matrix
     * @param  list<int>  $order
     */
    protected function pathCost(array $matrix, array $order): float
    {
        $cost = 0.0;
        $prev = 0;
        foreach ($order as $idx) {
            $cost += (float) ($matrix[$prev][$idx] ?? 0);
            $prev = $idx;
        }

        return $cost;
    }

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    public function metersBetween(array $a, array $b): int
    {
        $lat1 = deg2rad((float) $a['lat']);
        $lat2 = deg2rad((float) $b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b['lng'] - (float) $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $km = 2 * 6371 * asin(min(1, sqrt($h)));

        return (int) round($km * 1000);
    }

    public function geocode(string $query): ?array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        if ($query === '') {
            return null;
        }

        $cacheKey = 'delivery_geo_'.md5(mb_strtolower($query));
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        $variants = $this->geocodeQueryVariants($query);
        foreach ($variants as $variant) {
            $hit = $this->geocodeGoogle($variant)
                ?? $this->geocodeNominatim($variant)
                ?? $this->geocodePhoton($variant);
            if ($hit) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $hit, now()->addDays(30));

                return $hit;
            }
        }

        Log::warning('Delivery geocode failed for all providers.', ['query' => $query]);

        return null;
    }

    /** @return list<string> */
    protected function geocodeQueryVariants(string $query): array
    {
        $variants = [$query];
        if (preg_match('/\b(\d{5})(?:-\d{4})?\b/', $query, $m)) {
            $zip = $m[1];
            if (preg_match('/\b([A-Z]{2})\b/', strtoupper($query), $st)) {
                $city = '';
                if (preg_match('/,\s*([^,]+?)\s+[A-Z]{2}\s+\d{5}/i', $query, $cm)) {
                    $city = trim($cm[1]);
                }
                $variants[] = trim($city.' '.$st[1].' '.$zip.', USA');
                $variants[] = $st[1].' '.$zip.', USA';
            }
            $variants[] = $zip.', USA';
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function geocodeGoogle(string $query): ?array
    {
        $googleKey = (string) config('delivery.google.key');
        if ($googleKey === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $query,
                'key' => $googleKey,
            ]);
            $loc = $response->json('results.0.geometry.location');
            if (is_array($loc) && isset($loc['lat'], $loc['lng'])) {
                return ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng']];
            }
        } catch (\Throwable $e) {
            Log::notice('Google geocode failed.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function geocodeNominatim(string $query): ?array
    {
        $base = rtrim((string) config('delivery.nominatim.base_url'), '/');
        $ua = 'JAPS-POS-Delivery/1.0 ('.rtrim((string) config('app.url'), '/').'; '.((string) config('mail.from.address') ?: 'support@localhost').')';

        try {
            $response = Http::timeout(12)
                ->withUserAgent($ua)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Referer' => (string) config('app.url'),
                ])
                ->get($base.'/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);

            if (! $response->successful()) {
                Log::notice('Nominatim geocode HTTP '.$response->status(), ['query' => $query]);

                return null;
            }

            $first = $response->json('0');
            if (is_array($first) && isset($first['lat'], $first['lon'])) {
                return ['lat' => (float) $first['lat'], 'lng' => (float) $first['lon']];
            }
        } catch (\Throwable $e) {
            Log::notice('Nominatim geocode failed.', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function geocodePhoton(string $query): ?array
    {
        try {
            $response = Http::timeout(12)
                ->withUserAgent('JAPS-POS-Delivery/1.0')
                ->acceptJson()
                ->get('https://photon.komoot.io/api/', [
                    'q' => $query,
                    'limit' => 1,
                    'lang' => 'en',
                ]);
            $coords = $response->json('features.0.geometry.coordinates');
            if (is_array($coords) && isset($coords[0], $coords[1])) {
                return ['lat' => (float) $coords[1], 'lng' => (float) $coords[0]];
            }
        } catch (\Throwable $e) {
            Log::notice('Photon geocode failed.', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
