import type { SectionKey } from './sections';

export type PublicOption = {
    label: string;
    value: string | null;
    is_other: boolean;
    next_condition: number | null;
};

export type PublicCondition = {
    label: string;
    interaction_type: 'input' | 'range' | 'options';
    /** 'file' only with 'input': the client attaches documents. */
    type: 'text' | 'number' | 'date' | 'file';
    observation: string | null;
    /** Example of the expected answer, shown inside the field. */
    placeholder: string | null;
    /** Free text answered in a long field. */
    multiline: boolean;
    multiple: boolean;
    /** Block of the encuadre (see sections.ts); null = no block. */
    section: SectionKey | null;
    required: boolean;
    next_condition: number | null;
    options: PublicOption[];
    /** Soonest date accepted (Y-m-d), set by the server on the offer date: 3 business days after today. */
    min_date?: string;
};

export type QuoteViewData = {
    businessUnit: string;
    /** Slug of the unit (its URL), which also keys the draft. */
    unit: string;
    /** Units that can be quoted, for the tabs. */
    units: { name: string; display_name: string }[];
    /** Reachable conditions (the unit's encuadre) keyed by id. */
    conditions: Record<string, PublicCondition>;
    initial_condition_id: number | null;
};

export type RangeAnswer = { min: string; max: string };
/** Indexes of the chosen options plus the free text of an "Otro" option. */
export type OptionsAnswer = { selected: number[]; other: string };
/** Files attached in a 'file' question; they are sent with the request but not kept in the draft. */
export type FilesAnswer = File[];
export type Answer = string | RangeAnswer | OptionsAnswer | FilesAnswer;

export type ContactData = {
    name: string;
    company: string;
    nit: string;
    economic_activity: string;
    address: string;
    role: string;
    email: string;
    phone: string;
};
