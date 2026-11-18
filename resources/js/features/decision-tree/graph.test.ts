import { describe, expect, it } from 'vitest';
import { findCycle, mainSequence, withSequence } from './graph';
import type { ConditionData, TreeData } from './types';

const q = (id: number, next: number | null, branches: (number | null)[] = []): ConditionData => ({
    id,
    label: `P${id}`,
    interaction_type: branches.length ? 'options' : 'input',
    type: 'text',
    observation: null,
    placeholder: null,
    multiline: false,
    multiple: false,
    section: null,
    role: null,
    required: true,
    next_condition: next,
    options: branches.map((target, i) => ({ id: i + 1, label: `O${i}`, value: null, is_other: false, next_condition: target })),
});

const tree = (conditions: ConditionData[], initial: number | null = 1): TreeData => ({
    id: 1,
    name: 'Auditoría',
    initial_condition_id: initial,
    conditions,
});

describe('mainSequence', () => {
    it('follows each default next from the first question', () => {
        expect(mainSequence(tree([q(1, 2), q(2, 3), q(3, null)]))).toEqual([1, 2, 3]);
    });

    it('stops at a loop instead of running forever', () => {
        expect(mainSequence(tree([q(1, 2), q(2, 1)]))).toEqual([1, 2]);
    });
});

describe('withSequence', () => {
    it('re-points the default nexts so the order becomes the main path', () => {
        const reordered = withSequence(tree([q(1, 2), q(2, 3), q(3, null)]), [3, 1, 2]);
        expect(mainSequence(reordered)).toEqual([3, 1, 2]);
    });
});

describe('findCycle', () => {
    it('finds a branch that leads back to an earlier question', () => {
        expect(findCycle([q(1, 2), q(2, null, [1])])).not.toBeNull();
        expect(findCycle([q(1, 2), q(2, null)])).toBeNull();
    });
});
