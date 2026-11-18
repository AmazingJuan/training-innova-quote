import { formatAnswer, type WalkedAnswer } from '@/features/encuadre/answers';
import type { FormPage } from '@/features/encuadre/encuadre-form';
import { CONTACT_GROUPS, type ContactErrors } from './contact-fields';
import type { ReviewGroup } from './draft';
import type { Screen } from './step-nav';
import type { ContactData } from './types';

/**
 * The request form's logic, without React: which screens there are, what the
 * review shows and where each server error goes. Pure functions, so they can
 * be tested on their own.
 */

export const EMPTY_CONTACT: ContactData = { name: '', company: '', nit: '', economic_activity: '', address: '', role: '', email: '', phone: '' };

export const PRIVACY_ERROR = 'Autorice el tratamiento de sus datos para enviar la solicitud.';

/** The last two screens, after the blocks of the encuadre. */
const CONTACT_SCREEN: Screen = {
    id: 'datos',
    group: 'datos',
    title: 'Datos del cliente',
    short: 'Datos',
    hint: 'Datos de la organización y de la persona de contacto, a quien se enviará la propuesta.',
    part: 1,
    parts: 1,
};

const SEND_SCREEN: Screen = {
    id: 'envio',
    group: 'envio',
    title: 'Envío de la solicitud',
    short: 'Envío',
    hint: 'Verifique la información registrada antes de enviar la solicitud.',
    part: 1,
    parts: 1,
};

/** One screen per part of each block, then the client data and the sending. */
export function buildSteps(pages: FormPage[]): Screen[] {
    return [
        ...pages.map((p) => ({
            id: `bloque-${p.key}-${p.part}`,
            group: p.key,
            title: p.title,
            short: p.short,
            hint: p.hint,
            part: p.part,
            parts: p.parts,
        })),
        CONTACT_SCREEN,
        SEND_SCREEN,
    ];
}

/** Blocks as the step list counts them: a long block's parts are one step. */
export const blocksOf = (steps: Screen[]) => steps.map((s) => s.group).filter((group, index, all) => all.indexOf(group) === index);

const lowerFirst = (text: string) => text.charAt(0).toLowerCase() + text.slice(1);

/** "Razón social: este campo es obligatorio." or, for a question, "¿…? Este campo es obligatorio." */
export const problemOf = (label: string, error: string) => (/[?.!]$/.test(label) ? `${label} ${error}` : `${label}: ${lowerFirst(error)}`);

/** How a screen is named: "Identificación de la necesidad, parte 2 de 3". */
export const screenName = (screen: Screen) => (screen.parts > 1 ? `${screen.title}, parte ${screen.part} de ${screen.parts}` : screen.title);

/** Where a pager button goes; within the same block the part is enough ("Parte 2 de 3"). */
export const pagerName = (to: Screen, from: Screen) => (to.group === from.group ? `Parte ${to.part} de ${to.parts}` : to.title);

export type ReviewSection = ReviewGroup & { start: number };

/**
 * The whole request as it will be sent, block by block: the client data
 * first, since the proposal goes to that e-mail. Questions read as they are
 * sent (their key), so two questions with the same wording stay apart.
 */
export function reviewGroups(contact: ContactData, steps: Screen[], pages: FormPage[], walked: WalkedAnswer[]): ReviewSection[] {
    const contactStep = steps.length - 2;

    return [
        {
            title: CONTACT_SCREEN.title,
            short: true,
            start: contactStep,
            items: CONTACT_GROUPS.flatMap((g) => g.fields)
                .filter((f) => contact[f.key].trim() !== '')
                .map((f) => ({ question: f.label, answer: contact[f.key].trim() })),
        },
        ...blocksOf(steps)
            .filter((group) => group !== CONTACT_SCREEN.group && group !== SEND_SCREEN.group)
            .map((group) => {
                const ids = new Set(pages.filter((p) => p.key === group).flatMap((p) => p.fields.map((f) => f.id)));
                return {
                    title: pages.find((p) => p.key === group)?.title ?? '',
                    start: steps.findIndex((s) => s.group === group),
                    items: walked.filter((w) => ids.has(w.id)).map((w) => ({ question: w.key, answer: formatAnswer(w.condition, w.answer) })),
                };
            }),
    ].filter((group) => group.items.length > 0);
}

export type ServerErrors = { contact: ContactErrors; byField: Record<number, string>; others: string[] };

/**
 * Where each validation error of the server goes: the contact data, the
 * question it is about (answers are sent keyed by question, documents with
 * the question they belong to) or, otherwise, the general notice.
 */
export function placeServerErrors(errors: Record<string, string>, walked: WalkedAnswer[], documents: { question: string }[]): ServerErrors {
    const idOf = new Map(walked.map((w) => [w.key, w.id]));
    const placed: ServerErrors = { contact: {}, byField: {}, others: [] };

    for (const [key, message] of Object.entries(errors)) {
        const document = /^documents\.(\d+)\./.exec(key);
        const field = key.startsWith('answers.')
            ? idOf.get(key.slice('answers.'.length))
            : document
              ? idOf.get(documents[Number(document[1])]?.question ?? '')
              : undefined;

        if (key.startsWith('contact.')) placed.contact[key.slice('contact.'.length) as keyof ContactData] = message;
        else if (key === 'privacy_accepted') placed.contact.privacy_accepted = message;
        else if (field !== undefined) placed.byField[field] = message;
        else placed.others.push(message);
    }

    return placed;
}
