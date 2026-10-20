import { toast } from 'sonner';

export const OFFLINE_SUBMISSION_MESSAGE =
    'Sin conexión con el servidor: no se guardó. Revisá la conexión y probá de nuevo.';

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

/** One generic toast for any failed admin submission; repeated failures replace it instead of piling up. */
export function notifySubmissionFailure(message: string): void {
    toast.error(message, { id: 'submission-failure' });
}
