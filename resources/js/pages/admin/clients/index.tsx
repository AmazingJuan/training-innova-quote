import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { AdminPagination } from '@/components/admin/admin-pagination';
import { AdminTableToolbar } from '@/components/admin/admin-table-toolbar';
import { DataTable } from '@/components/admin/data-table';
import { FilterSelect } from '@/components/admin/filter-select';
import { adminListShellClass, adminOutlineButtonClass, adminTableTdClass } from '@/components/admin/styles';
import type { ClientFilters, ClientWithStats } from '@/features/clients/types';
import { useClientsExport } from '@/features/clients/use-clients-export';
import { useListFilters } from '@/hooks/use-list-filters';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate, orDash } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps, Paginated } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Loader2 } from 'lucide-react';

type IndexPageProps = PageProps<{
    viewData: {
        clients: Paginated<ClientWithStats>;
        filters: ClientFilters;
        options: { companies: string[]; businessUnits: { name: string; display_name: string }[] };
    };
}>;

const toOptions = (values: string[]) => values.map((value) => ({ value, label: value }));

export default function Index() {
    const { clients, filters, options } = usePage<IndexPageProps>().props.viewData;
    const visit = useListFilters('dashboard.clients.index', filters);
    const exporter = useClientsExport(filters);
    const isFiltered = Boolean(filters.search || filters.company || filters.business_unit);

    return (
        <AdminLayout title="Clientes">
            <div className={adminListShellClass}>
                <AdminPageHeader title="Clientes" description="Personas que han solicitado cotizaciones, con su historial.">
                    <button
                        type="button"
                        onClick={exporter.start}
                        disabled={exporter.exporting}
                        className={cn(
                            adminOutlineButtonClass,
                            'inline-flex items-center justify-center gap-2 text-sm disabled:cursor-wait disabled:opacity-70',
                        )}
                    >
                        {exporter.exporting ? (
                            <Loader2 className="h-4 w-4 animate-spin" aria-hidden />
                        ) : (
                            <FileSpreadsheet className="h-4 w-4 text-emerald-600" aria-hidden />
                        )}
                        {exporter.exporting ? 'Generando…' : isFiltered ? 'Exportar resultados' : 'Exportar a Excel'}
                    </button>
                </AdminPageHeader>

                {exporter.error && (
                    <p role="alert" className="rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {exporter.error}
                    </p>
                )}

                <AdminTableToolbar search={filters.search} onSearch={(search) => visit({ search })} searchPlaceholder="Nombre, correo o empresa…">
                    <FilterSelect
                        id="company-filter"
                        label="Empresa"
                        value={filters.company}
                        onChange={(company) => visit({ company })}
                        allLabel="Todas"
                        options={toOptions(options.companies)}
                    />
                    <FilterSelect
                        id="unit-filter"
                        label="Unidad de negocio"
                        value={filters.business_unit}
                        onChange={(business_unit) => visit({ business_unit })}
                        allLabel="Todas"
                        options={options.businessUnits.map((unit) => ({ value: unit.name, label: unit.display_name }))}
                    />
                </AdminTableToolbar>

                <DataTable
                    columns={[
                        { label: 'Cliente' },
                        { label: 'Empresa' },
                        { label: 'Teléfono', className: 'hidden md:table-cell' },
                        { label: 'Cotizaciones', align: 'center' },
                        { label: 'Última', className: 'hidden md:table-cell' },
                    ]}
                    minWidth="24rem"
                    isEmpty={clients.data.length === 0}
                    emptyMessage={isFiltered ? 'No hay clientes que coincidan con los filtros.' : 'Aún no hay clientes con cotizaciones registradas.'}
                >
                    {clients.data.map((client) => (
                        <tr key={client.id} className="hover:bg-nube/70">
                            <td className={adminTableTdClass}>
                                <Link
                                    href={route('dashboard.clients.show', client.id)}
                                    className="block font-medium text-tinta hover:text-azul-hondo hover:underline"
                                >
                                    {orDash(client.name)}
                                </Link>
                                <span className="block text-xs text-pizarra">{client.email}</span>
                            </td>
                            <td className={adminTableTdClass}>
                                {orDash(client.company)}
                                {client.role && <span className="block text-xs text-pizarra">{client.role}</span>}
                            </td>
                            <td className={cn(adminTableTdClass, 'hidden whitespace-nowrap md:table-cell')}>{orDash(client.phone)}</td>
                            <td className={cn(adminTableTdClass, 'text-center tabular-nums')}>
                                <span className="font-semibold text-tinta">{client.quotations_count}</span>
                                {client.pending_count > 0 && (
                                    <span className="block text-xs text-amber-700">
                                        {client.pending_count} pendiente{client.pending_count === 1 ? '' : 's'}
                                    </span>
                                )}
                            </td>
                            <td className={cn(adminTableTdClass, 'hidden whitespace-nowrap tabular-nums md:table-cell')}>
                                {formatDate(client.last_quotation_at)}
                            </td>
                        </tr>
                    ))}
                </DataTable>

                <AdminPagination meta={clients} onPageChange={(page) => visit({ page })} />
            </div>
        </AdminLayout>
    );
}
