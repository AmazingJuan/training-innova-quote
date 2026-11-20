export type UserRole = 'admin' | 'user';

export type RoleOption = { value: UserRole; label: string };

export interface User {
    id: number;
    name: string;
    email: string;
}

/** Corporate contact data (Configuración), shown in the public footer. */
export interface Company {
    email: string | null;
    phone: string | null;
    address: string | null;
    website_url: string | null;
    website_label: string | null;
}

export interface Flash {
    success?: string | null;
    error?: string | null;
    /** A quotation request was just sent (shown by the quote page itself). */
    quotation_sent?: { code: string | null; email: string } | null;
}

/** Props shared with every page by HandleInertiaRequests. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User | null;
        /** What the signed-in account may do (the server enforces it too). */
        can: { manageUsers: boolean };
    };
    flash: Flash;
    company: Company;
};

/** Laravel LengthAwarePaginator serialised to JSON. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export type SearchFilters = {
    search: string;
};
