import type { SectionKey } from './sections';

/**
 * The request form ("encuadre") as both the public form and the admin's
 * editor see it. Mirrors the enums of app/Enums.
 */

/** How the client answers (App\Enums\InteractionType). */
export type InteractionType = 'input' | 'range' | 'options';
/** Kind of value asked for (App\Enums\FieldType); 'file' only with 'input'. */
export type FieldType = 'text' | 'number' | 'date' | 'file';
/** What the application does with the answer (App\Enums\ConditionRole). */
export type ConditionRole = 'offer_date';

export type OptionData = {
    label: string;
    /** Opaque value stored with the option; preserved on save. */
    value: string | null;
    is_other: boolean;
    /** Branch: where this option leads. null = the question's default next. */
    next_condition: number | null;
};

/** A question, without its id: in the public form questions are keyed by id. */
export type QuestionData = {
    label: string;
    interaction_type: InteractionType;
    type: FieldType;
    observation: string | null;
    /** Example of the expected answer, shown inside the field (open questions). */
    placeholder: string | null;
    /** Free text answered in a long field. */
    multiline: boolean;
    multiple: boolean;
    /** Block of the encuadre it belongs to (see sections.ts); null = none. */
    section: SectionKey | null;
    role: ConditionRole | null;
    /** false lets the client skip it. */
    required: boolean;
    /** Default next question; null ends the questionnaire. */
    next_condition: number | null;
    options: OptionData[];
};

/** A question as the public form receives it. */
export type PublicCondition = QuestionData & {
    /** Soonest date accepted (Y-m-d), set by the server on the offer date: 3 business days after today. */
    min_date?: string;
};

export type RangeAnswer = { min: string; max: string };
/** Indexes of the chosen options plus the free text of an "Otro" option. */
export type OptionsAnswer = { selected: number[]; other: string };
/** Files attached in a 'file' question; they are sent with the request but not kept in the draft. */
export type FilesAnswer = File[];
export type Answer = string | RangeAnswer | OptionsAnswer | FilesAnswer;

/*
 * Reading an answer as the shape its question expects. An answer is stored
 * by question id and a question can change kind in the editor (or come from
 * an old draft), so each reader checks the shape and falls back to an empty
 * value instead of trusting a cast.
 */

export const textOf = (answer: Answer | undefined): string => (typeof answer === 'string' ? answer : '');

export const filesOf = (answer: Answer | undefined): FilesAnswer => (Array.isArray(answer) ? answer : []);

export const rangeOf = (answer: Answer | undefined): RangeAnswer =>
    typeof answer === 'object' && answer !== null && !Array.isArray(answer) && 'min' in answer ? answer : { min: '', max: '' };

export const optionsOf = (answer: Answer | undefined): OptionsAnswer =>
    typeof answer === 'object' && answer !== null && !Array.isArray(answer) && 'selected' in answer ? answer : { selected: [], other: '' };
