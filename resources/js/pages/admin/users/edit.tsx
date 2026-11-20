import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { BackLink } from '@/components/admin/back-link';
import { adminFormShellClass } from '@/components/admin/styles';
import { UserForm } from '@/features/users/user-form';
import AdminLayout from '@/layouts/admin-layout';
import type { PageProps, RoleOption, UserRole } from '@/types';
import { usePage } from '@inertiajs/react';

type EditPageProps = PageProps<{
    viewData: { user: { id: number; name: string; email: string; phone: string; role: UserRole }; roles: RoleOption[] };
}>;

export default function Edit() {
    const { user, roles } = usePage<EditPageProps>().props.viewData;

    return (
        <AdminLayout title="Editar usuario">
            <div className={adminFormShellClass}>
                <BackLink href={route('dashboard.users.index')} label="Usuarios" />
                <AdminPageHeader title="Editar usuario" description={user.email} />
                <UserForm roles={roles} userId={user.id} initial={user} />
            </div>
        </AdminLayout>
    );
}
