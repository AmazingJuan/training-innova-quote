import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const AUTO_DISMISS_MS = 8000;

/** Shows the session flash (success/error) of the current visit. */
export default function FlashAlert() {
    const { flash } = usePage<PageProps>().props;
    const message = flash.error ?? flash.success ?? null;
    const isError = Boolean(flash.error);
    const [dismissed, setDismissed] = useState<string | null>(null);
    const [shownFlash, setShownFlash] = useState(flash);

    // A new visit shows its message again, even when it repeats the last one (adjusted while rendering, not in an effect).
    if (flash !== shownFlash) {
        setShownFlash(flash);
        setDismissed(null);
    }

    useEffect(() => {
        if (!message) return;
        const timer = setTimeout(() => setDismissed(message), AUTO_DISMISS_MS);
        return () => clearTimeout(timer);
    }, [message, flash]);

    if (!message || dismissed === message) return null;

    const Icon = isError ? AlertCircle : CheckCircle2;

    return (
        <div
            role={isError ? 'alert' : 'status'}
            className={cn(
                'animate-fade-in flex items-start gap-3 rounded-control border-2 px-4 py-3 text-base',
                isError ? 'border-error/40 bg-red-50 text-error' : 'border-azul bg-nube text-tinta',
            )}
        >
            <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', isError ? 'text-error' : 'text-azul')} aria-hidden />
            <p className="min-w-0 flex-1 font-semibold">{message}</p>
            <button
                type="button"
                onClick={() => setDismissed(message)}
                className="-m-1 rounded-control p-1 opacity-70 transition hover:opacity-100"
                aria-label="Cerrar aviso"
            >
                <X className="h-4 w-4" aria-hidden />
            </button>
        </div>
    );
}
