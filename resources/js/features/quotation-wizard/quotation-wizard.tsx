import { corporate } from '@/components/public/links';
import { scrollIntoViewSoftly } from '@/lib/scroll';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CircleCheck, Printer, Send } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type MouseEvent } from 'react';
import { answeredPath, answerError, datePairErrors, formatAnswer, pathAnswers, pathFiles, visibleFields, type VisibleField } from './answers';
import { fieldAnchor } from './condition-field';
import { CONTACT_GROUPS, ContactFields, PrivacyConsent, validateContact, type ContactErrors } from './contact-fields';
import { draft, treeSignature, type ReviewGroup, type SentRequest } from './draft';
import { BlockFields, encuadreBlocks, paginate } from './encuadre-form';
import { ErrorSummary, Notice, type SummaryItem } from './notice';
import { ChannelsPanel, StepList, type Screen } from './step-nav';
import type { Answer, ContactData, PublicCondition, QuoteViewData } from './types';

const EMPTY_CONTACT: ContactData = { name: '', company: '', nit: '', economic_activity: '', address: '', role: '', email: '', phone: '' };

const PRIVACY_ERROR = 'Autorice el tratamiento de sus datos para enviar la solicitud.';

/** Problems of some encuadre fields, by question id. */
function encuadreProblems(conditions: Record<string, PublicCondition>, fields: VisibleField[], answers: Record<number, Answer>) {
    const errors: Record<number, string> = {};
    for (const { id } of fields) {
        const error = answerError(conditions[id], answers[id]);
        if (error) errors[id] = error;
    }
    return { ...errors, ...datePairErrors(conditions, fields, answers) };
}

const lowerFirst = (text: string) => text.charAt(0).toLowerCase() + text.slice(1);

/** "Razón social: este campo es obligatorio." or, for a question, "¿…? Este campo es obligatorio." */
const problemOf = (label: string, error: string) => (/[?.!]$/.test(label) ? `${label} ${error}` : `${label}: ${lowerFirst(error)}`);

/** How a screen is named: "Identificación de la necesidad, parte 2 de 3". */
const screenName = (screen: Screen) => (screen.parts > 1 ? `${screen.title}, parte ${screen.part} de ${screen.parts}` : screen.title);

/** Where a pager button goes; within the same block the part is enough ("Parte 2 de 3"). */
const pagerName = (to: Screen, from: Screen) => (to.group === from.group ? `Parte ${to.part} de ${to.parts}` : to.title);

const pagerButton = 'flex items-center gap-3 rounded-[10px] border-2 px-5 py-4 font-display font-bold transition-colors';
const pagerNext = cn(
    pagerButton,
    'justify-between border-azul bg-azul text-right text-white hover:border-azul-hondo hover:bg-azul-hondo max-sm:order-first sm:col-start-2',
);

/**
 * The quotation request: the "formato de encuadre" one screen at a time,
 * built with the corporate site's own tools and in the format's own formal
 * wording. What the client needs comes first and the client data last. The
 * step list works like the service explorer's line list, the pager names
 * where each button goes, an error summary takes the focus and links to every
 * field, the request is kept in this browser while it is filled in, and it is
 * reviewed in full before it is sent.
 */
export function QuotationWizard({ viewData }: { viewData: QuoteViewData }) {
    const { conditions, initial_condition_id: initialId, unit } = viewData;
    const { company, flash } = usePage<PageProps>().props;
    const signature = useMemo(() => treeSignature(conditions), [conditions]);
    const [saved] = useState(() => draft.answers(unit, signature));
    const [kept] = useState(() => draft.isKept());
    const [sentBefore] = useState(() => draft.sent(unit));

    const [contact, setContact] = useState<ContactData>(() => ({ ...EMPTY_CONTACT, ...draft.contact() }));
    const [answers, setAnswers] = useState<Record<number, Answer>>(saved?.answers ?? {});
    const [current, setCurrent] = useState(saved?.step ?? 0);
    const [reached, setReached] = useState(saved?.reached ?? 0);
    // A request left half done comes back where it was left, and says so.
    const [resumed, setResumed] = useState(() => Object.keys(saved?.answers ?? {}).length > 0);
    const [privacyAccepted, setPrivacyAccepted] = useState(false);
    const [web, setWeb] = useState(''); // honeypot
    const [showErrors, setShowErrors] = useState(false);
    const [serverErrors, setServerErrors] = useState<ContactErrors>({});
    const [serverFieldErrors, setServerFieldErrors] = useState<Record<number, string>>({});
    const [failure, setFailure] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const [uploaded, setUploaded] = useState<number | null>(null); // % sent, while documents go up
    const [announcement, setAnnouncement] = useState('');
    const [contactCleared, setContactCleared] = useState(false);

    const panelRef = useRef<HTMLElement>(null);
    const titleRef = useRef<HTMLHeadingElement>(null);
    const summaryRef = useRef<HTMLDivElement>(null);
    const doneRef = useRef<HTMLDivElement>(null);
    const moved = useRef(false);
    // What was sent, for the confirmation (the answers leave the form once it is sent).
    const sentReview = useRef<ReviewGroup[]>([]);

    const justSent = flash.quotation_sent ?? null;
    const sent: SentRequest | null = justSent ? { ...justSent, review: sentReview.current } : sentBefore;
    const isSent = sent !== null;

    const fields = useMemo(() => visibleFields(conditions, initialId, answers), [conditions, initialId, answers]);
    // Long blocks are split in parts that fit on screen.
    const pages = useMemo(() => encuadreBlocks(conditions, fields).flatMap((b) => paginate(conditions, b)), [conditions, fields]);
    const walked = useMemo(
        () =>
            answeredPath(
                conditions,
                fields.map((f) => f.id),
                answers,
            ),
        [conditions, fields, answers],
    );
    const answered = useMemo(
        () =>
            pathAnswers(
                conditions,
                fields.map((f) => f.id),
                answers,
            ),
        [conditions, fields, answers],
    );

    const steps: Screen[] = [
        ...pages.map((p) => ({
            id: `bloque-${p.key}-${p.part}`,
            group: p.key,
            title: p.title,
            short: p.short,
            hint: p.hint,
            part: p.part,
            parts: p.parts,
        })),
        {
            id: 'datos',
            group: 'datos',
            title: 'Datos del cliente',
            short: 'Datos',
            hint: 'Datos de la organización y de la persona de contacto, a quien se enviará la propuesta.',
            part: 1,
            parts: 1,
        },
        {
            id: 'envio',
            group: 'envio',
            title: 'Envío de la solicitud',
            short: 'Envío',
            hint: 'Verifique la información registrada antes de enviar la solicitud.',
            part: 1,
            parts: 1,
        },
    ];
    const last = steps.length - 1;
    const contactStep = last - 1;
    const step = Math.min(current, last);
    const screen = steps[step];
    const page = step < contactStep ? pages[step] : null;
    // Blocks as the step list counts them (a long block's parts are one step).
    const blocks = steps.map((s) => s.group).filter((group, index, all) => all.indexOf(group) === index);

    /** What keeps a screen from going on. The authorization is asked in the last one. */
    const contactProblemsOf = (index: number): ContactErrors => {
        if (index === contactStep) return validateContact(contact, true);
        if (index === last && !privacyAccepted) return { privacy_accepted: PRIVACY_ERROR };
        return {};
    };
    const fieldProblemsOf = (index: number) => (index < contactStep ? encuadreProblems(conditions, pages[index].fields, answers) : {});
    const isComplete = (index: number) => Object.keys(contactProblemsOf(index)).length + Object.keys(fieldProblemsOf(index)).length === 0;

    const contactErrors: ContactErrors = { ...serverErrors, ...(showErrors ? contactProblemsOf(step) : {}) };
    const fieldErrors: Record<number, string> = {
        ...Object.fromEntries((page?.fields ?? []).filter((f) => serverFieldErrors[f.id]).map((f) => [f.id, serverFieldErrors[f.id]])),
        ...(showErrors ? fieldProblemsOf(step) : {}),
    };
    const summary: SummaryItem[] = [
        ...Object.entries(contactErrors).map(([key, text]) => ({
            target: key === 'privacy_accepted' ? 'privacy' : `contact-${key}`,
            text: String(text),
        })),
        // In the order of the screen, not of the ids.
        ...(page?.fields ?? [])
            .filter(({ id }) => fieldErrors[id])
            .map(({ id }) => ({ target: fieldAnchor(id), text: problemOf(conditions[id].label, fieldErrors[id]) })),
    ];

    // Blocks whose screens were all reached and are complete: their marker becomes a check.
    const complete = new Set(
        steps.map((s) => s.group).filter((group) => steps.every((s, i) => s.group !== group || (i <= reached && isComplete(i)))),
    );

    // The whole request as it will be sent, block by block: the contact data first, since the proposal goes to that e-mail.
    const review: (ReviewGroup & { start: number })[] = [
        {
            title: 'Datos del cliente',
            short: true,
            start: contactStep,
            items: CONTACT_GROUPS.flatMap((g) => g.fields)
                .filter((f) => contact[f.key].trim() !== '')
                .map((f) => ({ question: f.label, answer: contact[f.key].trim() })),
        },
        ...blocks
            .filter((group) => group !== 'datos' && group !== 'envio')
            .map((group) => {
                const ids = new Set(pages.filter((p) => p.key === group).flatMap((p) => p.fields.map((f) => f.id)));
                return {
                    title: pages.find((p) => p.key === group)?.title ?? '',
                    start: steps.findIndex((s) => s.group === group),
                    items: walked
                        .filter((w) => ids.has(w.id))
                        .map((w) => ({ question: w.condition.label, answer: formatAnswer(w.condition, w.answer) })),
                };
            }),
    ].filter((group) => group.items.length > 0);

    const hasFiles = Object.values(answers).some((answer) => Array.isArray(answer) && answer.length > 0);
    const asksForFiles = Object.values(conditions).some((c) => c.interaction_type === 'input' && c.type === 'file');

    // Keep the request in this browser while it is filled in.
    useEffect(() => {
        draft.saveContact(contact);
    }, [contact]);
    useEffect(() => {
        if (!isSent) draft.saveAnswers(unit, { signature, answers, step, reached });
    }, [unit, signature, answers, step, reached, isSent]);

    // Sent: the draft goes away (the contact data stays for the next request) and the
    // confirmation is kept for this tab, so a reload shows it again instead of an empty form.
    useEffect(() => {
        if (!justSent) return;
        draft.clearAnswers(unit);
        draft.saveSent(unit, { ...justSent, review: sentReview.current });
    }, [justSent, unit]);

    useEffect(() => {
        if (!isSent) return;
        window.scrollTo({ top: 0 });
        doneRef.current?.focus({ preventScroll: true });
    }, [isSent]);

    // After changing screen: back to the top of the panel when it is out of view, focus on its title.
    useEffect(() => {
        if (!moved.current) return;
        moved.current = false;
        if ((panelRef.current?.getBoundingClientRect().top ?? 0) < 0) scrollIntoViewSoftly(panelRef.current);
        titleRef.current?.focus({ preventScroll: true });
        setAnnouncement(`Paso ${blocks.indexOf(screen.group) + 1} de ${blocks.length}: ${screenName(screen)}.`);
    }, [step]); // eslint-disable-line react-hooks/exhaustive-deps

    const go = (index: number, withErrors = false) => {
        moved.current = true;
        setCurrent(index);
        setReached((r) => Math.max(r, index));
        setShowErrors(withErrors);
        setResumed(false);
    };

    const focusSummary = () => requestAnimationFrame(() => summaryRef.current?.querySelector<HTMLElement>('[tabindex="-1"]')?.focus());

    const next = () => {
        if (!isComplete(step)) {
            setShowErrors(true);
            focusSummary();
            return;
        }
        go(step + 1);
    };

    const answer = (id: number, value: Answer) => {
        setAnswers((a) => ({ ...a, [id]: value }));
        if (serverFieldErrors[id]) setServerFieldErrors(({ [id]: _, ...rest }) => rest); // eslint-disable-line @typescript-eslint/no-unused-vars
    };

    const submit = (isRetry = false) => {
        if (processing && !isRetry) return;

        // An earlier answer may have opened new fields: send only when every screen is complete.
        const pending = steps.findIndex((_, index) => !isComplete(index));
        if (pending !== -1) {
            if (pending === step) setShowErrors(true);
            else go(pending, true);
            focusSummary();
            return;
        }

        setServerErrors({});
        setServerFieldErrors({});
        setFailure([]);
        sentReview.current = review.map(({ title, short, items }) => ({ title, short, items }));

        // A request left open (or kept as a draft) longer than the session gets "Page expired"
        // (419). Instead of Laravel's page, renew the session in the background and send again
        // once; the answers never leave the form.
        let expired = false;
        const stopWatching = router.on('invalid', (event) => {
            if (event.detail.response.status !== 419) return;
            event.preventDefault();
            expired = true;
        });

        const path = fields.map((f) => f.id);
        const documents = pathFiles(conditions, path, answers);

        router.post(
            route('quotation.store.proposal'),
            { contact, businessUnit: viewData.businessUnit, answers: answered, documents, privacy_accepted: privacyAccepted, web },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setAnnouncement('Se está enviando la solicitud. Por favor, no cierre esta página.');
                },
                onProgress: (progress) => documents.length > 0 && setUploaded(progress?.percentage ?? null),
                onFinish: () => {
                    stopWatching();
                    setUploaded(null);
                    if (!expired) return setProcessing(false);
                    if (!isRetry) return router.reload({ onSuccess: () => submit(true), onError: () => setProcessing(false) });
                    setProcessing(false);
                    setFailure([
                        'La sesión expiró y no fue posible renovarla. Recargue la página y envíe nuevamente la solicitud; sus respuestas se conservan.',
                    ]);
                    focusSummary();
                },
                onError: (errors) => {
                    // Answers are sent keyed by question: an error about one goes back to its field.
                    const idOf = Object.fromEntries(walked.map((w) => [w.key, w.id]));
                    const fromServer: ContactErrors = {};
                    const byField: Record<number, string> = {};
                    const others: string[] = [];
                    for (const [key, message] of Object.entries(errors)) {
                        const document = /^documents\.(\d+)\./.exec(key);
                        const field = key.startsWith('answers.')
                            ? idOf[key.slice('answers.'.length)]
                            : document
                              ? idOf[documents[Number(document[1])]?.question ?? '']
                              : undefined;

                        if (key.startsWith('contact.')) fromServer[key.slice('contact.'.length) as keyof ContactData] = message;
                        else if (key === 'privacy_accepted') fromServer.privacy_accepted = message;
                        else if (field !== undefined) byField[field] = message;
                        else others.push(message);
                    }
                    setServerErrors(fromServer);
                    setServerFieldErrors(byField);
                    setFailure(others);

                    const fieldPage = pages.findIndex((p) => p.fields.some((f) => byField[f.id]));
                    if (fieldPage !== -1) go(fieldPage, true);
                    else if (Object.keys(fromServer).some((k) => k !== 'privacy_accepted')) go(contactStep, true);
                    focusSummary();
                },
            },
        );
    };

    const startOver = () => {
        if (!window.confirm('¿Desea eliminar las respuestas de esta solicitud y reiniciarla? Los datos de contacto se conservarán.')) return;
        draft.clearAnswers(unit);
        setAnswers({});
        setPrivacyAccepted(false);
        setReached(0);
        go(0);
    };

    const changeUnit = (event: MouseEvent) => {
        if (
            hasFiles &&
            !window.confirm('Las respuestas se conservarán, pero los documentos adjuntos se retirarán. ¿Desea cambiar el tipo de servicio?')
        ) {
            event.preventDefault();
        }
    };

    if (sent) {
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
                                La confirmación y la propuesta llegarán a{' '}
                                <strong className="font-semibold text-white [overflow-wrap:anywhere] print:text-tinta">{sent.email}</strong>.
                            </p>

                            <div className="mt-8 border-t border-white/25 pt-6 print:hidden">
                                <h2 className="font-display font-bold">Qué sigue</h2>
                                <ol className="mt-4 grid gap-4">
                                    {[
                                        'Revise su correo: allí está la confirmación con el número de su solicitud.',
                                        'Nuestro equipo revisa su solicitud y prepara la propuesta.',
                                        'La propuesta será enviada al correo electrónico registrado en el menor tiempo posible, con un enlace seguro para su descarga.',
                                    ].map((text, i) => (
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
                                    Volver al sitio de Training Corporation
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

    const tools = (className: string) => (
        <RequestTools
            className={className}
            unitName={viewData.businessUnit}
            kept={kept}
            onChangeUnit={changeUnit}
            onStartOver={Object.keys(answers).length > 0 ? startOver : null}
        />
    );

    return (
        <div className="frame grid items-start gap-x-8 gap-y-6 pb-[clamp(4rem,2.4rem+6.5vw,9rem)] pt-5 lg:grid-cols-12 lg:pt-10">
            <p aria-live="polite" className="sr-only">
                {announcement}
            </p>

            <div className="grid gap-8 lg:sticky lg:top-[7.5rem] lg:col-span-4">
                <StepList steps={steps} current={step} reached={reached} complete={complete} onGo={(index: number) => go(index)} />
                {tools('hidden lg:grid')}
                <ChannelsPanel className="hidden lg:block" />
            </div>

            <section ref={panelRef} aria-labelledby="paso-titulo" className="min-w-0 scroll-mt-[6.5rem] lg:col-span-8 lg:scroll-mt-[7.5rem]">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (step === last) submit();
                        else next();
                    }}
                    noValidate
                >
                    <header className="mb-8">
                        <h2 id="paso-titulo" ref={titleRef} tabIndex={-1} className="subheading text-azul outline-none">
                            {screen.title}
                        </h2>
                        <p className="mt-2 font-semibold text-pizarra">
                            {screen.hint}
                            {screen.parts > 1 && ` Parte ${screen.part} de ${screen.parts}.`}
                        </p>
                        {!kept && (
                            <p className="mt-3 max-w-measure text-sm font-semibold text-tinta">
                                Este navegador no permite guardar las respuestas. Complete la solicitud antes de cerrar la página.
                            </p>
                        )}
                    </header>

                    <div className="grid gap-7">
                        {resumed && (
                            <Notice tone="ok" title="Solicitud recuperada">
                                <p>
                                    Se recuperaron las respuestas registradas anteriormente en este navegador.
                                    {asksForFiles && ' Los documentos adjuntos, si los había, deben cargarse nuevamente.'}{' '}
                                    <button type="button" onClick={startOver} className="link">
                                        Reiniciar la solicitud
                                    </button>
                                </p>
                            </Notice>
                        )}

                        <div ref={summaryRef} className="empty:hidden">
                            {summary.length > 0 && <ErrorSummary items={summary} action={step === last ? 'enviar' : 'continuar'} />}
                            {summary.length === 0 && failure.length > 0 && (
                                <Notice tone="error" title="La solicitud no se pudo enviar">
                                    <ul className="list-disc pl-5">
                                        {failure.map((message) => (
                                            <li key={message}>{message}</li>
                                        ))}
                                    </ul>
                                    <p className="mt-2">
                                        {company.email && (
                                            <>
                                                Escríbanos a{' '}
                                                <a href={`mailto:${company.email}`} className="font-semibold text-error underline">
                                                    {company.email}
                                                </a>
                                            </>
                                        )}
                                        {company.email && company.phone && ' o '}
                                        {company.phone && <>comuníquese al {company.phone}</>}. Sus respuestas se conservan en el formulario.
                                    </p>
                                </Notice>
                            )}
                        </div>

                        {page && (
                            <BlockFields fields={page.fields} conditions={conditions} answers={answers} onAnswer={answer} errors={fieldErrors} />
                        )}

                        {step === contactStep && (
                            <ContactFields
                                data={contact}
                                errors={contactErrors}
                                onChange={(field, value) => setContact((c) => ({ ...c, [field]: value }))}
                            />
                        )}

                        {step === last && (
                            <>
                                <Review groups={review} onChange={(index) => go(index)} />

                                <div className="absolute -left-[9999px]" aria-hidden>
                                    <label>
                                        No llenar{' '}
                                        <input
                                            type="text"
                                            name="web"
                                            tabIndex={-1}
                                            autoComplete="off"
                                            value={web}
                                            onChange={(e) => setWeb(e.target.value)}
                                        />
                                    </label>
                                </div>

                                <PrivacyConsent accepted={privacyAccepted} error={contactErrors.privacy_accepted} onChange={setPrivacyAccepted} />
                            </>
                        )}
                    </div>

                    {step === last && contact.email.trim() !== '' && (
                        <p className="mt-8 font-semibold text-tinta">
                            La propuesta será enviada a <span className="text-azul [overflow-wrap:anywhere]">{contact.email.trim()}</span>.
                        </p>
                    )}

                    {/* Like the explorer's pager: each button says where it goes. */}
                    <nav aria-label="Pasos" className={cn('grid gap-3 sm:grid-cols-2', step === last ? 'mt-4' : 'mt-10')}>
                        {step > 0 && (
                            <button
                                type="button"
                                onClick={() => go(step - 1)}
                                className={cn(pagerButton, 'border-linea text-left text-azul hover:border-azul')}
                            >
                                <ChevronLeft className="h-5 w-5 shrink-0" aria-hidden />
                                <span>
                                    <span className="block font-sans text-sm font-semibold text-pizarra">Anterior</span>
                                    {pagerName(steps[step - 1], screen)}
                                </span>
                            </button>
                        )}
                        {step < last ? (
                            <button type="submit" className={pagerNext}>
                                <span className="ml-auto">
                                    <span className="block font-sans text-sm font-semibold text-white/85">Siguiente</span>
                                    {pagerName(steps[step + 1], screen)}
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
                </form>
            </section>

            {tools('lg:hidden')}
            <ChannelsPanel className="lg:hidden" />
        </div>
    );
}

type RequestToolsProps = {
    className: string;
    unitName: string;
    kept: boolean;
    onChangeUnit: (event: MouseEvent) => void;
    /** Null while there is nothing to clear. */
    onStartOver: (() => void) | null;
};

/** The request itself, out of the way of the pager: which service it is, whether it is kept, and starting over. */
function RequestTools({ className, unitName, kept, onChangeUnit, onStartOver }: RequestToolsProps) {
    return (
        <div className={cn('gap-3 text-sm text-pizarra', className)}>
            <p>
                <span className="font-bold text-tinta">Servicio:</span> {unitName}.{' '}
                <Link href={route('quotation.select.business_unit')} onClick={onChangeUnit} className="link">
                    Cambiar tipo de servicio
                </Link>
            </p>
            {kept && <p>Las respuestas se guardan en este navegador mientras diligencia la solicitud.</p>}
            {onStartOver && (
                <p>
                    <button type="button" onClick={onStartOver} className="link">
                        Reiniciar la solicitud
                    </button>
                </p>
            )}
        </div>
    );
}

/** The request block by block, to check it before sending (with a way back to each block) and to keep a copy after. */
function Review({
    groups,
    onChange,
    className,
}: {
    groups: (ReviewGroup & { start?: number })[];
    onChange?: (start: number) => void;
    className?: string;
}) {
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
