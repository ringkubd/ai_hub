export function csrfHeader(): Record<string, string> {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token ? { 'X-CSRF-TOKEN': token } : {};
}

export function defaultHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...csrfHeader(),
    };
}

export function apiFetch(input: RequestInfo, init: RequestInit = {}) {
    return fetch(input, { credentials: 'same-origin', ...init });
}

export async function fetchJson<T>(input: RequestInfo, init: RequestInit = {}) {
    const response = await apiFetch(input, { ...init, headers: { Accept: 'application/json', ...(init.headers || {}) } });
    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }
    return response.json() as Promise<T>;
}
