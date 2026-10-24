import { useCallback, useEffect, useRef, useState } from 'react';
import { StationRequestError, stationRequest } from './api';
import { printHtml } from './print-frame';
import { canClaimJobs, flushAcks, printClaimedJobs } from './printer';
import { isStationActive, setStationActive } from './storage';
import { testTicketHtml } from './templates';
import type { PrintJob, QueueStatus } from './types';

const POLL_MS = 3000;
/** The server lists at most this many unconfirmed jobs (PrintJobController). */
const UNCONFIRMED_LIMIT = 50;
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
 * Drives the station page: polls the queue, prints claimed jobs while on screen, acknowledges them
 * (retrying from localStorage when the network drops) and turns every failure into a visible alert.
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

            if (activeRef.current && data.pending > 0 && canClaimJobs()) {
                changed = await printClaimedJobs(restaurant, setPrintError);
                ({ data } = await stationRequest<Envelope<QueueStatus>>(
                    '/admin/print-jobs/status',
                ));
            }

            setStatus(data);
            setConnection('ok');
            setLastContactAt(new Date());

            if (
                changed ||
                Math.min(data.unconfirmed, UNCONFIRMED_LIMIT) !==
                    listedUnconfirmed.current ||
                ticks.current % RECENT_EVERY_TICKS === 1
            ) {
                await refreshRecent();
            }
        } catch (error) {
            report(error);
        } finally {
            busy.current = false;
        }
    }, [refreshRecent, report, restaurant]);

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
            detail: 'Revisá que la estación esté activa, que haya una pantalla del admin a la vista en esta PC y que la impresora tenga papel.',
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
