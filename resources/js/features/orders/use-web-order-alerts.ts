import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const POLL_MS = 15_000;
/** Pages that list orders refresh themselves when a web order arrives. */
const ORDER_PAGES = ['admin/orders/index', 'admin/home'];

type SharedProps = { auth: { permissions: string[] } };

type WebPending = {
    pending_count: number;
    latest: {
        id: number;
        public_id: string;
        customer_name: string;
        url: string;
    } | null;
};

/**
 * Survives Inertia visits, so moving between admin pages does not repeat an alert. The first
 * response only sets the baseline: orders that were already waiting are on the orders page.
 */
let lastSeenId: number | null = null;

/** Every admin page tells staff when a new web order is waiting for confirmation. */
export function useWebOrderAlerts(): void {
    const { component, props } = usePage<SharedProps>();
    const enabled = props.auth.permissions.includes('orders.view');
    const refreshesList = ORDER_PAGES.includes(component);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let active = true;
        const tick = async () => {
            try {
                const response = await fetch('/admin/orders/web-pending', {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok || !active) {
                    return;
                }

                const { data } = (await response.json()) as {
                    data: WebPending;
                };
                const latest = data.latest;

                if (lastSeenId === null || latest === null) {
                    lastSeenId = Math.max(lastSeenId ?? 0, latest?.id ?? 0);

                    return;
                }

                if (latest.id <= lastSeenId) {
                    return;
                }

                lastSeenId = latest.id;
                chime();
                toast.info(
                    `Nuevo pedido web ${latest.public_id} · ${latest.customer_name}`,
                    {
                        description:
                            data.pending_count > 1
                                ? `${data.pending_count} pedidos web esperan confirmación.`
                                : 'Espera confirmación.',
                        duration: 30_000,
                        action: {
                            label: 'Ver',
                            onClick: () => router.visit(latest.url),
                        },
                    },
                );

                if (refreshesList) {
                    router.reload();
                }
            } catch {
                // The next tick retries; a dropped connection must not interrupt the admin.
            }
        };

        void tick();
        const timer = window.setInterval(() => void tick(), POLL_MS);

        return () => {
            active = false;
            window.clearInterval(timer);
        };
    }, [enabled, refreshesList]);
}

/** A short two-tone chime; browsers may block it until staff have clicked the page once. */
function chime(): void {
    try {
        const context = new AudioContext();
        [880, 1320].forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = context.currentTime + index * 0.18;
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.15, start);
            gain.gain.exponentialRampToValueAtTime(0.001, start + 0.16);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.16);
        });
        window.setTimeout(() => void context.close(), 600);
    } catch {
        // Sound is a bonus; the toast already tells staff.
    }
}
