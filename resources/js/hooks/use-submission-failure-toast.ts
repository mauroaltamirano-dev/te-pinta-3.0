import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    notifySubmissionFailure,
    OFFLINE_SUBMISSION_MESSAGE,
    submissionFailureMessage,
} from '@/lib/submission-failure';

/**
 * Toasts every Inertia submission (any non-GET visit) that fails: validation errors, error statuses
 * and lost connections. Page navigations and polling keep Inertia's default handling.
 *
 * Error-status and connection events do not say which visit failed. When only submissions are in
 * flight the failure is theirs: the toast replaces Inertia's handling. When a GET is in flight too,
 * Inertia keeps its default handling and the toast is decided when the failed visit finishes.
 */
export function useSubmissionFailureToast(): void {
    useEffect(() => {
        const inFlight = new Map<string, boolean>();
        let unattributed: string | null = null;
        const onlySubmissions = () =>
            inFlight.size > 0 && [...inFlight.values()].every(Boolean);

        function failed(event: Event, message: string, preventModal: boolean) {
            if (onlySubmissions()) {
                notifySubmissionFailure(message);

                if (preventModal) {
                    event.preventDefault();
                }
            } else if (inFlight.size > 0) {
                unattributed = message;
            }
        }

        const listeners = [
            router.on('start', (event) => {
                const visit = event.detail.visit;
                inFlight.set(visit.id, visit.method !== 'get');
            }),
            router.on('finish', (event) => {
                const id = event.detail.visit.id;

                // The failure event fires just before its own visit finishes.
                if (unattributed !== null && inFlight.get(id)) {
                    notifySubmissionFailure(unattributed);
                }

                unattributed = null;
                inFlight.delete(id);
            }),
            router.on('error', (event) => {
                const visitId = event.detail.visitId;

                if (!visitId || !inFlight.get(visitId)) {
                    return;
                }

                const first = Object.values(event.detail.errors)[0];
                notifySubmissionFailure(
                    typeof first === 'string' && first !== ''
                        ? first
                        : submissionFailureMessage(422),
                );
            }),
            router.on('httpException', (event) =>
                failed(
                    event,
                    submissionFailureMessage(event.detail.response.status),
                    // Local development keeps Inertia's error-page modal.
                    !import.meta.env.DEV,
                ),
            ),
            router.on('networkError', (event) =>
                failed(event, OFFLINE_SUBMISSION_MESSAGE, true),
            ),
        ];

        return () => listeners.forEach((off) => off());
    }, []);
}
