import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { adminListShellClass } from '@/components/admin/styles';
import { treeApi, type Placement } from '@/features/decision-tree/api';
import { draftIssues, editDraft, isDirty, newDraft, type Draft } from '@/features/decision-tree/draft';
import { arrivalsTo, labelOf, mainSequence, validateTree } from '@/features/decision-tree/graph';
import { QuestionCard } from '@/features/decision-tree/question-card';
import { TestRun } from '@/features/decision-tree/test-run';
import type { ConditionData, TreeData } from '@/features/decision-tree/types';
import { sectionOf } from '@/features/encuadre/sections';
import AdminLayout from '@/layouts/admin-layout';
import { scrollIntoViewSoftly } from '@/lib/scroll';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { AlertCircle, Play, Plus } from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';

type DecisionTreePageProps = PageProps<{ viewData: { businessUnits: TreeData[] } }>;

const DISCARD_WARNING = 'Tiene cambios sin guardar en la pregunta que está editando. ¿Desea descartarlos?';

function InsertHere({ onClick, disabled, label = 'Insertar pregunta aquí' }: { onClick: () => void; disabled: boolean; label?: string }) {
    return (
        <li className="group relative flex h-9 items-center justify-center">
            <span
                className="absolute inset-x-6 top-1/2 h-0.5 bg-linea opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
                aria-hidden
            />
            <button
                type="button"
                onClick={onClick}
                disabled={disabled}
                className="relative inline-flex items-center gap-1 rounded-full border-2 border-linea bg-white px-3 py-0.5 font-display text-xs font-bold text-azul opacity-60 transition-opacity hover:border-azul hover:opacity-100 focus-visible:opacity-100 disabled:invisible group-hover:opacity-100"
            >
                <Plus className="h-3.5 w-3.5" aria-hidden />
                {label}
            </button>
        </li>
    );
}

export default function DecisionTreePage() {
    const { businessUnits } = usePage<DecisionTreePageProps>().props.viewData;
    const [selectedId, setSelectedId] = useState<number | null>(businessUnits[0]?.id ?? null);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [serverErrors, setServerErrors] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [testing, setTesting] = useState(false);

    // Always the saved state: every action reloads it from the server.
    const tree = businessUnits.find((t) => t.id === selectedId) ?? null;
    const sequence = useMemo(() => (tree ? mainSequence(tree) : []), [tree]);
    const branchIds = useMemo(() => (tree ? tree.conditions.filter((c) => !sequence.includes(c.id)).map((c) => c.id) : []), [tree, sequence]);
    const savedIssues = useMemo(() => (tree ? validateTree(tree) : []), [tree]);
    const pendingIssues = useMemo(() => (tree && draft ? draftIssues(tree, draft) : []), [tree, draft]);
    const dirty = draft !== null && (isDirty(draft) || draft.question.id < 0);
    const locked = busy || draft !== null;
    const sources = businessUnits.filter((t) => t.id !== selectedId && t.conditions.length > 0);

    const questionText = (id: number) => labelOf(tree?.conditions.find((c) => c.id === id));

    /** How a question is named in sentences: "la pregunta 3", or its text for branch questions. */
    const refer = (id: number) => {
        const position = sequence.indexOf(id);
        return position >= 0 ? `la pregunta ${position + 1}` : `«${questionText(id)}»`;
    };

    const targetsFor = (id: number) =>
        [...sequence, ...branchIds]
            .filter((x) => x !== id)
            .map((x) => {
                const position = sequence.indexOf(x);
                return { id: x, label: position >= 0 ? `${position + 1}. ${questionText(x)}` : `(rama) ${questionText(x)}` };
            });

    // Warn before leaving the page with an unsaved question.
    useEffect(() => {
        if (!dirty) return;
        const removeGuard = router.on('before', (event) => {
            if (event.detail.visit.method === 'get' && !window.confirm(DISCARD_WARNING)) event.preventDefault();
        });
        const onBeforeUnload = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => {
            removeGuard();
            window.removeEventListener('beforeunload', onBeforeUnload);
        };
    }, [dirty]);

    // Bring the card being edited into view.
    const draftId = draft?.question.id;
    useEffect(() => {
        if (draftId !== undefined) requestAnimationFrame(() => scrollIntoViewSoftly(document.getElementById(`q-${draftId}`)));
    }, [draftId]);

    const canLeaveDraft = () => !dirty || window.confirm(DISCARD_WARNING);

    const open = (next: Draft) => {
        if (!canLeaveDraft()) return;
        setServerErrors([]);
        setDraft(next);
    };

    const run = (
        action: (callbacks: { onSuccess: () => void; onError: (e: Record<string, string>) => void; onFinish: () => void }) => void,
        after?: () => void,
    ) => {
        setBusy(true);
        action({
            onSuccess: () => {
                setServerErrors([]);
                after?.();
            },
            onError: (errors) => setServerErrors(Object.values(errors)),
            onFinish: () => setBusy(false),
        });
    };

    const save = () => {
        if (!tree || !draft) return;
        const { question, placement } = draft;
        run(
            (cb) => (placement ? treeApi.create(tree.id, question, placement, cb) : treeApi.update(question, cb)),
            () => setDraft(null),
        );
    };

    // A new question starts in the block of the one it follows or branches from.
    const createAt = (placement: Placement) => {
        const near = placement.insert_after ?? (placement.at_start ? sequence[0] : sequence[sequence.length - 1]);
        open(newDraft(placement, tree?.conditions.find((c) => c.id === near)?.section ?? null));
    };

    const duplicate = (question: ConditionData) => {
        const fresh = newDraft({ insert_after: question.id });
        open({
            ...fresh,
            question: {
                ...question,
                id: fresh.question.id,
                label: `${question.label} (copia)`,
                options: question.options.map((o) => ({ ...o, id: null, next_condition: null })),
            },
        });
    };

    const branchBlocker = (optionIndex: number) => {
        const option = draft?.question.options[optionIndex];
        if (!option?.id || (draft && draft.question.id < 0)) return 'guarde primero esta pregunta';
        if (dirty) return 'guarde primero los cambios';
        return null;
    };

    const card = (current: TreeData, question: ConditionData, number: string | null) => {
        const position = sequence.indexOf(question.id);
        const isEditing = draft?.question.id === question.id;
        return (
            <QuestionCard
                key={question.id}
                question={question}
                number={number}
                refer={refer}
                targets={targetsFor(question.id)}
                arrivals={number === null ? arrivalsTo(current, question.id) : []}
                issues={savedIssues.filter((i) => i.conditionId === question.id)}
                draft={isEditing ? draft : null}
                draftIssues={isEditing ? pendingIssues : []}
                serverErrors={isEditing ? serverErrors : []}
                locked={locked}
                saving={busy && isEditing}
                canMoveUp={position > 0}
                canMoveDown={position >= 0 && position < sequence.length - 1}
                onEdit={() => open(editDraft(question))}
                onChange={(change) => setDraft((d) => (d ? { ...d, question: change(d.question) } : d))}
                onSave={save}
                onCancel={() => canLeaveDraft() && (setDraft(null), setServerErrors([]))}
                onMove={(direction) => run((cb) => treeApi.move(question.id, direction, cb))}
                onRemove={() =>
                    run(
                        (cb) => treeApi.remove(question.id, cb),
                        () => setDraft(null),
                    )
                }
                onDuplicate={number !== null ? () => duplicate(question) : undefined}
                branchBlocker={branchBlocker}
                onCreateBranch={(optionIndex) => {
                    const optionId = draft?.question.options[optionIndex]?.id;
                    if (optionId) open(newDraft({ branch_option_id: optionId }, question.section));
                }}
            />
        );
    };

    // The main order as displayed, with a question being created shown where it will go.
    const placement = draft?.placement;
    const newMain = draft && placement && !placement.branch_option_id ? draft.question : null;
    const newBranch = draft && placement?.branch_option_id ? draft.question : null;
    const displayed: ConditionData[] = tree ? sequence.map((id) => tree.conditions.find((c) => c.id === id)!) : [];
    if (newMain) {
        const at = placement!.at_start ? 0 : placement!.insert_after ? sequence.indexOf(placement!.insert_after) + 1 : displayed.length;
        displayed.splice(at, 0, newMain);
    }

    return (
        <AdminLayout title="Árbol de decisión">
            <div className={cn(adminListShellClass, 'pb-16')}>
                <AdminPageHeader
                    title="Árbol de decisión"
                    description="Las preguntas que responde el cliente al cotizar, en el orden en que las verá. Cada cambio se guarda por separado y se comprueba que el cuestionario siga funcionando."
                >
                    {tree && tree.conditions.length > 0 && (
                        <button type="button" onClick={() => setTesting(true)} className="btn btn-outline btn-sm">
                            <Play aria-hidden />
                            Probar como cliente
                        </button>
                    )}
                </AdminPageHeader>

                {!tree ? (
                    <p className="rounded-[10px] border-2 border-dashed border-linea bg-white p-10 text-center text-pizarra">
                        Cree primero una unidad de negocio para configurar sus preguntas.
                    </p>
                ) : (
                    <>
                        {/* Unit tabs, like the corporate site's mode tabs. The rule is an inset shadow under the
                            tabs, not a border they overlap: overlapping it made the row overflow and show a scrollbar. */}
                        <div
                            role="group"
                            aria-label="Unidad de negocio"
                            className="flex gap-5 overflow-x-auto overflow-y-hidden shadow-[inset_0_-2px_0_theme(colors.linea)] [scrollbar-width:thin] sm:gap-10"
                        >
                            {businessUnits.map((t) => {
                                const active = t.id === tree.id;
                                return (
                                    <button
                                        key={t.id}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => {
                                            if (active || !canLeaveDraft()) return;
                                            setDraft(null);
                                            setSelectedId(t.id);
                                        }}
                                        className={cn(
                                            // Sized to fit the three units on a phone without hiding one past the edge.
                                            'shrink-0 border-b-4 pb-3 text-left font-display text-base font-bold tracking-snug transition-colors sm:text-xl',
                                            active ? 'border-azul text-azul' : 'border-transparent text-pizarra hover:text-tinta',
                                        )}
                                    >
                                        {t.name}
                                        <span className="mt-0.5 block font-sans text-sm font-semibold text-pizarra">
                                            {t.conditions.length === 1 ? '1 pregunta' : `${t.conditions.length} preguntas`}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {savedIssues.some((i) => i.conditionId === null) && (
                            <p
                                role="alert"
                                className="flex items-center gap-2 rounded-control border-2 border-error/40 bg-red-50 p-4 text-base text-error"
                            >
                                <AlertCircle className="h-5 w-5 shrink-0" aria-hidden />
                                {savedIssues.find((i) => i.conditionId === null)!.message}
                            </p>
                        )}

                        {tree.conditions.length === 0 && !newMain ? (
                            <div className="rounded-[10px] border-2 border-dashed border-linea bg-white px-6 py-12 text-center">
                                <p className="font-display text-xl font-bold text-tinta">«{tree.name}» aún no tiene preguntas</p>
                                <p className="mx-auto mt-2 max-w-md text-pizarra">
                                    Sin preguntas, los clientes no pueden cotizar esta unidad. Empiece con la primera o copie las de otra unidad.
                                </p>
                                <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                                    <button type="button" onClick={() => createAt({})} disabled={busy} className="btn btn-primary btn-sm">
                                        <Plus aria-hidden />
                                        Crear la primera pregunta
                                    </button>
                                    {sources.length > 0 && (
                                        <select
                                            value=""
                                            disabled={busy}
                                            onChange={(e) => e.target.value && run((cb) => treeApi.copy(tree.id, Number(e.target.value), cb))}
                                            className="control control-sm w-auto"
                                            aria-label="Copiar preguntas de otra unidad"
                                        >
                                            <option value="">Copiar las preguntas de…</option>
                                            {sources.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.name} ({s.conditions.length})
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <>
                                <section aria-labelledby="orden">
                                    <h2 id="orden" className="subheading text-azul">
                                        Preguntas en orden
                                    </h2>
                                    <p className="mt-1 max-w-measure text-base text-pizarra">
                                        El cliente las responde de arriba abajo. Pulse una pregunta para editarla; las flechas cambian el orden.
                                    </p>
                                    <ol className="mt-4">
                                        <InsertHere onClick={() => createAt({ at_start: true })} disabled={locked} label="Insertar al inicio" />
                                        {displayed.map((question, index) => (
                                            <Fragment key={question.id}>
                                                {question.section && question.section !== displayed[index - 1]?.section && (
                                                    <li className="pb-2 pt-4 first:pt-0">
                                                        <p className="font-display text-sm font-bold text-azul">
                                                            {sectionOf(question.section)?.title}
                                                        </p>
                                                    </li>
                                                )}
                                                {card(tree, question, String(index + 1))}
                                                {question.id > 0 && (displayed[index + 1]?.id ?? 0) > 0 && (
                                                    <InsertHere onClick={() => createAt({ insert_after: question.id })} disabled={locked} />
                                                )}
                                            </Fragment>
                                        ))}
                                    </ol>
                                    <button
                                        type="button"
                                        onClick={() => createAt({})}
                                        disabled={locked}
                                        className="mt-3 flex w-full items-center justify-center gap-2 rounded-[10px] border-2 border-dashed border-linea bg-white py-4 font-display font-bold text-azul transition-colors hover:border-azul disabled:opacity-40"
                                    >
                                        <Plus className="h-5 w-5" aria-hidden />
                                        Añadir pregunta al final
                                    </button>
                                </section>

                                {(branchIds.length > 0 || newBranch) && (
                                    <section aria-labelledby="ramas" className="border-t-2 border-linea pt-8">
                                        <h2 id="ramas" className="subheading text-azul">
                                            Preguntas de rama
                                        </h2>
                                        <p className="mt-1 max-w-measure text-base text-pizarra">
                                            Solo aparecen cuando el cliente elige ciertas opciones. Se crean desde «Al elegirla», en las opciones de
                                            una pregunta.
                                        </p>
                                        <ul className="mt-5 space-y-3">
                                            {branchIds.map((id) => card(tree, tree.conditions.find((c) => c.id === id)!, null))}
                                            {newBranch && card(tree, newBranch, null)}
                                        </ul>
                                    </section>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>

            {tree && <TestRun key={`${tree.id}-${tree.conditions.length}`} tree={tree} open={testing} onOpenChange={setTesting} />}
        </AdminLayout>
    );
}
