import type { PublicCondition } from '@/features/encuadre/types';

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
