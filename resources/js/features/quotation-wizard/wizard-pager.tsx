import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight, Send } from 'lucide-react';
import type { Screen } from './step-nav';
import { pagerName } from './wizard-model';

const pagerButton = 'flex items-center gap-3 rounded-[10px] border-2 px-5 py-4 font-display font-bold transition-colors';
const pagerNext = cn(
    pagerButton,
    'justify-between border-azul bg-azul text-right text-white hover:border-azul-hondo hover:bg-azul-hondo max-sm:order-first sm:col-start-2',
);

type WizardPagerProps = {
    steps: Screen[];
    step: number;
    onBack: () => void;
    processing: boolean;
    /** % of the documents sent, while they go up. */
    uploaded: number | null;
    className?: string;
};

/**
 * Like the corporate site's explorer pager: each button says where it goes.
 * "Siguiente" and "Enviar" submit the form, so Enter works as expected.
 */
export function WizardPager({ steps, step, onBack, processing, uploaded, className }: WizardPagerProps) {
    const screen = steps[step];
    const isLast = step === steps.length - 1;
    const previous = steps[step - 1];
    const next = steps[step + 1];

    return (
        <nav aria-label="Pasos" className={cn('grid gap-3 sm:grid-cols-2', className)}>
            {previous && screen && (
                <button type="button" onClick={onBack} className={cn(pagerButton, 'border-linea text-left text-azul hover:border-azul')}>
                    <ChevronLeft className="h-5 w-5 shrink-0" aria-hidden />
                    <span>
                        <span className="block font-sans text-sm font-semibold text-pizarra">Anterior</span>
                        {pagerName(previous, screen)}
                    </span>
                </button>
            )}
            {!isLast && next && screen ? (
                <button type="submit" className={pagerNext}>
                    <span className="ml-auto">
                        <span className="block font-sans text-sm font-semibold text-white/85">Siguiente</span>
                        {pagerName(next, screen)}
                    </span>
                    <ChevronRight className="h-5 w-5 shrink-0" aria-hidden />
                </button>
            ) : (
                // Stays focusable while sending (aria-disabled), so the focus is not lost.
                <button
                    type="submit"
                    aria-disabled={processing || undefined}
                    className={cn(pagerNext, 'aria-disabled:cursor-progress aria-disabled:opacity-85')}
                >
                    <span className="ml-auto">
                        <span className="block font-sans text-sm font-semibold tabular-nums text-white/85">
                            {processing ? (uploaded !== null ? `Cargando documentos… ${uploaded} %` : 'Enviando…') : 'Último paso'}
                        </span>
                        Enviar solicitud
                    </span>
                    <Send className="h-5 w-5 shrink-0" aria-hidden />
                </button>
            )}
        </nav>
    );
}
