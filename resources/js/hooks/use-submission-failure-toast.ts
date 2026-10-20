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
 */
export function useSubmissionFailureToast(): void {
    useEffect(() => {
        const submissions = new Set<string>();
        const submitting = () => submissions.size > 0;

        const listeners = [
            router.on('start', (event) => {
                const visit = event.detail.visit;

                if (visit.method !== 'get') {
                    submissions.add(visit.id);
                }
            }),
            router.on('finish', (event) => {
                submissions.delete(event.detail.visit.id);
            }),
            router.on('error', (event) => {
                if (
                    !event.detail.visitId ||
                    !submissions.has(event.detail.visitId)
                ) {
                    return;
                }

                const first = Object.values(event.detail.errors)[0];
                notifySubmissionFailure(
                    typeof first === 'string' && first !== ''
                        ? first
                        : submissionFailureMessage(422),
                );
            }),
            router.on('httpException', (event) => {
                if (!submitting()) {
                    return;
                }

                notifySubmissionFailure(
                    submissionFailureMessage(event.detail.response.status),
                );

                // The toast replaces Inertia's error-page modal; local development keeps the modal.
                if (!import.meta.env.DEV) {
                    event.preventDefault();
                }
            }),
            router.on('networkError', (event) => {
                if (!submitting()) {
                    return;
                }

                notifySubmissionFailure(OFFLINE_SUBMISSION_MESSAGE);
                event.preventDefault();
            }),
        ];

        return () => listeners.forEach((off) => off());
    }, []);
}
