import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { BackLink } from '@/components/admin/back-link';
import { adminFormShellClass } from '@/components/admin/styles';
import { UserForm } from '@/features/users/user-form';
import AdminLayout from '@/layouts/admin-layout';
import type { PageProps, RoleOption } from '@/types';
import { usePage } from '@inertiajs/react';

type CreatePageProps = PageProps<{ viewData: { roles: RoleOption[] } }>;

export default function Create() {
    const { roles } = usePage<CreatePageProps>().props.viewData;

    return (
        <AdminLayout title="Crear usuario">
            <div className={adminFormShellClass}>
                <BackLink href={route('dashboard.users.index')} label="Usuarios" />
                <AdminPageHeader
                    title="Crear usuario"
                    description="Todas las cuentas usan el panel; los administradores además gestionan los usuarios."
                />
                <UserForm roles={roles} />
            </div>
        </AdminLayout>
    );
}
