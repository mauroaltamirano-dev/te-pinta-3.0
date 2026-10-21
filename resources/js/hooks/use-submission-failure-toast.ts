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
        // Visit id → whether that in-flight visit is a submission (non-GET).
        const inFlightIsSubmission = new Map<string, boolean>();
        let unattributed: string | null = null;
        const onlySubmissions = () =>
            inFlightIsSubmission.size > 0 &&
            [...inFlightIsSubmission.values()].every(Boolean);

        function failed(event: Event, message: string, preventModal: boolean) {
            if (onlySubmissions()) {
                notifySubmissionFailure(message);

                if (preventModal) {
                    event.preventDefault();
                }
            } else if (inFlightIsSubmission.size > 0) {
                unattributed = message;
            }
        }

        const listeners = [
            router.on('start', (event) => {
                const visit = event.detail.visit;
                inFlightIsSubmission.set(visit.id, visit.method !== 'get');
            }),
            router.on('finish', (event) => {
                const id = event.detail.visit.id;

                // The failure event fires just before its own visit finishes.
                if (unattributed !== null && inFlightIsSubmission.get(id)) {
                    notifySubmissionFailure(unattributed);
                }

                unattributed = null;
                inFlightIsSubmission.delete(id);
            }),
            router.on('error', (event) => {
                const visitId = event.detail.visitId;

                if (!visitId || !inFlightIsSubmission.get(visitId)) {
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
