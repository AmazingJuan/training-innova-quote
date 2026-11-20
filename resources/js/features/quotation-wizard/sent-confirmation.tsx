import { corporate } from '@/components/public/links';
import type { PageProps } from '@/types';
import { Link } from '@inertiajs/react';
import { CircleCheck, Printer } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { draft, type SentRequest } from './draft';
import { Review } from './review';

const NEXT_STEPS = [
    'Revise su correo: allí está la confirmación con el número de su solicitud.',
    'Nuestro equipo revisa su solicitud y prepara la propuesta.',
    'La propuesta será enviada al correo electrónico registrado en el menor tiempo posible, con un enlace seguro para su descarga.',
];

type SentConfirmationProps = { sent: SentRequest; company: PageProps['company'] };

/**
 * The request was sent: its number, where the confirmation goes, what happens
 * next and a printable copy of what was sent. Takes the focus when it appears.
 */
export function SentConfirmation({ sent, company }: SentConfirmationProps) {
    const doneRef = useRef<HTMLDivElement>(null);
    const [contactCleared, setContactCleared] = useState(false);

    useEffect(() => {
        window.scrollTo({ top: 0 });
        doneRef.current?.focus({ preventScroll: true });
    }, []);

    return (
        <section className="frame pb-[clamp(4rem,2.4rem+6.5vw,9rem)] pt-4 lg:pt-6" aria-label="Solicitud enviada">
            <div className="grid items-start gap-10 lg:grid-cols-12 lg:gap-12">
                {/* What was received and what happens next, with the actions under it: on a phone the
                    summary goes between them (the wrapper dissolves into the grid), on a desk beside them. */}
                <div className="contents lg:col-span-5 lg:col-start-8 lg:row-start-1 lg:grid lg:gap-8">
                    <div
                        ref={doneRef}
                        tabIndex={-1}
                        className="order-1 rounded-control bg-azul px-[clamp(1.5rem,1rem+2vw,2.5rem)] py-[clamp(1.75rem,1.2rem+2vw,2.75rem)] text-white focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-[3px] focus-visible:outline-tinta print:border print:border-linea print:bg-white print:text-tinta"
                    >
                        <p className="flex items-center gap-2.5 font-display font-bold print:text-azul">
                            <CircleCheck className="h-6 w-6 shrink-0" aria-hidden />
                            Solicitud enviada
                        </p>
                        {sent.code && (
                            <p className="mt-6">
                                <span className="block text-sm text-white/85 print:text-pizarra">Número de solicitud</span>
                                <span className="mt-1 block font-display text-[clamp(1.75rem,1.4rem+1.4vw,2.5rem)] font-extrabold tabular-nums leading-tight tracking-snug">
                                    {sent.code}
                                </span>
                            </p>
                        )}
                        <p className="mt-5 text-white/85 print:text-pizarra">
                            La confirmación y la propuesta se le enviarán al correo{' '}
                            <strong className="font-semibold text-white [overflow-wrap:anywhere] print:text-tinta">{sent.email}</strong>.
                        </p>

                        <div className="mt-8 border-t border-white/25 pt-6 print:hidden">
                            <h2 className="font-display font-bold">Qué sigue</h2>
                            <ol className="mt-4 grid gap-4">
                                {NEXT_STEPS.map((text, i) => (
                                    <li key={text} className="grid grid-cols-[1.75rem_1fr] gap-3 text-white/90">
                                        <span
                                            className="grid h-7 w-7 place-items-center rounded-full border border-white/70 font-display text-sm font-bold text-white"
                                            aria-hidden
                                        >
                                            {i + 1}
                                        </span>
                                        <span className="pt-0.5">{text}</span>
                                    </li>
                                ))}
                            </ol>
                            {company.phone && (
                                <p className="mt-6 text-sm text-white/85">
                                    ¿Es urgente? Comuníquese al{' '}
                                    <a
                                        href={`tel:${company.phone.replace(/\s+/g, '')}`}
                                        className="font-semibold text-white underline underline-offset-[0.22em]"
                                    >
                                        {company.phone}
                                    </a>
                                    .
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="order-3 grid gap-4 print:hidden">
                        <div className="flex flex-wrap items-center gap-x-7 gap-y-4">
                            <Link href={route('quotation.select.business_unit')} className="btn btn-outline">
                                Solicitar otra cotización
                            </Link>
                            <a href={corporate.home} className="link">
                                Volver al sitio de Training Corporation S.A.S.
                            </a>
                        </div>
                        <p className="text-sm text-pizarra" aria-live="polite">
                            {contactCleared ? (
                                'Los datos de contacto fueron eliminados de este navegador.'
                            ) : (
                                <>
                                    Los datos de contacto se conservan en este navegador para futuras solicitudes.{' '}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            draft.clearContact();
                                            setContactCleared(true);
                                        }}
                                        className="link"
                                    >
                                        Eliminarlos de este navegador
                                    </button>
                                </>
                            )}
                        </p>
                    </div>
                </div>

                {sent.review.length > 0 && (
                    <section
                        aria-labelledby="resumen-enviado"
                        className="order-2 rounded-control border border-linea bg-white lg:order-none lg:col-span-7 lg:row-start-1 print:border-0"
                    >
                        <header className="flex flex-wrap items-center justify-between gap-4 border-b border-linea bg-nube/60 px-[clamp(1.25rem,0.8rem+1.8vw,2.25rem)] py-5 print:bg-white print:px-0">
                            <h2 id="resumen-enviado" className="subheading text-azul">
                                Resumen de la solicitud
                            </h2>
                            <button type="button" onClick={() => window.print()} className="btn btn-outline btn-sm print:hidden">
                                <Printer aria-hidden />
                                Imprimir o guardar en PDF
                            </button>
                        </header>
                        <Review groups={sent.review} className="px-[clamp(1.25rem,0.8rem+1.8vw,2.25rem)] pb-8 pt-7 print:px-0" />
                    </section>
                )}
            </div>
        </section>
    );
}
