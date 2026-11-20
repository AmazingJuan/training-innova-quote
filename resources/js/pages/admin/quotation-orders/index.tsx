import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { AdminPagination } from '@/components/admin/admin-pagination';
import { AdminTableToolbar } from '@/components/admin/admin-table-toolbar';
import { DataTable } from '@/components/admin/data-table';
import { RowActions, ViewAction } from '@/components/admin/row-actions';
import { QuotationStatusBadge, StatusBadge } from '@/components/admin/status-badge';
import { adminListShellClass, adminTableTdClass } from '@/components/admin/styles';
import type { QuotationOrder } from '@/features/quotations/types';
import { useListFilters } from '@/hooks/use-list-filters';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate, orDash } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps, Paginated, SearchFilters } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

type IndexPageProps = PageProps<{
    viewData: { quotationOrders: Paginated<QuotationOrder>; filters: SearchFilters };
}>;

export default function Index() {
    const { quotationOrders, filters } = usePage<IndexPageProps>().props.viewData;
    const visit = useListFilters('dashboard.quotation-orders.index', filters);

    return (
        <AdminLayout title="Cotizaciones">
            <div className={adminListShellClass}>
                <AdminPageHeader title="Cotizaciones" description="Solicitudes recibidas desde el cotizador. Abra una para subir la propuesta." />

                <AdminTableToolbar search={filters.search} onSearch={(search) => visit({ search })} searchPlaceholder="Código, cliente o empresa…" />

                <DataTable
                    columns={[
                        { label: 'Código' },
                        { label: 'Cliente' },
                        { label: 'Empresa', className: 'hidden lg:table-cell' },
                        { label: 'Unidad', className: 'hidden md:table-cell' },
                        { label: 'Fecha', className: 'hidden md:table-cell' },
                        { label: 'Estado' },
                        { label: 'Acciones', align: 'center' },
                    ]}
                    minWidth="22rem"
                    isEmpty={quotationOrders.data.length === 0}
                    emptyMessage={filters.search ? 'No hay resultados para su búsqueda.' : 'Aún no se han recibido solicitudes de cotización.'}
                >
                    {quotationOrders.data.map((order) => (
                        <tr key={order.id} className="hover:bg-nube/70">
                            <td className={cn(adminTableTdClass, 'whitespace-nowrap font-display font-bold')}>
                                <Link
                                    href={route('dashboard.quotation-orders.show', order.id)}
                                    className="text-tinta hover:text-azul-hondo hover:underline"
                                >
                                    {order.quotation_code ?? '—'}
                                </Link>
                                {/* On a phone the unit and date columns are hidden: they go under the code. */}
                                <span className="mt-0.5 block font-sans text-xs font-normal text-pizarra md:hidden">
                                    {order.business_unit.display_name}, {formatDate(order.created_at)}
                                </span>
                            </td>
                            <td className={adminTableTdClass}>
                                <span className="block font-medium text-tinta">{orDash(order.client?.name)}</span>
                                <span className="block text-xs text-pizarra">{order.client?.email}</span>
                            </td>
                            <td className={cn(adminTableTdClass, 'hidden lg:table-cell')}>{orDash(order.client?.company)}</td>
                            <td className={cn(adminTableTdClass, 'hidden md:table-cell')}>{order.business_unit.display_name}</td>
                            <td className={cn(adminTableTdClass, 'hidden whitespace-nowrap tabular-nums md:table-cell')}>
                                {formatDate(order.created_at)}
                            </td>
                            <td className={adminTableTdClass}>
                                <div className="flex flex-wrap gap-1.5">
                                    <QuotationStatusBadge generated={order.is_generated} />
                                    {order.is_generated &&
                                        (order.viewed_by_client ? (
                                            <StatusBadge tone="info">Vista</StatusBadge>
                                        ) : (
                                            <StatusBadge tone="neutral">No vista</StatusBadge>
                                        ))}
                                </div>
                            </td>
                            <td className={cn(adminTableTdClass, 'w-32')}>
                                <RowActions>
                                    <ViewAction href={route('dashboard.quotation-orders.show', order.id)} />
                                    {order.quotation_url && (
                                        <a
                                            href={order.quotation_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex h-9 w-9 items-center justify-center rounded-control text-azul-hondo transition-colors hover:bg-nube"
                                            title="Abrir propuesta"
                                            aria-label="Abrir propuesta"
                                        >
                                            <ExternalLink className="h-[18px] w-[18px]" aria-hidden />
                                        </a>
                                    )}
                                </RowActions>
                            </td>
                        </tr>
                    ))}
                </DataTable>

                <AdminPagination meta={quotationOrders} onPageChange={(page) => visit({ page })} />
            </div>
        </AdminLayout>
    );
}
