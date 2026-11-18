export function capitalize(text: string): string {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * Localised short date (dd/mm/yyyy) or an em dash when missing/invalid. A bare
 * date ("2026-11-19") is a calendar day, not UTC midnight: read as local time,
 * or in Colombia it would show the day before.
 */
export function formatDate(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('es-CO');
}

/** Shown in tables and detail views for empty values. */
export function orDash(value?: string | number | null): string {
    return value === null || value === undefined || value === '' ? '—' : String(value);
}
