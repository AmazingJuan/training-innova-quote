import { corporate } from '@/components/public/links';
import { SECTIONS } from '@/features/encuadre/sections';
import { draft } from '@/features/quotation-wizard/draft';
import { QuoteShell, unitSummaries, type QuotableUnit } from '@/features/quotation-wizard/quote-shell';
import { ChannelsPanel, StepList, type Screen } from '@/features/quotation-wizard/step-nav';
import PublicLayout from '@/layouts/public-layout';
import { capitalize } from '@/lib/format';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useEffect, useState } from 'react';

// The steps every request goes through, shown ahead (locked) so the client knows what comes.
const PREVIEW: Screen[] = [
    ...SECTIONS.map((s) => ({
        id: s.key,
        group: s.key,
        title: s.title,
        short: s.short,
        hint: s.hint,
        part: 1,
        parts: 1,
    })),
    { id: 'datos', group: 'datos', title: 'Datos del cliente', short: 'Datos', hint: '', part: 1, parts: 1 },
    { id: 'envio', group: 'envio', title: 'Envío de la solicitud', short: 'Envío', hint: '', part: 1, parts: 1 },
];

/**
 * Where a quotation starts: the same page as a unit's request (header, unit
 * tabs, steps and channels), with the choice of unit in place of the form, as
 * the corporate site's explorer shows an overview until a line is chosen.
 */
export default function SelectBusinessUnit() {
    const { businessUnits } = usePage<PageProps<{ viewData: { businessUnits: QuotableUnit[] } }>>().props.viewData;
    // Requests left half done in this browser.
    const [started] = useState(() => new Set(businessUnits.filter((u) => draft.hasAnswers(u.name)).map((u) => u.name)));
    // Coming back here starts a new request: a unit no longer shows the confirmation of the last one.
    useEffect(() => businessUnits.forEach((u) => draft.clearSent(u.name)), [businessUnits]);

    return (
        <PublicLayout title="Solicitar cotización" showCta={false}>
            <QuoteShell
                title="Solicitar cotización"
                crumb="Solicitar cotización"
                lede="Elija el tipo de servicio y complete el formulario de solicitud. Con esta información preparamos una propuesta a la medida."
                units={businessUnits}
                active={null}
            >
                <div className="frame grid items-start gap-x-8 gap-y-8 pb-[clamp(4rem,2.4rem+6.5vw,9rem)] pt-5 lg:grid-cols-12 lg:pt-10">
                    <div className="hidden gap-8 lg:sticky lg:top-[11rem] lg:col-span-4 lg:grid">
                        <StepList steps={PREVIEW} current={-1} reached={-1} complete={new Set()} onGo={() => {}} />
                        <ChannelsPanel />
                    </div>

                    <section aria-labelledby="elegir" className="min-w-0 lg:col-span-8">
                        <h2 id="elegir" className="subheading text-azul">
                            Elija el tipo de servicio
                        </h2>
                        <p className="mt-2 font-semibold text-pizarra">
                            Cada servicio tiene su propio formulario, con las preguntas que aplican a ese servicio.
                        </p>
                        <p className="mt-4 flex max-w-measure gap-3 text-pizarra">
                            <Clock className="mt-0.5 h-5 w-5 shrink-0 text-azul" aria-hidden />
                            <span>
                                Diligenciar la solicitud toma aproximadamente 15 minutos y puede retomarse posteriormente en este navegador. Se
                                recomienda tener a mano la información de la organización y, si aplica, el certificado de su sistema de gestión.
                            </span>
                        </p>

                        {businessUnits.length === 0 ? (
                            <p className="mt-8 max-w-measure rounded-[10px] border-2 border-linea p-6">
                                En este momento no hay servicios disponibles para cotizar en línea. Escríbanos desde la{' '}
                                <a href={corporate.contact} className="link">
                                    página de contacto
                                </a>{' '}
                                y le atenderemos directamente.
                            </p>
                        ) : (
                            <ul className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {businessUnits.map((unit) => (
                                    <li key={unit.name}>
                                        <Link
                                            href={route('quotation.quote.business_unit', unit.name)}
                                            className="group grid h-full grid-rows-[auto_1fr_auto] gap-3 rounded-[10px] border-2 border-linea p-6 text-inherit no-underline transition-colors duration-150 hover:border-azul"
                                        >
                                            <span className="font-display text-[clamp(1.375rem,1.2rem+0.7vw,1.75rem)] font-bold leading-tight text-azul">
                                                {capitalize(unit.display_name)}
                                            </span>
                                            <span className="text-base text-pizarra">{unitSummaries[unit.name] ?? ''}</span>
                                            <span className="font-display font-bold text-azul underline decoration-1 underline-offset-[0.2em] group-hover:decoration-2">
                                                {started.has(unit.name) ? 'Continuar su solicitud' : 'Empezar la solicitud'}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <ChannelsPanel className="lg:hidden" />
                </div>
            </QuoteShell>
        </PublicLayout>
    );
}
