import { SECTIONS, sectionOf, type SectionKey } from '@/features/encuadre/sections';
import { cn } from '@/lib/utils';
import { AlertCircle, ArrowDown, ArrowUp, Copy, CornerDownRight, Loader2, Pencil, Trash2 } from 'lucide-react';
import { useEffect, useId, useRef } from 'react';
import { AnswerKindPicker } from './answer-kind-picker';
import { isDirty, type Draft } from './draft';
import { labelOf, type Arrival, type Issue } from './graph';
import { OptionsEditor } from './options-editor';
import { canBranch, isChoice, kindLabel, kindOf, withKind, type ConditionData } from './types';

export type QuestionCardProps = {
    /** Saved question (or the draft itself when it is being created). */
    question: ConditionData;
    /** "1", "2"… on the main order; null for branch questions. */
    number: string | null;
    /** How to name any question in sentences: "la pregunta 3" / "«¿…?»". */
    refer: (id: number) => string;
    /** Questions it may lead to (all others, in display order). */
    targets: { id: number; label: string }[];
    arrivals: Arrival[];
    /** Problems of the saved question. */
    issues: Issue[];
    /** Set while this card is being edited. */
    draft: Draft | null;
    draftIssues: Issue[];
    serverErrors: string[];
    /** Another card is being edited or a request is running. */
    locked: boolean;
    saving: boolean;
    canMoveUp: boolean;
    canMoveDown: boolean;
    onEdit: () => void;
    onChange: (change: (question: ConditionData) => ConditionData) => void;
    onSave: () => void;
    onCancel: () => void;
    onMove: (direction: 'up' | 'down') => void;
    onRemove: () => void;
    onDuplicate?: () => void;
    /** Why a branch can't be created from that option yet, or null if it can. */
    branchBlocker: (optionIndex: number) => string | null;
    onCreateBranch: (optionIndex: number) => void;
};

const tag = 'rounded-full border border-linea bg-nube px-2 py-0.5 text-xs font-semibold text-pizarra';

const iconButton =
    'inline-flex h-10 w-10 items-center justify-center rounded-control text-pizarra transition-colors hover:bg-nube hover:text-tinta disabled:pointer-events-none disabled:opacity-30';

function Summary({ question, number, refer, arrivals }: Pick<QuestionCardProps, 'question' | 'number' | 'refer' | 'arrivals'>) {
    const isBranch = number === null;
    const branches = canBranch(question) ? question.options.filter((o) => o.next_condition !== null) : [];
    const after = question.next_condition === null ? 'termina el cuestionario' : `va a ${refer(question.next_condition)}`;

    return (
        <>
            <span
                className={cn(
                    'block font-display text-base font-bold leading-snug sm:text-lg',
                    question.label.trim() ? 'text-tinta' : 'italic text-pizarra',
                )}
            >
                {question.label.trim() || 'Nueva pregunta'}
            </span>
            <span className="mt-1 block text-sm text-pizarra">
                {kindLabel(question)}
                {isChoice(question) && question.options.length > 0 && <>: {question.options.map((o) => o.label || '…').join(', ')}</>}
            </span>
            {(!question.required || (isBranch && question.section)) && (
                <span className="mt-2 flex flex-wrap gap-1.5">
                    {isBranch && question.section && <span className={tag}>{sectionOf(question.section)?.title}</span>}
                    {!question.required && <span className={tag}>Opcional</span>}
                </span>
            )}
            <span className="mt-2 block space-y-0.5 text-sm text-pizarra">
                {isBranch && arrivals.length > 0 && (
                    <span className="block">
                        Aparece{' '}
                        {arrivals
                            .map((a) => (a.option ? `al elegir «${a.option}» en ${refer(a.fromId)}` : `después de ${refer(a.fromId)}`))
                            .join(' o ')}
                        .
                    </span>
                )}
                {branches.map((o, i) => (
                    <span key={i} className="flex items-center gap-1">
                        <CornerDownRight className="h-3.5 w-3.5 shrink-0 text-azul-vivo" aria-hidden />
                        Si elige «{o.label}» va a {refer(o.next_condition!)}
                    </span>
                ))}
                {(isBranch || branches.length > 0) && (
                    <span className="flex items-center gap-1">
                        <CornerDownRight className="h-3.5 w-3.5 shrink-0 text-azul-vivo" aria-hidden />
                        {branches.length > 0 ? 'Con las demás opciones' : 'Después'} {after}
                    </span>
                )}
            </span>
        </>
    );
}

export function QuestionCard(props: QuestionCardProps) {
    const { question, number, refer, arrivals, issues, draft, locked, saving } = props;
    const uid = useId();
    const isBranch = number === null;
    const isNew = question.id < 0;
    const unused = isBranch && !isNew && arrivals.length === 0;
    const editing = draft !== null;
    const labelRef = useRef<HTMLInputElement>(null);
    const editRef = useRef<HTMLButtonElement>(null);
    const wasEditing = useRef(false);

    // Opening the editor moves the focus to the question; closing it (saved or cancelled) brings it back
    // to the card's "Editar" button, instead of losing it when the editor goes away.
    useEffect(() => {
        if (editing && !wasEditing.current) labelRef.current?.focus();
        if (!editing && wasEditing.current) editRef.current?.focus({ preventScroll: true });
        wasEditing.current = editing;
    }, [editing]);
    const q = draft?.question ?? question;
    const dirty = draft ? isDirty(draft) || isNew : false;
    const blocking = [...props.draftIssues.map((i) => i.message), ...props.serverErrors];
    const defaultLabel = q.next_condition === null ? 'termina el cuestionario' : `va a ${refer(q.next_condition)}`;

    return (
        <li
            id={`q-${question.id}`}
            className={cn(
                'scroll-mt-28 rounded-[10px] border-2 bg-white transition-colors',
                editing ? 'border-azul' : issues.length > 0 ? 'border-error/60' : unused ? 'border-amber-400' : 'border-linea',
            )}
        >
            <div className="flex items-start gap-3 p-4 sm:p-5">
                <span
                    className={cn(
                        'mt-0.5 flex h-8 min-w-8 shrink-0 items-center justify-center rounded-full font-display text-sm font-extrabold tabular-nums',
                        isBranch ? 'border-2 border-dashed border-azul-vivo text-azul' : 'bg-azul text-white',
                    )}
                    aria-hidden
                >
                    {number ?? <CornerDownRight className="h-4 w-4" />}
                </span>

                <div className="min-w-0 flex-1">
                    {editing ? (
                        <p className="font-display text-lg font-bold text-azul">
                            {isNew ? (isBranch ? 'Nueva pregunta de rama' : 'Nueva pregunta') : 'Editando pregunta'}
                        </p>
                    ) : (
                        <button type="button" onClick={props.onEdit} disabled={locked} className="block w-full text-left disabled:cursor-default">
                            <Summary question={question} number={number} refer={refer} arrivals={arrivals} />
                        </button>
                    )}
                    {!editing &&
                        issues.map((issue) => (
                            <span key={issue.message} className="mt-2 flex items-center gap-1.5 text-sm font-bold text-error">
                                <AlertCircle className="h-4 w-4 shrink-0" aria-hidden />
                                {issue.message}
                            </span>
                        ))}
                    {!editing && unused && (
                        <span className="mt-2 block text-sm font-bold text-amber-800">Ninguna opción lleva aquí: el cliente no la verá.</span>
                    )}
                </div>

                {/* Stacked on a phone, so the question keeps the width of the card. */}
                {!editing && (
                    <div className="-my-1 flex shrink-0 flex-col items-center sm:my-0 sm:flex-row">
                        {!isBranch && (
                            <>
                                <button
                                    type="button"
                                    className={iconButton}
                                    onClick={() => props.onMove('up')}
                                    disabled={locked || !props.canMoveUp}
                                    aria-label={`Subir «${labelOf(question)}»`}
                                    title="Subir"
                                >
                                    <ArrowUp className="h-4 w-4" aria-hidden />
                                </button>
                                <button
                                    type="button"
                                    className={iconButton}
                                    onClick={() => props.onMove('down')}
                                    disabled={locked || !props.canMoveDown}
                                    aria-label={`Bajar «${labelOf(question)}»`}
                                    title="Bajar"
                                >
                                    <ArrowDown className="h-4 w-4" aria-hidden />
                                </button>
                            </>
                        )}
                        <button
                            ref={editRef}
                            type="button"
                            className={iconButton}
                            onClick={props.onEdit}
                            disabled={locked}
                            aria-label={`Editar «${labelOf(question)}»`}
                            title="Editar"
                        >
                            <Pencil className="h-4 w-4" aria-hidden />
                        </button>
                    </div>
                )}
            </div>

            {draft && (
                <form
                    className="space-y-6 border-t-2 border-linea p-4 sm:p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        props.onSave();
                    }}
                    noValidate
                >
                    <div>
                        <label htmlFor={`${uid}-label`} className="label">
                            Pregunta
                        </label>
                        <input
                            id={`${uid}-label`}
                            value={q.label}
                            onChange={(e) => props.onChange((d) => ({ ...d, label: e.target.value }))}
                            placeholder="Ej.: ¿Cuántos empleados tiene la empresa?"
                            className="control font-semibold"
                            ref={labelRef}
                        />
                    </div>

                    <div>
                        <label htmlFor={`${uid}-help`} className="label">
                            Ayuda para el cliente <span className="label-optional">(opcional)</span>
                        </label>
                        <input
                            id={`${uid}-help`}
                            value={q.observation ?? ''}
                            onChange={(e) => props.onChange((d) => ({ ...d, observation: e.target.value }))}
                            placeholder="Texto pequeño que aparece debajo de la pregunta"
                            className="control"
                        />
                    </div>

                    <div>
                        <label htmlFor={`${uid}-section`} className="label">
                            Bloque del formulario
                        </label>
                        <select
                            id={`${uid}-section`}
                            value={q.section ?? ''}
                            onChange={(e) => props.onChange((d) => ({ ...d, section: (e.target.value || null) as SectionKey | null }))}
                            className="control"
                        >
                            <option value="">Sin bloque</option>
                            {SECTIONS.map((s, i) => (
                                <option key={s.key} value={s.key}>
                                    {i + 1}. {s.title}
                                </option>
                            ))}
                        </select>
                    </div>

                    <label className="flex cursor-pointer items-start gap-3 text-base">
                        <input
                            type="checkbox"
                            checked={!q.required}
                            onChange={(e) => props.onChange((d) => ({ ...d, required: !e.target.checked }))}
                            className="mt-1 h-5 w-5 shrink-0 rounded border-2 border-gris-marca text-azul focus:ring-0 focus:ring-offset-0"
                        />
                        <span>
                            Opcional
                            <span className="block text-sm text-pizarra">El cliente puede dejarla en blanco y seguir.</span>
                        </span>
                    </label>

                    <AnswerKindPicker name={`${uid}-kind`} value={kindOf(q)} onChange={(kind) => props.onChange((d) => withKind(d, kind))} />

                    {/* A file picker has no text to show inside. */}
                    {q.interaction_type === 'input' && q.type !== 'file' && (
                        <div>
                            <label htmlFor={`${uid}-placeholder`} className="label">
                                Ejemplo dentro del campo <span className="label-optional">(opcional)</span>
                            </label>
                            <input
                                id={`${uid}-placeholder`}
                                value={q.placeholder ?? ''}
                                onChange={(e) => props.onChange((d) => ({ ...d, placeholder: e.target.value }))}
                                placeholder="Ej.: ICONTEC, Bureau Veritas, SGS"
                                className="control"
                            />
                            <p className="hint mt-2">Texto gris que muestra al cliente qué se espera que escriba; desaparece al escribir.</p>
                        </div>
                    )}

                    {isChoice(q) && (
                        <OptionsEditor
                            question={q}
                            targets={props.targets}
                            defaultLabel={defaultLabel}
                            onChange={(options) => props.onChange((d) => ({ ...d, options }))}
                            branchBlocker={props.branchBlocker}
                            onCreateBranch={props.onCreateBranch}
                        />
                    )}

                    {isBranch && !draft.placement?.branch_option_id && (
                        <div>
                            <label htmlFor={`${uid}-next`} className="label">
                                Después de esta pregunta
                            </label>
                            <select
                                id={`${uid}-next`}
                                value={q.next_condition ?? ''}
                                onChange={(e) => props.onChange((d) => ({ ...d, next_condition: e.target.value ? Number(e.target.value) : null }))}
                                className="control"
                            >
                                <option value="">Termina el cuestionario</option>
                                {props.targets.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        Continúa en: {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    {blocking.length > 0 && (
                        <ul role="alert" className="space-y-1 rounded-control border-2 border-error/40 bg-red-50 px-4 py-3 text-base text-error">
                            {blocking.map((message) => (
                                <li key={message} className="flex items-start gap-2">
                                    <AlertCircle className="mt-1 h-4 w-4 shrink-0" aria-hidden />
                                    {message}
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="flex flex-col-reverse gap-2 border-t border-linea pt-5 sm:flex-row sm:items-center">
                        {!isNew && (
                            <>
                                <button
                                    type="button"
                                    disabled={saving}
                                    onClick={() =>
                                        window.confirm(`¿Eliminar «${labelOf(question)}»? Las preguntas siguientes se mantienen conectadas.`) &&
                                        props.onRemove()
                                    }
                                    className="btn btn-sm border-transparent text-error hover:bg-red-50"
                                >
                                    <Trash2 aria-hidden />
                                    Eliminar
                                </button>
                                {props.onDuplicate && (
                                    <button
                                        type="button"
                                        disabled={saving || dirty}
                                        onClick={props.onDuplicate}
                                        className="btn btn-sm border-transparent text-azul hover:bg-nube"
                                        title={dirty ? 'Guarde o cancele los cambios primero' : undefined}
                                    >
                                        <Copy aria-hidden />
                                        Duplicar
                                    </button>
                                )}
                            </>
                        )}
                        <span className="sm:ml-auto" />
                        <button type="button" onClick={props.onCancel} disabled={saving} className="btn btn-outline btn-sm">
                            Cancelar
                        </button>
                        <button type="submit" disabled={saving || !dirty || props.draftIssues.length > 0} className="btn btn-primary btn-sm">
                            {saving && <Loader2 className="animate-spin" aria-hidden />}
                            {saving ? 'Guardando…' : isNew ? 'Crear pregunta' : 'Guardar pregunta'}
                        </button>
                    </div>
                </form>
            )}
        </li>
    );
}
