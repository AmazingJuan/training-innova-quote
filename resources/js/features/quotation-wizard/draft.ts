import type { Answer, ContactData, PublicCondition } from './types';

/**
 * The request is kept in this browser while it is filled in, as the corporate
 * site keeps the visitor's quote list (B2B buyers research over several days).
 * The contact data is shared by every unit; the answers are per unit and are
 * dropped when the questionnaire changed since they were saved.
 */
type SavedAnswers = { signature: string; answers: Record<number, Answer>; step: number; reached: number };

const CONTACT_KEY = 'tc-encuadre:contacto';
const answersKey = (unit: string) => `tc-encuadre:${unit}`;
const sentKey = (unit: string) => `tc-encuadre:enviada:${unit}`;

/** One group of the summary of a request: a block of the encuadre or the contact data. */
export type ReviewGroup = { title: string; short?: boolean; items: { question: string; answer: string }[] };

/** A request just sent, kept for this tab so a reload still shows its confirmation instead of an empty form. */
export type SentRequest = { code: string | null; email: string; review: ReviewGroup[] };

/**
 * Changes whenever the questions of the unit change: ids are recreated by the seeder, and
 * a question can change kind (e.g. from text to options), which changes the shape of its saved answer.
 */
export const treeSignature = (conditions: Record<string, PublicCondition>) =>
    Object.entries(conditions)
        .sort(([a], [b]) => Number(a) - Number(b))
        .map(([id, c]) => `${id}:${c.interaction_type}:${c.type}:${c.multiple ? 1 : 0}`)
        .join(',');

const local = () => window.localStorage;
const session = () => window.sessionStorage;

function read<T>(key: string, store = local): T | null {
    try {
        const raw = store().getItem(key);
        return raw ? (JSON.parse(raw) as T) : null;
    } catch {
        return null;
    }
}

function write(key: string, value: unknown, store = local): boolean {
    try {
        store().setItem(key, JSON.stringify(value));
        return true;
    } catch {
        // Private mode or full storage: the form keeps working, it just isn't kept.
        return false;
    }
}

function remove(key: string, store = local) {
    try {
        store().removeItem(key);
    } catch {
        // Nothing to clean up.
    }
}

export const draft = {
    /** Whether this browser keeps the request at all (not in some private modes, or with storage blocked). */
    isKept(): boolean {
        const probe = 'tc-encuadre:prueba';
        const ok = write(probe, 1);
        remove(probe);
        return ok;
    },

    contact: () => read<ContactData>(CONTACT_KEY),
    saveContact: (contact: ContactData) => write(CONTACT_KEY, contact),
    /** On a shared computer the client can take their data out of this browser. */
    clearContact: () => remove(CONTACT_KEY),

    answers(unit: string, signature: string): SavedAnswers | null {
        const saved = read<SavedAnswers>(answersKey(unit));
        return saved && saved.signature === signature ? saved : null;
    },
    /** Attached files can't be kept in the browser: they are left out and attached again. */
    saveAnswers: (unit: string, saved: SavedAnswers) =>
        write(answersKey(unit), {
            ...saved,
            answers: Object.fromEntries(Object.entries(saved.answers).filter(([, answer]) => !Array.isArray(answer))),
        }),
    /** Whether a request for this unit was left half done (to offer to continue it). */
    hasAnswers: (unit: string) => Object.keys(read<SavedAnswers>(answersKey(unit))?.answers ?? {}).length > 0,
    clearAnswers: (unit: string) => remove(answersKey(unit)),

    sent: (unit: string) => read<SentRequest>(sentKey(unit), session),
    saveSent: (unit: string, request: SentRequest) => write(sentKey(unit), request, session),
    /** Choosing a unit again starts a new request. */
    clearSent: (unit: string) => remove(sentKey(unit), session),
};
