import { cn } from '@/lib/utils';
import { FileText, Paperclip, X } from 'lucide-react';
import { useState, type InputHTMLAttributes } from 'react';
import { emptyAnswer, formatNumber, MAX_ANSWER_LENGTH, onlyDigits } from './answers';
import { ACCEPT, formatSize } from './documents';
import type { Answer, FilesAnswer, OptionsAnswer, PublicCondition, RangeAnswer } from './types';

type ConditionFieldProps = {
    id: number;
    condition: PublicCondition;
    answer: Answer | undefined;
    onChange: (answer: Answer) => void;
    error: string | null;
};

/** Id of the element a field's error links to (the summary focuses it). */
export const fieldAnchor = (id: number) => `field-${id}`;

/**
 * One question of the encuadre, built like the corporate site's UiField: the
 * label always visible with an explicit "(opcional)", the control, then the
 * help and the error, both wired with aria-describedby.
 */
export function ConditionField({ id, condition, answer, onChange, error }: ConditionFieldProps) {
    const anchor = fieldAnchor(id);
    const value = answer ?? emptyAnswer(condition);
    // Open text answers show how many characters are left, on the same line as the help.
    const counted = condition.interaction_type === 'input' && condition.type === 'text';
    const describedBy =
        [condition.observation && `${anchor}-help`, counted && `${anchor}-count`, error && `${anchor}-error`].filter(Boolean).join(' ') || undefined;
    const aria = { 'aria-describedby': describedBy, 'aria-invalid': error ? true : undefined };
    const isGroup = condition.interaction_type !== 'input';

    const label = (
        <>
            {condition.label} {!condition.required && <span className="font-normal text-pizarra">(opcional)</span>}
        </>
    );

    return (
        <div id={anchor} className="grid gap-2">
            {isGroup ? (
                <p id={`${anchor}-label`} className="font-bold">
                    {label}
                </p>
            ) : (
                <label htmlFor={`${anchor}-input`} className="font-bold">
                    {label}
                </label>
            )}

            <Control anchor={anchor} condition={condition} value={value} onChange={onChange} aria={aria} invalid={!!error} />

            {(condition.observation || counted) && (
                <div className="flex items-start justify-between gap-x-4">
                    {condition.observation && (
                        <p id={`${anchor}-help`} className="max-w-measure text-sm text-pizarra">
                            {condition.observation}
                        </p>
                    )}
                    {counted && <CharCount id={`${anchor}-count`} length={(value as string).length} className="ml-auto shrink-0" />}
                </div>
            )}
            {error && (
                <p id={`${anchor}-error`} className="text-sm font-bold text-error">
                    {error}
                </p>
            )}
        </div>
    );
}

type ControlProps = {
    anchor: string;
    condition: PublicCondition;
    value: Answer;
    onChange: (answer: Answer) => void;
    aria: { 'aria-describedby'?: string; 'aria-invalid'?: boolean };
    invalid: boolean;
};

function Control({ anchor, condition, value, onChange, aria, invalid }: ControlProps) {
    const placeholder = condition.placeholder ?? undefined;

    if (condition.interaction_type === 'input') {
        if (condition.type === 'file') {
            return <FilesControl anchor={anchor} files={value as FilesAnswer} onChange={onChange} aria={aria} invalid={invalid} />;
        }
        if (condition.type === 'text' && condition.multiline) {
            return (
                <textarea
                    id={`${anchor}-input`}
                    value={value as string}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    rows={5}
                    maxLength={MAX_ANSWER_LENGTH}
                    className="control resize-y"
                    {...aria}
                />
            );
        }
        if (condition.type === 'number') {
            return (
                <NumberInput
                    id={`${anchor}-input`}
                    value={value as string}
                    onChange={onChange}
                    placeholder={placeholder}
                    className="control sm:max-w-xs"
                    {...aria}
                />
            );
        }
        const isText = condition.type === 'text';
        const input = (
            <input
                id={`${anchor}-input`}
                type={condition.type}
                value={value as string}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                maxLength={isText ? MAX_ANSWER_LENGTH : undefined}
                className={cn('control', !isText && 'sm:max-w-xs')}
                {...aria}
            />
        );
        return condition.type === 'date' ? (
            <div className="grid gap-1.5">
                {input}
                <DateInWords value={value as string} />
            </div>
        ) : (
            input
        );
    }

    if (condition.interaction_type === 'range') {
        const range = value as RangeAnswer;
        return (
            <div role="group" aria-labelledby={`${anchor}-label`} className="grid grid-cols-1 gap-3 sm:max-w-lg sm:grid-cols-2">
                {(['min', 'max'] as const).map((bound) => (
                    <label key={bound} className="grid gap-1">
                        <span className="text-sm text-pizarra">{bound === 'min' ? 'Desde' : 'Hasta'}</span>
                        {condition.type === 'number' ? (
                            <NumberInput
                                value={range[bound]}
                                onChange={(digits) => onChange({ ...range, [bound]: digits })}
                                className="control"
                                {...aria}
                            />
                        ) : (
                            <>
                                <input
                                    type={condition.type}
                                    value={range[bound]}
                                    onChange={(e) => onChange({ ...range, [bound]: e.target.value })}
                                    className="control"
                                    {...aria}
                                />
                                {condition.type === 'date' && <DateInWords value={range[bound]} />}
                            </>
                        )}
                    </label>
                ))}
            </div>
        );
    }

    const options = value as OptionsAnswer;
    const toggle = (index: number) => {
        const isOn = options.selected.includes(index);
        const selected = condition.multiple ? (isOn ? options.selected.filter((i) => i !== index) : [...options.selected, index]) : [index];
        onChange({ ...options, selected });
    };
    const otherChosen = options.selected.some((i) => condition.options[i]?.is_other);

    // Bordered options, as the contact form's "¿Qué necesita?". A few short ones (Sí / No,
    // Presencial / Virtual / Mixto) share the row and wrap only when it is too narrow;
    // the rest go two per row (three when there are exactly three).
    const inline = condition.options.length <= 3 && condition.options.every((o) => o.label.length <= 12);
    const layout = inline ? 'flex flex-wrap' : cn('grid', condition.options.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2');

    return (
        <div role={condition.multiple ? 'group' : 'radiogroup'} aria-labelledby={`${anchor}-label`} {...aria}>
            <div className={cn('gap-2', layout)}>
                {condition.options.map((option, index) => (
                    <label
                        key={index}
                        className={cn(
                            'choice has-[:focus-visible]:outline has-[:focus-visible]:outline-[3px] has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-azul-vivo',
                            inline && 'w-auto flex-[1_1_0] whitespace-nowrap max-sm:gap-2 max-sm:px-3',
                            invalid && !options.selected.length && 'border-error',
                        )}
                    >
                        <input
                            type={condition.multiple ? 'checkbox' : 'radio'}
                            name={anchor}
                            checked={options.selected.includes(index)}
                            onChange={() => toggle(index)}
                            className={cn(
                                'h-5 w-5 shrink-0 border-2 border-gris-marca text-azul focus:ring-0 focus:ring-offset-0',
                                condition.multiple && 'rounded',
                            )}
                        />
                        <span>{option.label}</span>
                    </label>
                ))}
            </div>
            {otherChosen && (
                <div className="mt-2 grid gap-1">
                    <input
                        value={options.other}
                        onChange={(e) => onChange({ ...options, other: e.target.value })}
                        className="control"
                        placeholder="Especifique"
                        maxLength={MAX_ANSWER_LENGTH}
                        aria-label={`${condition.label}: especifique`}
                        aria-describedby={`${anchor}-other-count`}
                    />
                    <CharCount id={`${anchor}-other-count`} length={options.other.length} />
                </div>
            )}
        </div>
    );
}

/**
 * "120 / 5.000 caracteres", so the person sees how much room is left. It is not a
 * live region: announcing every keystroke would be noise; screen readers read it
 * with the field (aria-describedby).
 */
function CharCount({ id, length, className }: { id: string; length: number; className?: string }) {
    const atLimit = length >= MAX_ANSWER_LENGTH;
    const format = (n: number) => n.toLocaleString('es-CO');

    return (
        <p id={id} className={cn('text-right text-sm tabular-nums', atLimit ? 'font-bold text-error' : 'text-pizarra', className)}>
            {format(length)} / {format(MAX_ANSWER_LENGTH)} caracteres
            {atLimit && ' · límite alcanzado'}
        </p>
    );
}

/**
 * The chosen date written out ("viernes, 20 de noviembre de 2026"): the browser draws the
 * field in its own format (mm/dd/yyyy in an English one), which can be read the wrong way.
 */
function DateInWords({ value }: { value: string }) {
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : null;
    return (
        <p className="min-h-5 text-sm font-semibold text-azul" aria-live="polite">
            {date && !Number.isNaN(date.getTime())
                ? date.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                : null}
        </p>
    );
}

type NumberInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'type'> & {
    value: string;
    onChange: (digits: string) => void;
};

/**
 * A whole number typed as people write it ("15.000.000", "$ 15 000 000"):
 * only the digits are kept, and they read grouped once the field is left.
 * A number input would drop such a value and report the field as empty.
 */
function NumberInput({ value, onChange, ...props }: NumberInputProps) {
    const [editing, setEditing] = useState(false);
    const digits = onlyDigits(value);

    return (
        <input
            {...props}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            value={editing ? digits : formatNumber(digits)}
            onFocus={() => setEditing(true)}
            onBlur={() => setEditing(false)}
            onChange={(e) => onChange(onlyDigits(e.target.value))}
        />
    );
}

type FilesControlProps = Pick<ControlProps, 'anchor' | 'onChange' | 'aria' | 'invalid'> & { files: FilesAnswer };

/**
 * The documents of the request: each pick adds to the list (choosing again
 * does not drop what was already attached) and every file can be removed. The
 * native input stays for keyboards and screen readers but is drawn as a
 * button, since its own "Ningún archivo seleccionado" contradicts the list.
 */
function FilesControl({ anchor, files, onChange, aria, invalid }: FilesControlProps) {
    const add = (picked: FileList | null) => {
        const isNew = (file: File) => !files.some((f) => f.name === file.name && f.size === file.size);
        onChange([...files, ...Array.from(picked ?? []).filter(isNew)]);
    };

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                <input
                    id={`${anchor}-input`}
                    type="file"
                    multiple
                    accept={ACCEPT}
                    onChange={(e) => {
                        add(e.target.files);
                        e.target.value = ''; // so the same file can be picked again after removing it
                    }}
                    className="peer sr-only"
                    {...aria}
                />
                <label
                    htmlFor={`${anchor}-input`}
                    className={cn(
                        'btn btn-outline cursor-pointer peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-azul-vivo',
                        invalid && 'border-error',
                    )}
                >
                    <Paperclip className="h-5 w-5" aria-hidden />
                    {files.length > 0 ? 'Adjuntar más documentos' : 'Seleccionar documentos'}
                </label>
                <span className="text-sm font-semibold text-pizarra" aria-live="polite">
                    {files.length === 0
                        ? 'Ningún documento adjunto'
                        : files.length === 1
                          ? '1 documento adjunto'
                          : `${files.length} documentos adjuntos`}
                </span>
            </div>
            {files.length > 0 && (
                <ul className="grid gap-2" aria-label="Documentos adjuntos">
                    {files.map((file, index) => (
                        <li key={`${file.name}-${file.size}`} className="flex items-center gap-3 rounded-control border-2 border-linea px-4 py-2">
                            <FileText className="h-5 w-5 shrink-0 text-azul" aria-hidden />
                            <span className="min-w-0 flex-1 truncate">{file.name}</span>
                            <span className="shrink-0 text-sm tabular-nums text-pizarra">{formatSize(file.size)}</span>
                            <button
                                type="button"
                                onClick={() => onChange(files.filter((_, i) => i !== index))}
                                className="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-pizarra hover:bg-nube hover:text-error"
                                aria-label={`Quitar ${file.name}`}
                            >
                                <X className="h-4 w-4" aria-hidden />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <p className="text-sm text-pizarra">
                Los documentos no se guardan en este navegador; si sale de la página, deberá adjuntarlos nuevamente.
            </p>
        </div>
    );
}
