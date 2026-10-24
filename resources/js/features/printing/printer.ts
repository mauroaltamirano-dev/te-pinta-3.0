import { StationRequestError, stationRequest } from './api';
import { printHtml } from './print-frame';
import { pendingAcks, savePendingAcks } from './storage';
import { jobHtml } from './templates';
import type { PrintJob } from './types';

const CLAIM_TIMEOUT_MS = 30_000;

type Envelope<T> = { data: T };

/**
 * Only a tab on screen may claim jobs: Chrome holds print() in a background tab until it is shown,
 * so a hidden tab would sit on claimed jobs that a visible one could print right away.
 */
export function canClaimJobs(): boolean {
    return document.visibilityState === 'visible';
}

/** Sends the stored acknowledgements; every tab of this PC shares them through localStorage. */
export async function flushAcks(): Promise<void> {
    for (const ack of pendingAcks()) {
        try {
            await stationRequest(
                `/admin/print-jobs/${ack.id}/printed`,
                'POST',
                {
                    claim_token: ack.token,
                },
            );
        } catch (error) {
            // 404/409/422: the job is gone or someone else settled it; nothing left to confirm.
            if (
                !(error instanceof StationRequestError) ||
                ![404, 409, 422].includes(error.status ?? 0)
            ) {
                throw error;
            }
        }

        // Re-read: another tab may have stored acknowledgements meanwhile.
        savePendingAcks(pendingAcks().filter((item) => item.id !== ack.id));
    }
}

/**
 * Claims the pending jobs, prints them and acknowledges them. A job that fails to print goes back
 * to the queue; `onFailure` gets the message to show. Returns whether any job was claimed.
 */
export async function printClaimedJobs(
    restaurant: string,
    onFailure: (message: string) => void,
): Promise<boolean> {
    // A claim committed on the server but abandoned here leaves its jobs unconfirmed, so it waits longer.
    const { data: jobs } = await stationRequest<Envelope<PrintJob[]>>(
        '/admin/print-jobs/claim',
        'POST',
        undefined,
        CLAIM_TIMEOUT_MS,
    );

    for (const job of jobs) {
        try {
            await printHtml(jobHtml(job, restaurant));
        } catch {
            const released = await stationRequest(
                `/admin/print-jobs/${job.id}/release`,
                'POST',
            ).then(
                () => true,
                () => false,
            );
            onFailure(
                released
                    ? `No se pudo imprimir el trabajo #${job.id}. Volvió a la cola.`
                    : `No se pudo imprimir el trabajo #${job.id} y quedó sin confirmar: mandalo de nuevo a la cola.`,
            );
            continue;
        }

        // Without a token the job stays unconfirmed and a person settles it.
        if (job.claim_token) {
            savePendingAcks([
                ...pendingAcks(),
                { id: job.id, token: job.claim_token },
            ]);
        }
    }

    // Once, after the batch: a failed acknowledgement stays stored and never stops printing.
    await flushAcks().catch(() => undefined);

    return jobs.length > 0;
}
