/**
 * Customer app scaffold — wire to Sanctum API (see ../../docs/MOBILE_APPS.md).
 * Replace with full Expo/React Native or Flutter project for production builds.
 */
const API_BASE = process.env.EXPO_PUBLIC_API_URL || 'https://pos.local/api';

export async function login(email, password) {
  const res = await fetch(`${API_BASE}/login`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, device_name: 'customer-app' }),
  });
  if (!res.ok) throw new Error('Login failed');
  return res.json();
}

export async function fetchCatalog(token, search = '') {
  const url = new URL(`${API_BASE}/items`);
  if (search) url.searchParams.set('search', search);
  const res = await fetch(url, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
  return res.json();
}

export async function createOrder(token, customerId, lines) {
  const res = await fetch(`${API_BASE}/sales-orders`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ customer_id: customerId, lines }),
  });
  if (!res.ok) throw new Error('Order create failed');
  return res.json();
}

console.log('Customer app API helpers ready. Point EXPO_PUBLIC_API_URL at on-prem HTTPS.');
