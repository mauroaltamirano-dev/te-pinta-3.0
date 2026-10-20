/**
 * Per-device station state in localStorage: whether this PC is the station and which
 * acknowledgements still have to reach the server. Storage can be unavailable (private
 * window, blocked site data); the station then works without remembering anything.
 */
const ACTIVE_KEY = 'print-station.active';
const ACKS_KEY = 'print-station.pending-acks';

export type PendingAck = { id: number; token: string };

function read(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string | null): void {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Nothing to remember without storage.
    }
}

export function isStationActive(): boolean {
    return read(ACTIVE_KEY) === '1';
}

export function setStationActive(active: boolean): void {
    write(ACTIVE_KEY, active ? '1' : null);
}

export function pendingAcks(): PendingAck[] {
    try {
        const parsed: unknown = JSON.parse(read(ACKS_KEY) ?? '[]');

        return Array.isArray(parsed)
            ? parsed.filter(
                  (item): item is PendingAck =>
                      typeof item?.id === 'number' &&
                      typeof item?.token === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

export function savePendingAcks(acks: PendingAck[]): void {
    write(ACKS_KEY, acks.length ? JSON.stringify(acks) : null);
}
