import { describe, expect, it } from 'vitest';
import { answerError, answeredPath, datePairErrors, formatAnswer, pathAnswers, validateFields, visibleFields } from './answers';
import type { PublicCondition } from './types';

const question = (overrides: Partial<PublicCondition>): PublicCondition => ({
    label: 'Pregunta',
    interaction_type: 'input',
    type: 'text',
    observation: null,
    placeholder: null,
    multiline: false,
    multiple: false,
    section: null,
    role: null,
    required: true,
    next_condition: null,
    options: [],
    ...overrides,
});

describe('visibleFields', () => {
    // 1 (Sí → 2, No → 3) ; 2 → 3 ; 3 ends.
    const conditions = {
        1: question({
            label: '¿Tiene certificación?',
            interaction_type: 'options',
            next_condition: 3,
            options: [
                { label: 'Sí', value: null, is_other: false, next_condition: 2 },
                { label: 'No', value: null, is_other: false, next_condition: null },
            ],
        }),
        2: question({ label: 'Ente certificador', next_condition: 3 }),
        3: question({ label: 'Observaciones' }),
    };

    it('follows the main order when no option branches', () => {
        expect(visibleFields(conditions, 1, {}).map((f) => f.id)).toEqual([1, 3]);
    });

    it('opens a branch and rejoins the main order after it', () => {
        expect(visibleFields(conditions, 1, { 1: { selected: [0], other: '' } })).toEqual([
            { id: 1, depth: 0 },
            { id: 2, depth: 1 },
            { id: 3, depth: 0 },
        ]);
    });
});

describe('answerError', () => {
    it('asks for required answers and lets optional ones be blank', () => {
        expect(answerError(question({}), '')).toBe('Este campo es obligatorio.');
        expect(answerError(question({ required: false }), '')).toBeNull();
    });

    it('rejects an offer date sooner than the minimum the server sent', () => {
        const offer = question({ type: 'date', role: 'offer_date', min_date: '2026-11-19' });
        expect(answerError(offer, '2026-11-18')).toMatch(/a partir del/);
        expect(answerError(offer, '2026-11-19')).toBeNull();
    });

    it('asks for the text of "Otro" when it is chosen', () => {
        const choice = question({ interaction_type: 'options', options: [{ label: 'Otro', value: null, is_other: true, next_condition: null }] });
        expect(answerError(choice, { selected: [0], other: '' })).toBe('Escriba su respuesta en «Otro».');
    });
});

describe('dates read together', () => {
    const conditions = { 1: question({ type: 'date', label: 'Inicio', next_condition: 2 }), 2: question({ type: 'date', label: 'Fin' }) };
    const fields = [
        { id: 1, depth: 0 },
        { id: 2, depth: 0 },
    ];

    it('needs the end after the start', () => {
        expect(datePairErrors(conditions, fields, { 1: '2026-12-10', 2: '2026-12-01' })).toEqual({ 2: 'Debe ser posterior a la fecha de inicio.' });
        expect(validateFields(conditions, fields, { 1: '2026-12-01', 2: '2026-12-10' })).toEqual({});
    });
});

describe('answers as they are sent', () => {
    it('keeps repeated labels apart and leaves blank optional answers out', () => {
        const conditions = {
            1: question({ label: 'Sede', next_condition: 2 }),
            2: question({ label: 'Sede', next_condition: 3 }),
            3: question({ label: 'Notas', required: false }),
        };
        expect(answeredPath(conditions, [1, 2, 3], { 1: 'Medellín', 2: 'Bogotá', 3: '' }).map((w) => w.key)).toEqual(['Sede', 'Sede (2)']);
        expect(pathAnswers(conditions, [1, 2, 3], { 1: 'Medellín', 2: 'Bogotá' })).toEqual({ Sede: 'Medellín', 'Sede (2)': 'Bogotá' });
    });

    it('formats numbers as people read them', () => {
        expect(formatAnswer(question({ type: 'number' }), '15000000')).toBe((15000000).toLocaleString('es-CO'));
    });
});
