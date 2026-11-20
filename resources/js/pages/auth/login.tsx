import Checkbox from '@/components/checkbox';
import InputError from '@/components/input-error';
import InputLabel from '@/components/input-label';
import { PasswordInput } from '@/components/password-input';
import PrimaryButton from '@/components/primary-button';
import TextInput from '@/components/text-input';
import GuestLayout from '@/layouts/guest-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <h1 className="mb-7 font-display text-[1.75rem] font-extrabold leading-tight tracking-tight text-azul">Panel de cotizaciones</h1>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Contraseña" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) => setData('remember', (e.target.checked || false) as false)}
                        />
                        <span className="ms-2 text-sm text-pizarra">Mantener la sesión iniciada</span>
                    </label>
                </div>

                <p className="mt-4 text-sm text-pizarra">Si olvidó su contraseña, solicite una nueva a Gerencia.</p>

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton disabled={processing}>Iniciar sesión</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
