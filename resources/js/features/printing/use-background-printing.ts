import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { stationRequest } from './api';
import { canClaimJobs, flushAcks, printClaimedJobs } from './printer';
import { isStationActive } from './storage';
import type { QueueStatus } from './types';

const POLL_MS = 3000;
const STATION_PAGE = 'admin/printing/station';

type SharedProps = {
    restaurantName: string | null;
    auth: { permissions: string[] };
};

/**
 * On the PC activated as print station, every admin page prints the queue, so the cashier can
 * work on any screen. The station page runs its own loop with alerts, so this one stays off there.
 * Connection problems are left to the station page; only a failed print is shown here.
 */
export function useBackgroundPrinting(): void {
    const { component, props } = usePage<SharedProps>();
    const restaurant = props.restaurantName;
    const enabled =
        component !== STATION_PAGE &&
        restaurant !== null &&
        props.auth.permissions.includes('orders.manage');

    useEffect(() => {
        if (!enabled || restaurant === null) {
            return;
        }

        let busy = false;
        const tick = async () => {
            if (busy || !isStationActive() || !canClaimJobs()) {
                return;
            }

            busy = true;

            try {
                await flushAcks();
                const { data } = await stationRequest<{ data: QueueStatus }>(
                    '/admin/print-jobs/status',
                );

                if (data.pending > 0) {
                    await printClaimedJobs(restaurant, (message) =>
                        toast.error(message),
                    );
                }
            } catch {
                // The next tick retries; the station page explains connection problems.
            } finally {
                busy = false;
            }
        };

        const first = window.setTimeout(() => void tick(), 0);
        const timer = window.setInterval(() => void tick(), POLL_MS);
        const onVisibility = () => void tick();
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            window.clearTimeout(first);
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled, restaurant]);
}
