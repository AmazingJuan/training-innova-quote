export interface ClientSummary {
    id: number;
    name: string;
    email: string;
    company?: string | null;
    phone?: string | null;
    role?: string | null;
    nit?: string | null;
    economic_activity?: string | null;
    address?: string | null;
}

export interface QuotationOrder {
    id: string;
    quotation_code: string | null;
    client: ClientSummary | null;
    business_unit: { id: number; display_name: string };
    answers?: Record<string, string>;
    /** Date the client expects the offer by (Y-m-d). */
    offer_date?: string | null;
    is_generated: boolean;
    viewed_by_client: boolean;
    quotation_url: string | null;
    created_at: string;
}

export const quotationTitle = (order: Pick<QuotationOrder, 'id' | 'quotation_code'>) =>
    order.quotation_code ? `Cotización ${order.quotation_code}` : 'Cotización sin código';
