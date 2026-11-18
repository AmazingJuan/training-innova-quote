import type { FormPage } from '@/features/encuadre/encuadre-form';
import type { PublicCondition } from '@/features/encuadre/types';
import { describe, expect, it } from 'vitest';
import { EMPTY_CONTACT, buildSteps, pagerName, placeServerErrors, problemOf, reviewGroups } from './wizard-model';

const page = (key: string, part: number, parts: number, ids: number[]): FormPage => ({
    key,
    title: `Bloque ${key}`,
    short: key,
    hint: '',
    part,
    parts,
    fields: ids.map((id) => ({ id, depth: 0 })),
});

const condition = { label: 'Sede' } as PublicCondition;

describe('buildSteps', () => {
    it('adds the client data and the sending after the blocks', () => {
        const steps = buildSteps([page('necesidad', 1, 2, [1]), page('necesidad', 2, 2, [2])]);
        expect(steps.map((s) => s.id)).toEqual(['bloque-necesidad-1', 'bloque-necesidad-2', 'datos', 'envio']);
    });

    it('names the next part within a block and the block otherwise', () => {
        const [first, second, contact] = buildSteps([page('necesidad', 1, 2, [1]), page('necesidad', 2, 2, [2])]);
        expect(pagerName(second!, first!)).toBe('Parte 2 de 2');
        expect(pagerName(contact!, second!)).toBe('Datos del cliente');
    });
});

describe('problemOf', () => {
    it('joins a label and its error as a sentence', () => {
        expect(problemOf('Razón social', 'Este campo es obligatorio.')).toBe('Razón social: este campo es obligatorio.');
        expect(problemOf('¿Tiene sede?', 'Seleccione una opción.')).toBe('¿Tiene sede? Seleccione una opción.');
    });
});

describe('reviewGroups', () => {
    it('shows the client data first and each block with the questions as they are sent', () => {
        const pages = [page('necesidad', 1, 1, [1, 2])];
        const walked = [
            { id: 1, key: 'Sede', condition, answer: 'Medellín' },
            { id: 2, key: 'Sede (2)', condition, answer: 'Bogotá' },
        ];
        const groups = reviewGroups({ ...EMPTY_CONTACT, name: 'Ana' }, buildSteps(pages), pages, walked);

        expect(groups[0]).toMatchObject({ title: 'Datos del cliente', start: 1, items: [{ question: 'Nombre del contacto', answer: 'Ana' }] });
        expect(groups[1]?.items.map((i) => i.question)).toEqual(['Sede', 'Sede (2)']);
    });
});

describe('placeServerErrors', () => {
    it('sends each error to the contact field, the question it is about or the general notice', () => {
        const walked = [{ id: 7, key: 'Adjuntar documentos', condition, answer: [] }];
        const placed = placeServerErrors(
            {
                'contact.email': 'Correo inválido.',
                'answers.Adjuntar documentos': 'Requerido.',
                'documents.0.file': 'Muy grande.',
                request: 'Demasiadas solicitudes.',
            },
            walked,
            [{ question: 'Adjuntar documentos' }],
        );

        expect(placed.contact).toEqual({ email: 'Correo inválido.' });
        expect(placed.byField).toEqual({ 7: 'Muy grande.' });
        expect(placed.others).toEqual(['Demasiadas solicitudes.']);
    });
});
