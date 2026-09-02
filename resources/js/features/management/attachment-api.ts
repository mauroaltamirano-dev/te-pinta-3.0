import type { ManagementAttachment } from './types';

type ErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

function xsrfToken() {
    return document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
}

function headers(extra: HeadersInit = {}): HeadersInit {
    const token = xsrfToken();

    return {
        Accept: 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        ...extra,
    };
}

async function requestError(response: Response, fallback: string) {
    const payload = (await response.json().catch(() => ({}))) as ErrorPayload;
    const firstError = payload.errors
        ? Object.values(payload.errors).flat()[0]
        : undefined;

    return new Error(firstError ?? payload.message ?? fallback);
}

function collectionUrl(transactionId: number) {
    return `/admin/management/transactions/${transactionId}/attachments`;
}

export async function listManagementAttachments(
    transactionId: number,
    signal: AbortSignal,
) {
    const response = await fetch(collectionUrl(transactionId), {
        credentials: 'same-origin',
        headers: headers(),
        signal,
    });

    if (!response.ok) {
        throw await requestError(
            response,
            'No se pudieron cargar los comprobantes.',
        );
    }

    const payload = (await response.json()) as {
        attachments: ManagementAttachment[];
    };

    return payload.attachments;
}

export async function uploadManagementAttachment(
    transactionId: number,
    file: File,
) {
    const data = new FormData();
    data.append('file', file);

    const response = await fetch(collectionUrl(transactionId), {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers(),
        body: data,
    });

    if (!response.ok) {
        throw await requestError(
            response,
            'No se pudo adjuntar el comprobante.',
        );
    }

    const payload = (await response.json()) as {
        attachment: ManagementAttachment;
    };

    return payload.attachment;
}

export async function deleteManagementAttachment(
    transactionId: number,
    attachmentId: number,
    reason: string,
) {
    const response = await fetch(
        `${collectionUrl(transactionId)}/${attachmentId}`,
        {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ reason }),
        },
    );

    if (!response.ok) {
        throw await requestError(
            response,
            'No se pudo eliminar el comprobante.',
        );
    }
}
