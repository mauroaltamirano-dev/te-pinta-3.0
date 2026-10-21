import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const PAYLOAD_TOO_LARGE = 413;
const FALLBACK_MESSAGE = 'El archivo es demasiado grande.';

/**
 * Shows a request body rejected by the PHP size limit as a toast instead of
 * Inertia's error dialog, keeping the form and its input on screen.
 */
export function usePayloadTooLargeToast(): void {
    useEffect(() => {
        return router.on('httpException', (event) => {
            const { response } = event.detail;

            if (response.status !== PAYLOAD_TOO_LARGE) {
                return;
            }

            event.preventDefault();
            const message =
                typeof response.data === 'object'
                    ? response.data.message
                    : undefined;
            toast.error(
                typeof message === 'string' ? message : FALLBACK_MESSAGE,
            );
        });
    }, []);
}
