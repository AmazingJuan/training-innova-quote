import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Check, Mail, Phone } from 'lucide-react';

/** One screen of the form; long blocks take several (`part` of `parts`) under the same `group`. */
export type Screen = { id: string; group: string; title: string; short: string; hint: string; part: number; parts: number };

type StepListProps = {
    steps: Screen[];
    /** Index of the screen on view. */
    current: number;
    /** Furthest screen reached: blocks up to it can be revisited. */
    reached: number;
    /** Blocks whose fields are all complete. */
    complete: Set<string>;
    onGo: (index: number) => void;
};

/**
 * The blocks of the request, built like the line list of the corporate site's
 * service explorer: a column of bordered rows on large screens, compact chips
 * with short names on phones. Numbers are solid discs (at this size an open
 * ring reads as a loading spinner) and turn into a check when a block is done.
 */
export function StepList({ steps, current, reached, complete, onGo }: StepListProps) {
    const entries = steps.flatMap((step, start) => (step.part === 1 ? [{ ...step, start }] : []));
    const activeGroup = steps[current]?.group;

    const activeIndex = entries.findIndex((entry) => entry.group === activeGroup);

    return (
        <nav aria-label="Pasos de su solicitud">
            {/* Phones: one line and one segment per block, so the form starts right away. */}
            <div className="lg:hidden">
                <p className="flex items-baseline justify-between gap-4 font-display font-bold text-tinta" aria-hidden>
                    <span>{entries[activeIndex]?.short}</span>
                    <span className="font-sans text-sm font-semibold text-pizarra">
                        Paso {activeIndex + 1} de {entries.length}
                    </span>
                </p>
                {/* Done blocks are solid, the current one is taller, blocks left half done are light. */}
                <ol className="flex gap-1.5">
                    {entries.map((entry, index) => {
                        const active = index === activeIndex;
                        const locked = entry.start > reached;
                        const done = !active && complete.has(entry.group);
                        return (
                            <li key={entry.group} className="flex-1">
                                <button
                                    type="button"
                                    onClick={() => !active && onGo(entry.start)}
                                    disabled={locked}
                                    aria-current={active ? 'step' : undefined}
                                    className="flex h-11 w-full items-center disabled:cursor-default"
                                >
                                    <span
                                        className={cn(
                                            'w-full rounded-full transition-colors',
                                            active ? 'h-2.5 bg-azul' : 'h-1.5',
                                            !active && (done ? 'bg-azul' : locked ? 'bg-linea' : 'bg-cielo'),
                                        )}
                                    />
                                    <span className="sr-only">
                                        {index + 1}. {entry.title}
                                        {done && ' (completo)'}
                                        {!active && !done && !locked && ' (pendiente)'}
                                        {locked && ' (se habilita al completar los pasos anteriores)'}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </div>

            <p className="mb-3 hidden font-bold text-pizarra lg:block">Pasos de su solicitud</p>
            <ol className="hidden flex-col gap-2 lg:flex">
                {entries.map((entry, index) => {
                    const active = entry.group === activeGroup;
                    const locked = entry.start > reached;
                    const done = !active && !locked && complete.has(entry.group);
                    return (
                        <li key={entry.group}>
                            <button
                                type="button"
                                onClick={() => !active && onGo(entry.start)}
                                disabled={locked}
                                aria-current={active ? 'step' : undefined}
                                title={locked ? 'Se habilita al completar los pasos anteriores' : undefined}
                                className={cn(
                                    'flex min-h-12 w-full items-center gap-3 rounded-control border-2 py-2 pl-2 pr-4 text-left font-display font-bold transition-colors duration-150',
                                    active && 'border-azul bg-azul text-white',
                                    !active && !locked && 'border-linea text-tinta hover:border-azul',
                                    locked && 'cursor-default border-linea text-pizarra',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-extrabold tabular-nums',
                                        active ? 'bg-azul-hondo text-white' : done ? 'bg-azul text-white' : 'bg-nube text-azul-hondo',
                                    )}
                                    aria-hidden
                                >
                                    {done ? <Check className="h-4 w-4" strokeWidth={3} /> : index + 1}
                                </span>
                                {entry.title}
                                {done && <span className="sr-only">(completo)</span>}
                                {locked && <span className="sr-only">(se habilita al completar los pasos anteriores)</span>}
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

/** The contact page's blue channels panel, for whoever prefers to call or write. */
export function ChannelsPanel({ className }: { className?: string }) {
    const { company } = usePage<PageProps>().props;
    if (!company.phone && !company.email) return null;

    return (
        <aside aria-labelledby="canales" className={cn('field-azul p-6 lg:p-8', className)}>
            <h2 id="canales" className="font-display text-xl font-bold leading-tight">
                ¿Prefiere hablar directamente?
            </h2>
            <ul className="mt-5 grid gap-5">
                {company.phone && (
                    <li className="grid grid-cols-[1.5rem_minmax(0,1fr)] gap-3">
                        <Phone className="h-6 w-6" aria-hidden />
                        <div>
                            <p className="text-sm font-bold">Teléfono</p>
                            <a href={`tel:${company.phone.replace(/[^\d+]/g, '')}`} className="text-lg font-semibold no-underline hover:underline">
                                {company.phone}
                            </a>
                        </div>
                    </li>
                )}
                {company.email && (
                    <li className="grid grid-cols-[1.5rem_minmax(0,1fr)] gap-3">
                        <Mail className="h-6 w-6" aria-hidden />
                        <div>
                            <p className="text-sm font-bold">Correo</p>
                            <a href={`mailto:${company.email}`} className="font-semibold no-underline [overflow-wrap:anywhere] hover:underline">
                                {company.email}
                            </a>
                        </div>
                    </li>
                )}
            </ul>
        </aside>
    );
}
