/** JSON calls of the print station; failures carry the HTTP status so the page can tell a lost session from a lost connection. */
export class StationRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number | null,
    ) {
        super(message);
    }

    get sessionLost(): boolean {
        return this.status === 401 || this.status === 419;
    }
}

function xsrfToken(): string | undefined {
    const token = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? decodeURIComponent(token) : undefined;
}

/** A hung request (half-open connection) must fail into the offline alert, not freeze the polling. */
const TIMEOUT_MS = 8000;

export async function stationRequest<T>(
    url: string,
    method: 'GET' | 'POST' = 'GET',
    body?: unknown,
    timeoutMs = TIMEOUT_MS,
): Promise<T> {
    let response: Response;

    try {
        const token = xsrfToken();
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                ...(method === 'POST'
                    ? { 'Content-Type': 'application/json' }
                    : {}),
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: method === 'POST' ? JSON.stringify(body ?? {}) : undefined,
            signal: AbortSignal.timeout(timeoutMs),
        });
    } catch {
        throw new StationRequestError('Sin conexión con el servidor.', null);
    }

    if (!response.ok) {
        throw new StationRequestError(
            response.status === 401 || response.status === 419
                ? 'La sesión se cerró.'
                : `El servidor respondió ${response.status}.`,
            response.status,
        );
    }

    return (await response.json()) as T;
}
