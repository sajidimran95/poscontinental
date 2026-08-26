<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class SalePwaController extends Controller
{
    protected function context(): array
    {
        $company = Company::query()->orderBy('id')->first();
        $name = trim(($company->name ?? config('app.name', 'Sales')).' Sales');

        return [
            'app_name' => $name !== '' ? $name : 'Sales',
            'short_name' => mb_substr($name !== '' ? $name : 'Sales', 0, 12),
            'theme_color' => '#0f766e',
            'background_color' => '#0b1220',
        ];
    }

    public function manifest()
    {
        $ctx = $this->context();
        $icon192 = file_exists(public_path('pwa/sale-icon-192.png'))
            ? asset('pwa/sale-icon-192.png')
            : asset('favicon.ico');
        $icon512 = file_exists(public_path('pwa/sale-icon-512.png'))
            ? asset('pwa/sale-icon-512.png')
            : $icon192;

        $manifest = [
            'id' => url('/sale').'#sale-pwa',
            'name' => $ctx['app_name'],
            'short_name' => $ctx['short_name'],
            'description' => 'Sales representative app',
            'start_url' => url('/sale/login').'?source=pwa',
            'scope' => url('/sale').'/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => $ctx['background_color'],
            'theme_color' => $ctx['theme_color'],
            'categories' => ['business', 'productivity'],
            'icons' => [
                ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    public function serviceWorker()
    {
        $version = config('app.asset_version', '1').'-sale-1';
        $offline = url('/sale/pwa/offline');
        $cache = 'japspos-sale-pwa-v'.preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $version);

        $shell = json_encode([
            url('/sale'),
            url('/sale/orders'),
            url('/sale/orders/create'),
            $offline,
            asset('pwa/sale-icon-192.png'),
            asset('pwa/sale-icon-512.png'),
        ]);

        $js = <<<JS
const CACHE_NAME = '{$cache}';
const OFFLINE_URL = '{$offline}';
const SHELL = {$shell};

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith('japspos-sale-pwa-') && k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (!url.pathname.startsWith('/sale')) return;

  const isDoc = req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html');
  if (isDoc) {
    event.respondWith(
      fetch(req).then((res) => {
        if (!res.ok || res.redirected || res.type === 'opaqueredirect' || (res.status >= 300 && res.status < 400)) {
          return res;
        }
        const copy = res.clone();
        caches.open(CACHE_NAME).then((c) => c.put(req, copy)).catch(() => {});
        return res;
      }).catch(() => caches.match(req).then((r) => r || caches.match(OFFLINE_URL)))
    );
  }
});
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/sale/',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    public function offline()
    {
        return response()->view('sale.pwa_offline', $this->context())->header('Cache-Control', 'no-cache');
    }
}
