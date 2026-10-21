import {
    OFFLINE_SUBMISSION_MESSAGE,
    serverFailureMessage,
    UNEXPECTED_RESPONSE_MESSAGE,
} from '@/lib/submission-failure';

export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const token = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify(body),
    }).catch(() => {
        throw new Error(OFFLINE_SUBMISSION_MESSAGE);
    });
    // An expired session or a proxy error answers HTML, not JSON.
    const payload = (await response.json().catch(() => null)) as
        (T & { message?: string; errors?: Record<string, string[]> }) | null;

    if (!response.ok) {
        throw new Error(serverFailureMessage(response.status, payload ?? {}));
    }

    // A 200 that is not JSON (a login or proxy page) never counts as a saved order.
    if (payload === null) {
        throw new Error(UNEXPECTED_RESPONSE_MESSAGE);
    }

    return payload;
}
