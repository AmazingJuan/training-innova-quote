import { canBranch, type ConditionData, type TreeData } from './types';

export function labelOf(condition: ConditionData | undefined): string {
    return condition?.label.trim() || 'Pregunta sin título';
}

/** Distinct questions a condition can lead to (its default next and option branches). */
export function linksOf(condition: ConditionData): number[] {
    const targets = [condition.next_condition, ...(canBranch(condition) ? condition.options.map((o) => o.next_condition) : [])];
    return [...new Set(targets.filter((id): id is number => id !== null))];
}

/** The main path: from the first question, following each default "next". */
export function mainSequence(tree: TreeData): number[] {
    const byId = new Map(tree.conditions.map((c) => [c.id, c]));
    const sequence: number[] = [];
    let id = tree.initial_condition_id;

    while (id !== null && byId.has(id) && !sequence.includes(id)) {
        sequence.push(id);
        id = byId.get(id)!.next_condition;
    }

    return sequence;
}

/** Re-point the default "next" of every question in `order` so it becomes the main path. */
export function withSequence(tree: TreeData, order: number[]): TreeData {
    const nextOf = new Map(order.map((id, i) => [id, order[i + 1] ?? null]));

    return {
        ...tree,
        initial_condition_id: order[0] ?? null,
        conditions: tree.conditions.map((c) => (nextOf.has(c.id) ? { ...c, next_condition: nextOf.get(c.id)! } : c)),
    };
}

/** How the client can reach a question that is not on the main path. */
export type Arrival = { fromId: number; option: string | null };

export function arrivalsTo(tree: TreeData, id: number): Arrival[] {
    const arrivals: Arrival[] = [];
    for (const c of tree.conditions) {
        if (c.id === id) continue;
        if (canBranch(c)) {
            for (const o of c.options) if (o.next_condition === id) arrivals.push({ fromId: c.id, option: o.label || 'opción sin texto' });
        }
        if (c.next_condition === id) arrivals.push({ fromId: c.id, option: null });
    }
    return arrivals;
}

/** Returns a closed path (first id repeated at the end) or null. */
export function findCycle(conditions: ConditionData[]): number[] | null {
    const byId = new Map(conditions.map((c) => [c.id, c]));
    const state = new Map<number, 'active' | 'done'>();
    const path: number[] = [];

    const visit = (id: number): number[] | null => {
        state.set(id, 'active');
        path.push(id);
        for (const next of linksOf(byId.get(id)!)) {
            if (!byId.has(next)) continue;
            if (state.get(next) === 'active') return [...path.slice(path.indexOf(next)), next];
            if (!state.has(next)) {
                const cycle = visit(next);
                if (cycle) return cycle;
            }
        }
        path.pop();
        state.set(id, 'done');
        return null;
    };

    for (const c of conditions) {
        if (!state.has(c.id)) {
            const cycle = visit(c.id);
            if (cycle) return cycle;
        }
    }
    return null;
}

export type Issue = { conditionId: number | null; message: string };

/** Problems that block saving, attached to the question that causes them. Mirrors the server. */
export function validateTree(tree: TreeData): Issue[] {
    const byId = new Map(tree.conditions.map((c) => [c.id, c]));
    const issues: Issue[] = [];

    if (tree.conditions.length > 0 && mainSequence(tree).length === 0) {
        issues.push({ conditionId: null, message: 'El cuestionario no tiene una primera pregunta.' });
    }

    for (const c of tree.conditions) {
        if (!c.label.trim()) issues.push({ conditionId: c.id, message: 'Escriba la pregunta.' });
        if (c.interaction_type === 'options') {
            const regular = c.options.filter((o) => !o.is_other);
            if (regular.length === 0) issues.push({ conditionId: c.id, message: 'Agregue al menos una opción.' });
            if (c.options.some((o) => !o.label.trim())) issues.push({ conditionId: c.id, message: 'Hay opciones sin texto: escríbalas o quítelas.' });
        }
        if (linksOf(c).some((target) => !byId.has(target))) {
            issues.push({ conditionId: c.id, message: 'Lleva a una pregunta que ya no existe.' });
        }
    }

    const cycle = findCycle(tree.conditions);
    if (cycle) {
        issues.push({
            conditionId: cycle[0] ?? null,
            message: `Se forma un ciclo: ${cycle.map((id) => `«${labelOf(byId.get(id))}»`).join(' → ')}. El cliente nunca terminaría.`,
        });
    }

    return issues;
}
