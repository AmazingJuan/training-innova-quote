import { cn } from '@/lib/utils';
import { useEffect, useRef, useState, type ReactNode, type Ref } from 'react';
import type { VisibleField } from './answers';
import { ConditionField } from './condition-field';
import { sectionKey, sectionOf } from './sections';
import type { Answer, PublicCondition } from './types';

export type EncuadreBlock = { key: string; title: string; short: string; hint: string; fields: VisibleField[] };

/** Visible fields grouped in consecutive blocks of the encuadre. */
export function encuadreBlocks(conditions: Record<string, PublicCondition>, fields: VisibleField[]): EncuadreBlock[] {
    const blocks: EncuadreBlock[] = [];
    for (const field of fields) {
        const key = sectionKey(conditions[field.id].section) ?? 'otras';
        const last = blocks[blocks.length - 1];
        if (last?.key === key) {
            last.fields.push(field);
        } else {
            const section = sectionOf(key);
            blocks.push({
                key,
                title: section?.title ?? 'Otras preguntas',
                short: section?.short ?? 'Otras',
                hint: section?.hint ?? '',
                fields: [field],
            });
        }
    }
    return blocks;
}

/** A plain number box (e.g. "Número de empleados fijos"): short, so it can share its row with another one. */
const isCompact = (condition: PublicCondition) => condition.interaction_type === 'input' && condition.type === 'number';

/**
 * The fields as rows: consecutive number boxes that are not opened by an answer
 * go side by side (two per row on wide screens, stacked on narrow ones); every
 * other field takes its own row.
 */
function rowsOf(conditions: Record<string, PublicCondition>, fields: VisibleField[]): VisibleField[][] {
    const inRow = (field: VisibleField) => field.depth === 0 && isCompact(conditions[field.id]);
    const rows: VisibleField[][] = [];
    for (const field of fields) {
        const last = rows[rows.length - 1];
        if (last && inRow(field) && last.every(inRow)) last.push(field);
        else rows.push([field]);
    }
    return rows;
}

/** One screen of the form: a block, or a part of a long one. */
export type FormPage = EncuadreBlock & { part: number; parts: number };

/** How much room a question takes on screen (long text counts double). */
const weightOf = (condition: PublicCondition) => (condition.interaction_type === 'input' && condition.multiline ? 2 : 1);

/**
 * Split a long block into parts that fit on screen. A question and the fields
 * it opens always stay together, and only the questions themselves are
 * weighed, so answering never moves a field to another part.
 */
export function paginate(conditions: Record<string, PublicCondition>, block: EncuadreBlock, maxWeight = 4): FormPage[] {
    const groups: VisibleField[][] = [];
    // A row of number boxes counts as one group, so a pair is never split across two screens.
    for (const row of rowsOf(conditions, block.fields)) {
        if (row[0].depth === 0 || groups.length === 0) groups.push([...row]);
        else groups[groups.length - 1].push(...row);
    }

    const parts: VisibleField[][] = [];
    let weight = 0;
    for (const group of groups) {
        const w = weightOf(conditions[group[0].id]);
        if (parts.length === 0 || weight + w > maxWeight) {
            parts.push([]);
            weight = 0;
        }
        parts[parts.length - 1].push(...group);
        weight += w;
    }

    return parts.map((fields, index) => ({ ...block, fields, part: index + 1, parts: parts.length }));
}

type FieldsProps = {
    conditions: Record<string, PublicCondition>;
    answers: Record<number, Answer>;
    onAnswer: (id: number, answer: Answer) => void;
    /** Errors by question id (only shown after trying to go on). */
    errors: Record<number, string>;
};

/** The fields of one block; the ones opened by an answer go indented right under it, and unfold instead of popping in. */
export function BlockFields({ fields, conditions, answers, onAnswer, errors }: FieldsProps & { fields: VisibleField[] }) {
    const shown = useRef<Set<number> | null>(null);
    const [opening, setOpening] = useState<Set<number>>(new Set());

    // Fields that appear on the same screen were opened by an answer (not by moving to another screen).
    useEffect(() => {
        const ids = new Set(fields.map((f) => f.id));
        if (shown.current?.has(fields[0]?.id)) {
            const opened = fields.filter((f) => f.depth > 0 && !shown.current?.has(f.id)).map((f) => f.id);
            if (opened.length > 0) setOpening((current) => new Set([...current, ...opened]));
        }
        shown.current = ids;
    }, [fields]);

    const renderField = ({ id, depth }: VisibleField) => (
        <div
            key={id}
            className={cn(depth > 0 && 'border-l-2 border-linea pl-4 sm:pl-6', opening.has(id) && 'unfold')}
            style={{ marginLeft: depth > 1 ? `${(depth - 1) * 1.5}rem` : undefined }}
            onAnimationEnd={(event) => {
                if (event.target !== event.currentTarget) return;
                setOpening((current) => {
                    const next = new Set(current);
                    next.delete(id);
                    return next;
                });
            }}
        >
            <div className={cn(opening.has(id) && 'min-h-0 overflow-hidden')}>
                <ConditionField
                    id={id}
                    condition={conditions[id]}
                    answer={answers[id]}
                    onChange={(answer) => onAnswer(id, answer)}
                    error={errors[id] ?? null}
                />
            </div>
        </div>
    );

    return (
        <>
            {rowsOf(conditions, fields).map((row) =>
                row.length > 1 ? (
                    <div key={row[0].id} className="grid gap-7 sm:grid-cols-2">
                        {row.map(renderField)}
                    </div>
                ) : (
                    renderField(row[0])
                ),
            )}
        </>
    );
}

/** The whole encuadre at once, block after block (used by the admin's test run). */
export function EncuadreForm({ fields, ...props }: FieldsProps & { fields: VisibleField[] }) {
    return (
        <>
            {encuadreBlocks(props.conditions, fields).map((block, index) => (
                <FormBlock key={block.key} id={`bloque-${block.key}`} number={index + 1} title={block.title} hint={block.hint}>
                    <BlockFields fields={block.fields} {...props} />
                </FormBlock>
            ))}
        </>
    );
}

type FormBlockProps = {
    id: string;
    number: number;
    title: string;
    hint?: string;
    children: ReactNode;
    /** Navigation under the fields. */
    footer?: ReactNode;
    titleRef?: Ref<HTMLHeadingElement>;
};

/** A numbered block of the request form. */
export function FormBlock({ id, number, title, hint, children, footer, titleRef }: FormBlockProps) {
    return (
        <section id={id} aria-labelledby={`${id}-title`} className="panel scroll-mt-28 p-5 sm:p-8">
            <header className="mb-6 flex items-start gap-4 border-b border-linea pb-5">
                <span
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-azul font-display text-base font-extrabold tabular-nums text-white"
                    aria-hidden
                >
                    {number}
                </span>
                <div>
                    <h2
                        id={`${id}-title`}
                        ref={titleRef}
                        tabIndex={-1}
                        className="font-display text-xl font-bold leading-tight text-tinta outline-none sm:text-2xl"
                    >
                        {title}
                    </h2>
                    {hint && <p className="mt-1 text-base text-pizarra">{hint}</p>}
                </div>
            </header>
            <div className="space-y-7">{children}</div>
            {footer && <div className="mt-8 border-t border-linea pt-6">{footer}</div>}
        </section>
    );
}
