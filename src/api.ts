// PWA runs on mine.blockrium.com while the existing Laravel API remains on cPanel.
const API_PREFIX = import.meta.env.VITE_API_PREFIX || 'https://maticlegend.com/api/';
const TOKEN_KEY = 'blockrium.web.session.v1';

export type ApiResult<T = any> = { success: boolean; data?: T; error?: string; status?: number };

export const session = {
  token: () => sessionStorage.getItem(TOKEN_KEY) || '',
  setToken: (value: string) => value ? sessionStorage.setItem(TOKEN_KEY, value) : sessionStorage.removeItem(TOKEN_KEY),
  clear: () => sessionStorage.removeItem(TOKEN_KEY),
};

function tokenFrom(value: any): string {
  const root = value?.data ?? value ?? {};
  const data = root?.data ?? {};
  const token = root?.token?.original ?? root?.token ?? data?.token?.original ?? data?.token ?? {};
  return token?.access_token || token?.token || root?.access_token || data?.access_token || (typeof root?.token === 'string' ? root.token : '');
}

export async function api<T = any>(path: string, options: RequestInit = {}): Promise<ApiResult<T>> {
  const headers = new Headers(options.headers);
  headers.set('Accept', 'application/json');
  if (!(options.body instanceof FormData) && options.body) headers.set('Content-Type', 'application/json');
  const token = session.token();
  if (token) headers.set('Authorization', `Bearer ${token}`);
  try {
    const response = await fetch(`${API_PREFIX}${path.replace(/^\//, '')}`, { ...options, headers });
    const text = await response.text();
    let data: any = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { message: text }; }
    if (!response.ok) {
      const errors = data?.errors ? Object.values(data.errors).flat().join(' ') : '';
      return { success: false, error: errors || data?.message || data?.error || `Request failed (${response.status}).`, status: response.status, data };
    }
    return { success: data?.success !== false && data?.status !== 0, data: data as T, status: response.status, error: data?.message || data?.error };
  } catch (error) {
    return { success: false, error: error instanceof Error ? error.message : 'Network error. Check your connection.' };
  }
}

export const post = <T = any>(path: string, payload: unknown) => api<T>(path, { method: 'POST', body: JSON.stringify(payload) });
export const get = <T = any>(path: string) => api<T>(path);

export function readData(value: any): any {
  return value?.data?.data ?? value?.data ?? value ?? {};
}

export function acceptAuthResponse(value: any): boolean {
  const token = tokenFrom(value);
  if (!token) return false;
  session.setToken(token);
  return true;
}
