import { FormActions, FormCard, FormField } from '@/components/admin/form';
import { adminFieldInputClass } from '@/components/admin/styles';
import { PasswordInput } from '@/components/password-input';
import { cn } from '@/lib/utils';
import type { RoleOption, UserRole } from '@/types';
import { useForm } from '@inertiajs/react';

export type UserFormData = { name: string; email: string; phone: string; password: string; role: UserRole };

type UserFormProps = {
    roles: RoleOption[];
    initial?: Omit<UserFormData, 'password'>;
    /** Present when editing. */
    userId?: number;
};

export function UserForm({ roles, initial, userId }: UserFormProps) {
    const isEdit = userId !== undefined;
    const { data, setData, post, put, processing, errors } = useForm<UserFormData>({
        name: initial?.name ?? '',
        email: initial?.email ?? '',
        phone: initial?.phone ?? '',
        password: '',
        role: initial?.role ?? 'user',
    });

    const submit = () => (isEdit ? put(route('dashboard.users.update', userId)) : post(route('dashboard.users.store')));

    return (
        <FormCard onSubmit={submit}>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <FormField id="name" label="Nombre" required error={errors.name}>
                    <input
                        type="text"
                        autoComplete="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={adminFieldInputClass}
                    />
                </FormField>
                <FormField id="email" label="Correo electrónico" required error={errors.email}>
                    <input
                        type="email"
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className={adminFieldInputClass}
                    />
                </FormField>
                <FormField id="phone" label="Teléfono" required error={errors.phone}>
                    <input
                        type="tel"
                        autoComplete="tel"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        className={adminFieldInputClass}
                        placeholder="3001234567"
                    />
                </FormField>
                <FormField
                    id="password"
                    label={isEdit ? 'Nueva contraseña' : 'Contraseña'}
                    required={!isEdit}
                    hint={isEdit ? 'Déjela en blanco para conservar la actual.' : 'Mínimo 8 caracteres.'}
                    error={errors.password}
                >
                    <PasswordInput autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                </FormField>
                <FormField
                    id="role"
                    label="Rol"
                    required
                    hint="Solo los administradores ven y editan los usuarios y sus contraseñas."
                    error={errors.role}
                >
                    <select
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value as UserRole)}
                        className={cn(adminFieldInputClass, 'cursor-pointer')}
                    >
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                </FormField>
            </div>
            <FormActions
                processing={processing}
                submitLabel={isEdit ? 'Guardar cambios' : 'Crear usuario'}
                cancelHref={route('dashboard.users.index')}
            />
        </FormCard>
    );
}
