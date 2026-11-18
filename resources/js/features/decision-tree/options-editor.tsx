import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { blankOption, canBranch, type ConditionData, type OptionData } from './types';

const NEW_BRANCH = '__new';

type OptionsEditorProps = {
    question: ConditionData;
    /** Other questions of the unit, in display order, to branch to. */
    targets: { id: number; label: string }[];
    /** Name of where the question goes by default ("la pregunta 3" / "el final"). */
    defaultLabel: string;
    onChange: (options: OptionData[]) => void;
    /** Why a branch can't be created from that option yet, or null if it can. */
    branchBlocker: (optionIndex: number) => string | null;
    onCreateBranch: (optionIndex: number) => void;
};

const iconButton =
    'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-control text-pizarra transition-colors hover:bg-nube hover:text-tinta disabled:opacity-30';

export function OptionsEditor({ question, targets, defaultLabel, onChange, branchBlocker, onCreateBranch }: OptionsEditorProps) {
    const { options } = question;
    const branching = canBranch(question);
    const inputs = useRef<(HTMLInputElement | null)[]>([]);
    const focusIndex = useRef<number | null>(null);
    const regular = options.map((option, index) => ({ option, index })).filter(({ option }) => !option.is_other);
    const other = options.findIndex((o) => o.is_other);
    const otherOption = options[other];

    // Focus the option just created with Enter / "Añadir opción".
    useEffect(() => {
        if (focusIndex.current !== null) {
            inputs.current[focusIndex.current]?.focus();
            focusIndex.current = null;
        }
    });

    const set = (index: number, patch: Partial<OptionData>) => onChange(options.map((o, i) => (i === index ? { ...o, ...patch } : o)));

    /** New regular options go before "Otro", which always stays last. */
    const addAfter = (index: number) => {
        const at = Math.min(index + 1, other >= 0 ? other : options.length);
        onChange([...options.slice(0, at), blankOption(), ...options.slice(at)]);
        focusIndex.current = at;
    };

    const move = (index: number, delta: -1 | 1) => {
        const to = index + delta;
        const [from, target] = [options[index], options[to]];
        if (!from || !target || target.is_other) return;
        const copy = [...options];
        copy[index] = target;
        copy[to] = from;
        onChange(copy);
    };

    const toggleOther = (enabled: boolean) =>
        onChange(enabled ? [...options, { ...blankOption(), label: 'Otro', is_other: true }] : options.filter((o) => !o.is_other));

    const branchSelect = (option: OptionData, index: number) => (
        <select
            value={option.next_condition ?? ''}
            onChange={(e) =>
                e.target.value === NEW_BRANCH ? onCreateBranch(index) : set(index, { next_condition: e.target.value ? Number(e.target.value) : null })
            }
            className="control control-sm"
            aria-label={`Qué pasa al elegir «${option.label || `opción ${index + 1}`}»`}
        >
            <option value="">Sigue normal ({defaultLabel})</option>
            {targets.map((t) => (
                <option key={t.id} value={t.id}>
                    Ir a: {t.label}
                </option>
            ))}
            <option value={NEW_BRANCH} disabled={branchBlocker(index) !== null}>
                + Crear una pregunta solo para esta opción{branchBlocker(index) ? ` (${branchBlocker(index)})` : ''}
            </option>
        </select>
    );

    return (
        <fieldset>
            <legend className="label">Opciones</legend>
            <p className="hint mb-3">
                Pulse Enter para añadir la siguiente.
                {branching && ' En «Al elegirla» puede enviar al cliente a otra pregunta.'}
            </p>

            <ol className="space-y-2">
                {regular.map(({ option, index }, position) => (
                    <li key={index} className="rounded-control border-2 border-linea bg-white p-2">
                        <div className="flex items-center gap-1">
                            <span className="w-6 shrink-0 text-center text-sm font-bold tabular-nums text-pizarra" aria-hidden>
                                {position + 1}
                            </span>
                            <input
                                ref={(el) => {
                                    inputs.current[index] = el;
                                }}
                                value={option.label}
                                onChange={(e) => set(index, { label: e.target.value })}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addAfter(index);
                                    } else if (e.key === 'Backspace' && option.label === '' && regular.length > 1) {
                                        e.preventDefault();
                                        onChange(options.filter((_, i) => i !== index));
                                        focusIndex.current = Math.max(0, regular[position - 1]?.index ?? 0);
                                    }
                                }}
                                placeholder={`Opción ${position + 1}`}
                                aria-label={`Texto de la opción ${position + 1}`}
                                aria-invalid={option.label.trim() === '' ? true : undefined}
                                className="control control-sm min-w-0 flex-1"
                            />
                            <button
                                type="button"
                                className={iconButton}
                                onClick={() => move(index, -1)}
                                disabled={position === 0}
                                aria-label="Subir opción"
                            >
                                <ArrowUp className="h-4 w-4" aria-hidden />
                            </button>
                            <button
                                type="button"
                                className={iconButton}
                                onClick={() => move(index, 1)}
                                disabled={position === regular.length - 1}
                                aria-label="Bajar opción"
                            >
                                <ArrowDown className="h-4 w-4" aria-hidden />
                            </button>
                            <button
                                type="button"
                                className={`${iconButton} hover:text-error`}
                                onClick={() => onChange(options.filter((_, i) => i !== index))}
                                disabled={regular.length === 1}
                                aria-label={`Quitar opción ${position + 1}`}
                            >
                                <Trash2 className="h-4 w-4" aria-hidden />
                            </button>
                        </div>
                        {branching && (
                            <label className="mt-2 flex flex-col gap-1 pl-7 text-sm text-pizarra sm:flex-row sm:items-center sm:gap-3">
                                <span className="shrink-0 font-semibold">Al elegirla</span>
                                {branchSelect(option, index)}
                            </label>
                        )}
                    </li>
                ))}
            </ol>

            <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" onClick={() => addAfter(regular[regular.length - 1]?.index ?? -1)} className="btn btn-outline btn-sm">
                    Añadir opción
                </button>
                <label className="flex cursor-pointer items-center gap-2 text-base">
                    <input
                        type="checkbox"
                        checked={other >= 0}
                        onChange={(e) => toggleOther(e.target.checked)}
                        className="h-5 w-5 rounded border-2 border-gris-marca text-azul focus:ring-0 focus:ring-offset-0"
                    />
                    Incluir «Otro» para que escriba su respuesta
                </label>
            </div>

            {otherOption && branching && (
                <label className="mt-2 flex flex-col gap-1 rounded-control border-2 border-dashed border-linea p-2 pl-9 text-sm text-pizarra sm:flex-row sm:items-center sm:gap-3">
                    <span className="shrink-0 font-semibold">Al elegir «Otro»</span>
                    {branchSelect(otherOption, other)}
                </label>
            )}
        </fieldset>
    );
}
