import { useCallback, useEffect, useRef, useState } from 'react';
import { StationRequestError, stationRequest } from './api';
import { printHtml } from './print-frame';
import {
    isStationActive,
    pendingAcks,
    savePendingAcks,
    setStationActive,
} from './storage';
import { jobHtml, testTicketHtml } from './templates';
import type { PrintJob, QueueStatus } from './types';

const POLL_MS = 3000;
const RECENT_EVERY_TICKS = 5;
export const STALE_SECONDS = 30;

export type StationConnection = 'ok' | 'offline' | 'session';

export type StationAlert = {
    id: string;
    title: string;
    detail: string;
    action?: { label: string; href: string };
};

type Envelope<T> = { data: T };

/**
 * Drives the shop PC: polls the queue, prints claimed jobs, acknowledges them (retrying from
 * localStorage when the network drops) and turns every failure into a visible alert.
 */
export function usePrintStation(restaurant: string) {
    const [active, setActiveState] = useState(isStationActive);
    const [status, setStatus] = useState<QueueStatus | null>(null);
    const [recent, setRecent] = useState<PrintJob[]>([]);
    const [unconfirmed, setUnconfirmed] = useState<PrintJob[]>([]);
    const [connection, setConnection] = useState<StationConnection>('ok');
    const [lastContactAt, setLastContactAt] = useState<Date | null>(null);
    const [printError, setPrintError] = useState<string | null>(null);
    const [wasHidden, setWasHidden] = useState(false);
    const activeRef = useRef(active);
    const busy = useRef(false);
    const ticks = useRef(0);
    const listedUnconfirmed = useRef(0);

    const report = useCallback((error: unknown) => {
        if (error instanceof StationRequestError && error.sessionLost) {
            setConnection('session');
        } else {
            setConnection('offline');
        }
    }, []);

    const refreshRecent = useCallback(async () => {
        const response = await stationRequest<
            Envelope<PrintJob[]> & { unconfirmed: PrintJob[] }
        >('/admin/print-jobs');
        setRecent(response.data);
        setUnconfirmed(response.unconfirmed);
        listedUnconfirmed.current = response.unconfirmed.length;
    }, []);

    const flushAcks = useCallback(async () => {
        let queue = pendingAcks();

        for (const ack of queue) {
            try {
                await stationRequest(
                    `/admin/print-jobs/${ack.id}/printed`,
                    'POST',
                    { claim_token: ack.token },
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

            queue = queue.filter((item) => item.id !== ack.id);
            savePendingAcks(queue);
        }
    }, []);

    const printClaimed = useCallback(async () => {
        const { data: jobs } = await stationRequest<Envelope<PrintJob[]>>(
            '/admin/print-jobs/claim',
            'POST',
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
                setPrintError(
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
    }, [flushAcks, restaurant]);

    const tick = useCallback(async () => {
        if (busy.current) {
            return;
        }

        busy.current = true;
        ticks.current += 1;

        try {
            await flushAcks();
            let { data } = await stationRequest<Envelope<QueueStatus>>(
                '/admin/print-jobs/status',
            );
            let changed = false;

            if (activeRef.current && data.pending > 0) {
                changed = await printClaimed();
                ({ data } = await stationRequest<Envelope<QueueStatus>>(
                    '/admin/print-jobs/status',
                ));
            }

            setStatus(data);
            setConnection('ok');
            setLastContactAt(new Date());

            if (
                changed ||
                data.unconfirmed !== listedUnconfirmed.current ||
                ticks.current % RECENT_EVERY_TICKS === 1
            ) {
                await refreshRecent();
            }
        } catch (error) {
            report(error);
        } finally {
            busy.current = false;
        }
    }, [flushAcks, printClaimed, refreshRecent, report]);

    useEffect(() => {
        const first = window.setTimeout(() => void tick(), 0);
        const timer = window.setInterval(() => void tick(), POLL_MS);
        const onVisibility = () => {
            if (document.visibilityState === 'hidden') {
                setWasHidden(true);
            } else {
                void tick();
            }
        };
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            window.clearTimeout(first);
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [tick]);

    useEffect(() => {
        const pending = status?.pending ?? 0;
        document.title = pending
            ? `(${pending}) Estación de impresión`
            : 'Estación de impresión';
    }, [status?.pending]);

    const setActive = useCallback(
        (value: boolean) => {
            activeRef.current = value;
            setActiveState(value);
            setStationActive(value);

            if (value) {
                void tick();
            }
        },
        [tick],
    );

    const settle = useCallback(
        async (url: string) => {
            try {
                await stationRequest(url, 'POST');
                setPrintError(null);
                await refreshRecent();
                void tick();
            } catch (error) {
                report(error);
            }
        },
        [refreshRecent, report, tick],
    );

    const alerts: StationAlert[] = [];

    if (connection === 'session') {
        alerts.push({
            id: 'session',
            title: 'Se cerró la sesión',
            detail: 'Esta PC dejó de imprimir. Iniciá sesión otra vez y volvé a esta página.',
            action: { label: 'Iniciar sesión', href: '/login' },
        });
    } else if (connection === 'offline') {
        alerts.push({
            id: 'offline',
            title: 'Sin conexión con el servidor',
            detail: `Las comandas no llegan${lastContactAt ? ` desde las ${lastContactAt.toLocaleTimeString('es-AR')}` : ''}. Revisá internet y que Tailscale esté conectado.`,
        });
    }

    if (status && !active && status.pending > 0) {
        alerts.push({
            id: 'inactive',
            title: 'Esta PC no está imprimiendo',
            detail: 'Hay trabajos en cola. Activá la estación si esta es la PC de la impresora.',
        });
    }

    if ((status?.oldest_pending_seconds ?? 0) >= STALE_SECONDS) {
        alerts.push({
            id: 'stale-pending',
            title: `${status?.pending} pendiente(s) hace más de ${STALE_SECONDS} segundos`,
            detail: 'Revisá que la estación esté activa, en su propia ventana al frente, y que la impresora tenga papel.',
        });
    }

    if ((status?.oldest_unconfirmed_seconds ?? 0) >= STALE_SECONDS) {
        alerts.push({
            id: 'stale-unconfirmed',
            title: 'Hay trabajos sin confirmar',
            detail: 'Se enviaron a la impresora pero no se confirmaron. Revisá si salieron y confirmalos o mandalos de nuevo a la cola.',
        });
    }

    if (printError) {
        alerts.push({
            id: 'print-error',
            title: 'Error de impresión',
            detail: printError,
        });
    }

    return {
        active,
        setActive,
        status,
        recent,
        unconfirmed,
        alerts,
        connection,
        lastContactAt,
        wasHidden,
        dismissHidden: () => setWasHidden(false),
        confirmPrinted: (id: number) =>
            settle(`/admin/print-jobs/${id}/printed`),
        release: (id: number) => settle(`/admin/print-jobs/${id}/release`),
        reprint: (id: number) => settle(`/admin/print-jobs/${id}/reprint`),
        printTest: () => printHtml(testTicketHtml(new Date())),
    };
}
