import { answeredPath, pathAnswers, pathFiles, validateFields, visibleFields } from '@/features/encuadre/answers';
import { fieldAnchor } from '@/features/encuadre/condition-field';
import { BlockFields, encuadreBlocks, paginate } from '@/features/encuadre/encuadre-form';
import type { Answer } from '@/features/encuadre/types';
import { scrollIntoViewSoftly } from '@/lib/scroll';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type MouseEvent } from 'react';
import { ContactFields, PrivacyConsent, validateContact, type ContactErrors } from './contact-fields';
import { draft, treeSignature, type ReviewGroup, type SentRequest } from './draft';
import { ErrorSummary, Notice, type SummaryItem } from './notice';
import { RequestTools } from './request-tools';
import { Review } from './review';
import { SentConfirmation } from './sent-confirmation';
import { ChannelsPanel, StepList } from './step-nav';
import type { ContactData, QuoteViewData } from './types';
import { EMPTY_CONTACT, PRIVACY_ERROR, blocksOf, buildSteps, placeServerErrors, problemOf, reviewGroups, screenName } from './wizard-model';
import { WizardPager } from './wizard-pager';

/**
 * The quotation request: the "formato de encuadre" one screen at a time,
 * built with the corporate site's own tools and in the format's own formal
 * wording. What the client needs comes first and the client data last. The
 * step list works like the service explorer's line list, the pager names
 * where each button goes, an error summary takes the focus and links to every
 * field, the request is kept in this browser while it is filled in, and it is
 * reviewed in full before it is sent.
 *
 * This component holds the state and the transitions; what the screens are,
 * what the review shows and where server errors go is in wizard-model.ts.
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
    // What was sent, for the confirmation (the answers leave the form once it is sent).
    const [sentReview, setSentReview] = useState<ReviewGroup[]>([]);

    const panelRef = useRef<HTMLElement>(null);
    const titleRef = useRef<HTMLHeadingElement>(null);
    const summaryRef = useRef<HTMLDivElement>(null);
    const moved = useRef(false);

    const justSent = flash.quotation_sent ?? null;
    const sent: SentRequest | null = useMemo(() => (justSent ? { ...justSent, review: sentReview } : sentBefore), [justSent, sentReview, sentBefore]);

    const fields = useMemo(() => visibleFields(conditions, initialId, answers), [conditions, initialId, answers]);
    const path = useMemo(() => fields.map((f) => f.id), [fields]);
    // Long blocks are split in parts that fit on screen.
    const pages = useMemo(() => encuadreBlocks(conditions, fields).flatMap((b) => paginate(conditions, b)), [conditions, fields]);
    const walked = useMemo(() => answeredPath(conditions, path, answers), [conditions, path, answers]);
    const steps = useMemo(() => buildSteps(pages), [pages]);
    const blocks = useMemo(() => blocksOf(steps), [steps]);
    const review = useMemo(() => reviewGroups(contact, steps, pages, walked), [contact, steps, pages, walked]);

    const last = steps.length - 1;
    const contactStep = last - 1;
    const step = Math.min(current, last);
    const screen = steps[step];
    const page = step < contactStep ? pages[step] : undefined;

    /** What keeps a screen from going on. The authorization is asked in the last one. */
    const contactProblemsOf = (index: number): ContactErrors => {
        if (index === contactStep) return validateContact(contact, true);
        if (index === last && !privacyAccepted) return { privacy_accepted: PRIVACY_ERROR };
        return {};
    };
    const fieldProblemsOf = (index: number) => {
        const fieldsOfPage = index < contactStep ? pages[index]?.fields : undefined;
        return fieldsOfPage ? validateFields(conditions, fieldsOfPage, answers) : {};
    };
    const isComplete = (index: number) => Object.keys(contactProblemsOf(index)).length + Object.keys(fieldProblemsOf(index)).length === 0;

    const contactErrors: ContactErrors = { ...serverErrors, ...(showErrors ? contactProblemsOf(step) : {}) };
    const fieldErrors: Record<number, string> = {
        ...Object.fromEntries((page?.fields ?? []).flatMap((f) => (serverFieldErrors[f.id] ? [[f.id, serverFieldErrors[f.id]]] : []))),
        ...(showErrors ? fieldProblemsOf(step) : {}),
    };
    const summary: SummaryItem[] = [
        ...Object.entries(contactErrors).map(([key, text]) => ({
            target: key === 'privacy_accepted' ? 'privacy' : `contact-${key}`,
            text: String(text),
        })),
        // In the order of the screen, not of the ids.
        ...(page?.fields ?? []).flatMap(({ id }) => {
            const error = fieldErrors[id];
            const label = conditions[id]?.label;
            return error && label ? [{ target: fieldAnchor(id), text: problemOf(label, error) }] : [];
        }),
    ];

    // Blocks whose screens were all reached and are complete: their marker becomes a check.
    const complete = new Set(blocks.filter((group) => steps.every((s, i) => s.group !== group || (i <= reached && isComplete(i)))));

    const hasFiles = Object.values(answers).some((answer) => Array.isArray(answer) && answer.length > 0);
    const asksForFiles = Object.values(conditions).some((c) => c.interaction_type === 'input' && c.type === 'file');

    // Keep the request in this browser while it is filled in.
    useEffect(() => {
        draft.saveContact(contact);
    }, [contact]);
    useEffect(() => {
        if (sent === null) draft.saveAnswers(unit, { signature, answers, step, reached });
    }, [unit, signature, answers, step, reached, sent]);

    // Sent: the draft goes away (the contact data stays for the next request) and the
    // confirmation is kept for this tab, so a reload shows it again instead of an empty form.
    useEffect(() => {
        if (!justSent) return;
        draft.clearAnswers(unit);
        draft.saveSent(unit, { ...justSent, review: sentReview });
    }, [justSent, unit, sentReview]);

    // After changing screen: back to the top of the panel when it is out of view, focus on its title.
    useEffect(() => {
        if (!moved.current || !screen) return;
        moved.current = false;
        if ((panelRef.current?.getBoundingClientRect().top ?? 0) < 0) scrollIntoViewSoftly(panelRef.current);
        titleRef.current?.focus({ preventScroll: true });
        setAnnouncement(`Paso ${blocks.indexOf(screen.group) + 1} de ${blocks.length}: ${screenName(screen)}.`);
    }, [step]); // eslint-disable-line react-hooks/exhaustive-deps -- only when the screen changes

    const go = (index: number, withErrors = false) => {
        moved.current = true;
        setCurrent(index);
        setReached((r) => Math.max(r, index));
        setShowErrors(withErrors);
        setResumed(false);
    };

    /** The error summary or the failure notice, whichever is shown. */
    const focusSummary = () => requestAnimationFrame(() => summaryRef.current?.focus());

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
        if (serverFieldErrors[id]) {
            setServerFieldErrors((errors) => Object.fromEntries(Object.entries(errors).filter(([key]) => Number(key) !== id)));
        }
    };

    // The retry after renewing the session uses the latest submit, not the one of an older render.
    const submitRef = useRef<(isRetry?: boolean) => void>(() => {});

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
        setSentReview(review.map(({ title, short, items }) => ({ title, short, items })));

        // A request left open (or kept as a draft) longer than the session gets "Page expired"
        // (419). Instead of Laravel's page, renew the session in the background and send again
        // once; the answers never leave the form.
        let expired = false;
        const stopWatching = router.on('invalid', (event) => {
            if (event.detail.response.status !== 419) return;
            event.preventDefault();
            expired = true;
        });

        const documents = pathFiles(conditions, path, answers);

        router.post(
            route('quotation.store.proposal'),
            { contact, unit, answers: pathAnswers(conditions, path, answers), documents, privacy_accepted: privacyAccepted, web },
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
                    if (!isRetry) return router.reload({ onSuccess: () => submitRef.current(true), onError: () => setProcessing(false) });
                    setProcessing(false);
                    setFailure([
                        'La sesión expiró y no fue posible renovarla. Recargue la página y envíe nuevamente la solicitud; sus respuestas se conservan.',
                    ]);
                    focusSummary();
                },
                onError: (errors) => {
                    const placed = placeServerErrors(errors, walked, documents);
                    setServerErrors(placed.contact);
                    setServerFieldErrors(placed.byField);
                    setFailure(placed.others);

                    const fieldPage = pages.findIndex((p) => p.fields.some((f) => placed.byField[f.id]));
                    if (fieldPage !== -1) go(fieldPage, true);
                    else if (Object.keys(placed.contact).some((k) => k !== 'privacy_accepted')) go(contactStep, true);
                    focusSummary();
                },
            },
        );
    };

    useEffect(() => {
        submitRef.current = submit;
    });

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
        return <SentConfirmation sent={sent} company={company} />;
    }

    if (!screen) return null;

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

                        {summary.length > 0 && <ErrorSummary ref={summaryRef} items={summary} action={step === last ? 'enviar' : 'continuar'} />}
                        {summary.length === 0 && failure.length > 0 && (
                            <Notice ref={summaryRef} tone="error" title="La solicitud no se pudo enviar">
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

                    <WizardPager
                        steps={steps}
                        step={step}
                        onBack={() => go(step - 1)}
                        processing={processing}
                        uploaded={uploaded}
                        className={cn(step === last ? 'mt-4' : 'mt-10')}
                    />
                </form>
            </section>

            {tools('lg:hidden')}
            <ChannelsPanel className="lg:hidden" />
        </div>
    );
}
