import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { AdminPagination } from '@/components/admin/admin-pagination';
import { AdminTableToolbar } from '@/components/admin/admin-table-toolbar';
import { DataTable } from '@/components/admin/data-table';
import { DeleteAction, EditAction, RowActions } from '@/components/admin/row-actions';
import { adminListShellClass, adminPrimaryButtonClass, adminTableTdClass } from '@/components/admin/styles';
import { useListFilters } from '@/hooks/use-list-filters';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type { PageProps, Paginated, SearchFilters, UserRole } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

type UserRow = { id: number; name: string; email: string; phone: string | null; role: UserRole };

const roleLabels: Record<UserRole, string> = { admin: 'Administrador', user: 'Usuario' };

type IndexPageProps = PageProps<{
    viewData: { users: Paginated<UserRow>; filters: SearchFilters };
}>;

export default function Index() {
    const { auth, viewData } = usePage<IndexPageProps>().props;
    const { users, filters } = viewData;
    const visit = useListFilters('dashboard.users.index', filters);

    return (
        <AdminLayout title="Usuarios">
            <div className={adminListShellClass}>
                <AdminPageHeader title="Usuarios" description="Cuentas con acceso al panel de administración.">
                    <Link
                        href={route('dashboard.users.create')}
                        className={cn(adminPrimaryButtonClass, 'inline-flex items-center justify-center gap-2 text-sm')}
                    >
                        <Plus className="h-4 w-4" aria-hidden />
                        Nuevo usuario
                    </Link>
                </AdminPageHeader>

                <AdminTableToolbar search={filters.search} onSearch={(search) => visit({ search })} searchPlaceholder="Nombre o correo…" />

                <DataTable
                    columns={[
                        { label: 'ID' },
                        { label: 'Nombre' },
                        { label: 'Correo' },
                        { label: 'Teléfono' },
                        { label: 'Rol' },
                        { label: 'Acciones', align: 'center' },
                    ]}
                    isEmpty={users.data.length === 0}
                    emptyMessage={filters.search ? 'No hay resultados para su búsqueda.' : 'No hay usuarios registrados.'}
                >
                    {users.data.map((user) => (
                        <tr key={user.id} className="hover:bg-nube/70">
                            <td className={cn(adminTableTdClass, 'tabular-nums text-pizarra')}>{user.id}</td>
                            <td className={cn(adminTableTdClass, 'font-medium text-tinta')}>
                                {user.name}
                                {auth.user?.id === user.id && <span className="ml-2 text-xs font-normal text-pizarra">(usted)</span>}
                            </td>
                            <td className={adminTableTdClass}>{user.email}</td>
                            <td className={adminTableTdClass}>{user.phone ?? '—'}</td>
                            <td className={adminTableTdClass}>{roleLabels[user.role]}</td>
                            <td className={adminTableTdClass}>
                                <RowActions>
                                    <EditAction href={route('dashboard.users.edit', user.id)} />
                                    {auth.user?.id !== user.id && (
                                        <DeleteAction
                                            href={route('dashboard.users.delete', user.id)}
                                            confirmMessage={`¿Eliminar al usuario ${user.name}?`}
                                        />
                                    )}
                                </RowActions>
                            </td>
                        </tr>
                    ))}
                </DataTable>

                <AdminPagination meta={users} onPageChange={(page) => visit({ page })} />
            </div>
        </AdminLayout>
    );
}
