import type { Placement } from './api';
import { mainSequence, validateTree, withSequence, type Issue } from './graph';
import { blankOption, type ConditionData, type TreeData } from './types';

/** The question being edited (or created) in one card. Only one at a time. */
export type Draft = {
    question: ConditionData;
    /** Present when creating. */
    placement: Placement | null;
    /** Unchanged copy, to know whether there is something to save. */
    original: ConditionData;
};

let nextNewId = -1;

/** `section` is usually the one of the question it is placed after or branches from. */
export function newDraft(placement: Placement, section: ConditionData['section'] = null): Draft {
    const question: ConditionData = {
        id: nextNewId--,
        label: '',
        interaction_type: 'options',
        type: 'text',
        observation: '',
        placeholder: '',
        multiline: false,
        role: null,
        multiple: false,
        section,
        required: true,
        next_condition: null,
        options: [blankOption(), blankOption()],
    };
    return { question, placement, original: question };
}

export function editDraft(question: ConditionData): Draft {
    const copy = structuredClone(question);
    return { question: copy, placement: null, original: question };
}

export const isDirty = (draft: Draft) => JSON.stringify(draft.question) !== JSON.stringify(draft.original);

/** The tree as it would be after saving the draft, to check it before sending. */
export function simulate(tree: TreeData, draft: Draft): TreeData {
    const q = draft.question;
    const { placement } = draft;

    if (!placement) {
        return {
            ...tree,
            conditions: tree.conditions.map((c) =>
                c.id === q.id ? { ...q, next_condition: mainSequence(tree).includes(q.id) ? c.next_condition : q.next_condition } : c,
            ),
        };
    }

    if (placement.branch_option_id) {
        const parent = tree.conditions.find((c) => c.options.some((o) => o.id === placement.branch_option_id));
        return {
            ...tree,
            conditions: [
                ...tree.conditions.map((c) =>
                    c === parent
                        ? { ...c, options: c.options.map((o) => (o.id === placement.branch_option_id ? { ...o, next_condition: q.id } : o)) }
                        : c,
                ),
                { ...q, next_condition: parent?.next_condition ?? null },
            ],
        };
    }

    const order = mainSequence(tree);
    const at = placement.at_start ? 0 : placement.insert_after ? order.indexOf(placement.insert_after) + 1 : order.length;
    return withSequence({ ...tree, conditions: [...tree.conditions, q] }, [...order.slice(0, at), q.id, ...order.slice(at)]);
}

/** Problems the draft would introduce (ones already present in the saved tree are not its fault). */
export function draftIssues(tree: TreeData, draft: Draft): Issue[] {
    const before = new Set(validateTree(tree).map((i) => i.message));
    return validateTree(simulate(tree, draft)).filter((i) => i.conditionId === draft.question.id || !before.has(i.message));
}
