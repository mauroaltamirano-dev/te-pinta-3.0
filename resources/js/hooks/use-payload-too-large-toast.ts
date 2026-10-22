import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    notifySubmissionFailure,
    PAYLOAD_TOO_LARGE_MESSAGE,
} from '@/lib/submission-failure';

const PAYLOAD_TOO_LARGE = 413;

/**
 * Shows a request body rejected by the PHP size limit as a toast instead of
 * Inertia's error dialog, keeping the form and its input on screen. It shares the failed-submission
 * toast, so the admin panel's own failure toast replaces it instead of adding a second one.
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
                // A proxy may answer with an empty or non-object body.
                response.data !== null && typeof response.data === 'object'
                    ? response.data.message
                    : undefined;
            notifySubmissionFailure(
                typeof message === 'string'
                    ? message
                    : PAYLOAD_TOO_LARGE_MESSAGE,
            );
        });
    }, []);
}
