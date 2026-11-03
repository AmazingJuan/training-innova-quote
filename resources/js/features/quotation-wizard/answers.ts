import { filesError } from './documents';
import type { Answer, FilesAnswer, OptionsAnswer, PublicCondition, RangeAnswer } from './types';

export const emptyAnswer = (condition: PublicCondition): Answer => {
    if (condition.interaction_type === 'range') return { min: '', max: '' };
    if (condition.interaction_type === 'options') return { selected: [], other: '' };
    if (condition.type === 'file') return [];
    return '';
};

const isFiles = (condition: PublicCondition) => condition.interaction_type === 'input' && condition.type === 'file';

const otherIndex = (condition: PublicCondition, answer: OptionsAnswer) => answer.selected.find((i) => condition.options[i]?.is_other);

/** Same limit as the server for every answer. */
export const MAX_ANSWER_LENGTH = 5000;

/** Numbers are kept as plain digits: "15.000.000" or "$15 000 000" is typed, "15000000" is stored. */
export const onlyDigits = (value: string) => value.replace(/\D/g, '');

/** "15000000" → "15.000.000", as written in Colombia. */
export const formatNumber = (digits: string) => (digits === '' ? '' : Number(digits).toLocaleString('es-CO', { maximumFractionDigits: 0 }));

/** Nothing entered yet (an optional question left like this is skipped). */
export function isBlank(condition: PublicCondition, answer: Answer | undefined): boolean {
    const value = answer ?? emptyAnswer(condition);
    switch (condition.interaction_type) {
        case 'input':
            return isFiles(condition) ? (value as FilesAnswer).length === 0 : String(value).trim() === '';
        case 'range': {
            const { min, max } = value as RangeAnswer;
            return min.trim() === '' && max.trim() === '';
        }
        case 'options':
            return (value as OptionsAnswer).selected.length === 0;
    }
}

/** Why the answer can't be accepted yet, or null when it is complete. */
export function answerError(condition: PublicCondition, answer: Answer | undefined): string | null {
    const value = answer ?? emptyAnswer(condition);
    if (!condition.required && isBlank(condition, value)) return null;

    switch (condition.interaction_type) {
        case 'input':
            if (isFiles(condition)) {
                const files = value as FilesAnswer;
                return files.length === 0 ? 'Adjunte al menos un documento.' : filesError(files);
            }
            return String(value).trim() === '' ? 'Este campo es obligatorio.' : null;

        case 'range': {
            const { min, max } = value as RangeAnswer;
            if (min.trim() === '' || max.trim() === '') return 'Indique ambos valores.';
            if (condition.type === 'number') {
                const [a, b] = [onlyDigits(min), onlyDigits(max)].map((v) => (v === '' ? NaN : Number(v)));
                if (Number.isNaN(a) || Number.isNaN(b)) return 'Escriba solo números.';
                return a <= b ? null : 'El valor inicial no puede ser mayor que el final.';
            }
            if (condition.type === 'date') {
                return new Date(min) <= new Date(max) ? null : 'La fecha final no puede ser anterior a la inicial.';
            }
            return null;
        }

        case 'options': {
            const options = value as OptionsAnswer;
            if (options.selected.length === 0) return condition.multiple ? 'Seleccione al menos una opción.' : 'Seleccione una opción.';
            if (otherIndex(condition, options) !== undefined && options.other.trim() === '') return 'Escriba su respuesta en «Otro».';
            return null;
        }
    }
}

/** Where the flow goes after this answer: a single-choice option may branch. */
export function nextConditionId(condition: PublicCondition, answer: Answer | undefined): number | null {
    if (condition.interaction_type === 'options' && !condition.multiple && answer) {
        const chosen = condition.options[(answer as OptionsAnswer).selected[0]];
        if (chosen?.next_condition) return chosen.next_condition;
    }
    return condition.next_condition;
}

/** A question shown in the form; `depth` > 0 when it opened from an option of another one. */
export type VisibleField = { id: number; depth: number };

const isDate = (condition: PublicCondition) => condition.interaction_type === 'input' && condition.type === 'date';

/**
 * Two date questions one after the other are a start and an end (that is also how
 * they are drawn, side by side), so the second one has to come after the first.
 * Each is still answered on its own: the problem is only reported once both are filled in.
 */
export function datePairErrors(
    conditions: Record<string, PublicCondition>,
    fields: VisibleField[],
    answers: Record<number, Answer>,
): Record<number, string> {
    const errors: Record<number, string> = {};

    for (let i = 1; i < fields.length; i++) {
        const [start, end] = [fields[i - 1], fields[i]];
        if (start.depth !== 0 || end.depth !== 0) continue;
        if (!isDate(conditions[start.id]) || !isDate(conditions[end.id])) continue;

        const [from, to] = [start, end].map((f) => new Date(`${String(answers[f.id] ?? '').trim()}T00:00:00`));
        if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) continue;
        if (to <= from) errors[end.id] = 'Debe ser posterior a la fecha de inicio.';
    }

    return errors;
}

/**
 * The questions the client sees with the current answers, in order: the main
 * order plus the branches opened by the chosen options (a branch rejoins the
 * flow where its question would have gone). This is also the path whose
 * answers are sent.
 */
export function visibleFields(
    conditions: Record<string, PublicCondition>,
    initialId: number | null,
    answers: Record<number, Answer>,
): VisibleField[] {
    const fields: VisibleField[] = [];
    const joins: number[] = []; // where each open branch rejoins, innermost last
    const seen = new Set<number>();
    let id = initialId;

    while (id !== null && conditions[id] && !seen.has(id)) {
        while (joins.length > 0 && joins[joins.length - 1] === id) joins.pop();
        seen.add(id);
        fields.push({ id, depth: joins.length });

        const condition = conditions[id];
        const next = nextConditionId(condition, answers[id]);
        if (next !== condition.next_condition && condition.next_condition !== null) joins.push(condition.next_condition);
        else if (next !== condition.next_condition) joins.push(-1); // branch that ends the questionnaire
        id = next;
    }

    return fields;
}

const formatDate = (value: string) => {
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('es-CO');
};

/** Human-readable answer, as stored in the quotation and shown in the summary. */
export function formatAnswer(condition: PublicCondition, answer: Answer): string {
    switch (condition.interaction_type) {
        case 'input':
            if (isFiles(condition)) return (answer as FilesAnswer).map((file) => file.name).join(', ');
            if (condition.type === 'date') return formatDate(String(answer));
            return condition.type === 'number' ? formatNumber(onlyDigits(String(answer))) : String(answer).trim();

        case 'range': {
            const { min, max } = answer as RangeAnswer;
            const fmt =
                condition.type === 'date'
                    ? formatDate
                    : condition.type === 'number'
                      ? (v: string) => formatNumber(onlyDigits(v))
                      : (v: string) => v.trim();
            return `Desde ${fmt(min)} hasta ${fmt(max)}`;
        }

        case 'options': {
            const { selected, other } = answer as OptionsAnswer;
            return [...selected]
                .sort((a, b) => Number(condition.options[a]?.is_other) - Number(condition.options[b]?.is_other) || a - b)
                .map((i) => (condition.options[i]?.is_other ? other.trim() : condition.options[i]?.label))
                .filter(Boolean)
                .join(', ');
        }
    }
}

/**
 * The answered conditions actually walked (in order), each with the key its
 * answer is sent under. Answers of branches the client backed out of, and
 * optional questions left blank, are ignored. Repeated labels get a suffix so
 * they don't overwrite each other.
 */
export function answeredPath(conditions: Record<string, PublicCondition>, path: number[], answers: Record<number, Answer>) {
    const keys = new Set<string>();
    const result: { id: number; key: string; condition: PublicCondition; answer: Answer }[] = [];

    for (const id of path) {
        const condition = conditions[id];
        const answer = answers[id];
        if (!condition || answer === undefined || isBlank(condition, answer)) continue;

        let key = condition.label;
        for (let n = 2; keys.has(key); n++) key = `${condition.label} (${n})`;
        keys.add(key);
        result.push({ id, key, condition, answer });
    }

    return result;
}

/** Readable answers keyed by question, as stored in the quotation. */
export function pathAnswers(conditions: Record<string, PublicCondition>, path: number[], answers: Record<number, Answer>): Record<string, string> {
    return Object.fromEntries(answeredPath(conditions, path, answers).map(({ key, condition, answer }) => [key, formatAnswer(condition, answer)]));
}

/** The files attached along the walked path, each with the key of its question's answer (the admin shows them under it). */
export function pathFiles(
    conditions: Record<string, PublicCondition>,
    path: number[],
    answers: Record<number, Answer>,
): { question: string; file: File }[] {
    return answeredPath(conditions, path, answers)
        .filter(({ condition }) => isFiles(condition))
        .flatMap(({ key, answer }) => (answer as FilesAnswer).map((file) => ({ question: key, file })));
}
