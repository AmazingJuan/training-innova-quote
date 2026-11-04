import { FormActions, FormCard, FormField } from '@/components/admin/form';
import { adminFieldInputClass } from '@/components/admin/styles';
import { PasswordInput } from '@/components/password-input';
import { useForm } from '@inertiajs/react';

export type UserFormData = { name: string; email: string; phone: string; password: string };

type UserFormProps = {
    initial?: Omit<UserFormData, 'password'>;
    /** Present when editing. */
    userId?: number;
};

export function UserForm({ initial, userId }: UserFormProps) {
    const isEdit = userId !== undefined;
    const { data, setData, post, put, processing, errors } = useForm<UserFormData>({
        name: initial?.name ?? '',
        email: initial?.email ?? '',
        phone: initial?.phone ?? '',
        password: '',
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
            </div>
            <FormActions
                processing={processing}
                submitLabel={isEdit ? 'Guardar cambios' : 'Crear usuario'}
                cancelHref={route('dashboard.users.index')}
            />
        </FormCard>
    );
}
