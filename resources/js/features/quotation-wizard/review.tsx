import { cn } from '@/lib/utils';
import type { ReviewGroup } from './draft';

type ReviewProps = {
    groups: (ReviewGroup & { start?: number })[];
    /** Before sending: a way back to each block. */
    onChange?: (start: number) => void;
    className?: string;
};

/** The request block by block, to check it before sending (with a way back to each block) and to keep a copy after. */
export function Review({ groups, onChange, className }: ReviewProps) {
    return (
        <div className={cn('grid gap-10', className)}>
            {groups.map((group) => (
                <section key={group.title} aria-label={group.title} className="break-inside-avoid-page">
                    <div className="flex items-baseline justify-between gap-4 border-b-2 border-azul/25 pb-2.5">
                        <h3 className="font-display text-lg font-bold text-tinta">{group.title}</h3>
                        {onChange && group.start !== undefined && (
                            <button type="button" onClick={() => onChange(group.start ?? 0)} className="link shrink-0 font-semibold">
                                Modificar<span className="sr-only"> {group.title.toLowerCase()}</span>
                            </button>
                        )}
                    </div>
                    {group.short ? (
                        // Short values (the client's data) read as a card, two by two.
                        <dl className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,16rem),1fr))] gap-x-8 gap-y-5 pt-5">
                            {group.items.map(({ question, answer }) => (
                                <div key={question}>
                                    <dt className="text-[0.9375rem] text-pizarra">{question}</dt>
                                    <dd className="mt-0.5 font-semibold text-tinta [overflow-wrap:anywhere]">{answer}</dd>
                                </div>
                            ))}
                        </dl>
                    ) : (
                        <dl className="divide-y divide-linea">
                            {group.items.map(({ question, answer }) => (
                                <div key={question} className="grid gap-1 py-3.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-8">
                                    <dt className="text-[0.9375rem] text-pizarra">{question}</dt>
                                    <dd className="whitespace-pre-line font-semibold text-tinta [overflow-wrap:anywhere]">{answer}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </section>
            ))}
        </div>
    );
}
