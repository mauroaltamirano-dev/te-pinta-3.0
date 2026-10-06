import type { FloorArea } from './types';

export async function saveFloor(
    url: string,
    method: 'POST' | 'PUT',
    payload: unknown,
): Promise<{
    response: Response;
    data: {
        area?: FloorArea;
        message?: string;
        code?: string;
        errors?: Record<string, string[]>;
    };
}> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify(payload),
    });
    const data = await response.json();

    return { response, data };
}
