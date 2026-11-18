import { useCallback, useEffect, useRef, useState } from 'react';

const POLL_INTERVAL_MS = 1500;
/** ~3 minutes, in case the queue worker is slow to pick the job up. */
const MAX_POLLS = 120;

function readCookie(name: string): string {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

/**
 * Starts the queued Excel export and polls its status until the file is ready,
 * then triggers the download. Polling stops if the component unmounts.
 */
export function useClientsExport(filters: Record<string, string>) {
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout>>();
    const alive = useRef(true);

    // Set on every mount: StrictMode mounts, unmounts and mounts again in development.
    useEffect(() => {
        alive.current = true;
        return () => {
            alive.current = false;
            clearTimeout(timer.current);
        };
    }, []);

    const fail = (message: string) => {
        if (!alive.current) return;
        setExporting(false);
        setError(message);
    };

    const poll = useCallback(async (token: string, attempt: number) => {
        if (!alive.current) return;
        if (attempt > MAX_POLLS) {
            return fail('La generación está tardando demasiado. Verifique que el worker de la cola esté en ejecución.');
        }

        try {
            const response = await fetch(route('dashboard.clients.export.status', token), { headers: { Accept: 'application/json' } });
            const { status } = (await response.json()) as { status: string };

            if (status === 'ready') {
                setExporting(false);
                window.location.href = route('dashboard.clients.export.download', token);
            } else if (status === 'failed' || status === 'unknown') {
                fail('No se pudo generar el archivo. Intente nuevamente.');
            } else {
                timer.current = setTimeout(() => void poll(token, attempt + 1), POLL_INTERVAL_MS);
            }
        } catch {
            fail('Se perdió la conexión durante la exportación.');
        }
    }, []);

    const start = useCallback(async () => {
        if (exporting) return;
        setError(null);
        setExporting(true);

        try {
            const response = await fetch(route('dashboard.clients.export.start'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
                },
                body: JSON.stringify(filters),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const { token } = (await response.json()) as { token: string };
            poll(token, 0);
        } catch {
            fail('No se pudo iniciar la exportación. Intente nuevamente.');
        }
    }, [exporting, filters, poll]);

    return { start, exporting, error };
}
