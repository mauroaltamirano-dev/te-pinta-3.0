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
    });
    const payload = (await response.json()) as T & {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : undefined;

        throw new Error(
            firstError ??
                payload.message ??
                'The request could not be completed.',
        );
    }

    return payload;
}
