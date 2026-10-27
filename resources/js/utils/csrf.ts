import { usePage } from '@inertiajs/vue3';

/**
 * Mengambil cookie berdasarkan nama
 */
export function getCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
  return match ? decodeURIComponent(match[3]) : null;
}

/**
 * Memperbarui tag meta csrf-token di dokumen agar selalu sinkron dengan sesi aktif
 */
export function updateMetaCsrfToken(token: string): void {
  if (!token || typeof document === 'undefined') return;
  let meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
  if (!meta) {
    meta = document.createElement('meta');
    meta.setAttribute('name', 'csrf-token');
    document.head.appendChild(meta);
  }
  meta.setAttribute('content', token);
}

/**
 * Mengambil CSRF token yang valid dan selalu ter-refresh dari berbagai sumber:
 * 1. Inertia usePage().props.csrf_token (terbaru per navigasi SPA)
 * 2. Meta tag <meta name="csrf-token">
 */
export function getCsrfToken(): string {
  // 1. Coba ambil dari Inertia page props (paling mutakhir pada navigasi SPA)
  try {
    const page = usePage();
    const token = page?.props?.csrf_token as string | undefined;
    if (token) {
      updateMetaCsrfToken(token);
      return token;
    }
  } catch {
    // Di luar konteks Vue setup
  }

  // 2. Coba ambil dari meta tag di <head>
  if (typeof document !== 'undefined') {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    if (meta?.content) {
      return meta.content;
    }
  }

  return '';
}

/**
 * Mengambil header standar untuk HTTP Fetch yang aman terhadap CSRF
 */
export function getCsrfHeaders(customHeaders: Record<string, string> = {}): Record<string, string> {
  const token = getCsrfToken();
  const xsrfCookie = getCookie('XSRF-TOKEN');

  const headers: Record<string, string> = {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    ...customHeaders,
  };

  if (token) {
    headers['X-CSRF-TOKEN'] = token;
  }

  if (xsrfCookie) {
    headers['X-XSRF-TOKEN'] = xsrfCookie;
  }

  return headers;
}

/**
 * Wrapper fetch cerdas yang otomatis menyertakan CSRF header
 * dan mampu melakukan auto-retry 1x secara transparan jika terjadi 419 (CSRF token mismatch)
 */
export async function secureFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const initialHeaders = getCsrfHeaders((options.headers as Record<string, string>) || {});

  let response = await fetch(url, {
    ...options,
    headers: initialHeaders,
  });

  // Jika terkena 419 (CSRF token mismatch atau kedaluwarsa), ambil token baru dari server dan ulangi request
  if (response.status === 419) {
    try {
      const refreshRes = await fetch('/csrf-token', {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (refreshRes.ok) {
        const refreshData = await refreshRes.json();
        if (refreshData?.csrf_token) {
          updateMetaCsrfToken(refreshData.csrf_token);

          // Susun ulang header dengan token baru
          const retryHeaders = getCsrfHeaders((options.headers as Record<string, string>) || {});
          retryHeaders['X-CSRF-TOKEN'] = refreshData.csrf_token;

          response = await fetch(url, {
            ...options,
            headers: retryHeaders,
          });
        }
      }
    } catch (err) {
      console.warn('Gagal me-refresh CSRF token secara otomatis:', err);
    }
  }

  return response;
}
