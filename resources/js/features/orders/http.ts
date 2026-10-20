import {
    OFFLINE_SUBMISSION_MESSAGE,
    submissionFailureMessage,
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
    const payload = (await response.json().catch(() => ({}))) as T & {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : undefined;

        throw new Error(
            firstError ??
                // Laravel's own messages for other statuses are English ("Server Error").
                (response.status === 422 ? payload.message : undefined) ??
                submissionFailureMessage(response.status),
        );
    }

    return payload;
}
