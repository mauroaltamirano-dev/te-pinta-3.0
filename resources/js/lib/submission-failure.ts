import { toast } from 'sonner';

// The request may have reached the server before the connection dropped.
export const OFFLINE_SUBMISSION_MESSAGE =
    'Sin conexión con el servidor. Revisá si se guardó antes de reintentar.';

export const UNEXPECTED_RESPONSE_MESSAGE =
    'Respuesta inesperada del servidor. Revisá si se guardó antes de reintentar.';

/** User-facing reason for a submission the server answered with an error status. */
export function submissionFailureMessage(status: number): string {
    switch (status) {
        case 403:
            return 'No tenés permiso para hacer esto.';
        case 419:
            return 'La sesión venció. Recargá la página e iniciá sesión de nuevo.';
        case 422:
            return 'No se pudo guardar: revisá los datos marcados.';
        case 429:
            return 'Demasiados intentos. Esperá un momento y probá de nuevo.';
        default:
            return `No se pudo completar la acción (error ${status}). Probá de nuevo.`;
    }
}

/**
 * Reason to show for a failed JSON request: the first validation error, the message of a 422, or a
 * message per status (Laravel's own messages for other statuses are English, for example "Server Error").
 */
export function serverFailureMessage(
    status: number,
    payload: { message?: string; errors?: Record<string, string[]> },
): string {
    const firstError = payload.errors
        ? Object.values(payload.errors).flat()[0]
        : undefined;

    return (
        firstError ??
        (status === 422 ? payload.message : undefined) ??
        submissionFailureMessage(status)
    );
}

/** One generic toast for any failed admin submission; repeated failures replace it instead of piling up. */
export function notifySubmissionFailure(message: string): void {
    toast.error(message, { id: 'submission-failure' });
}
