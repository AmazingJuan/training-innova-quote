import PrimaryButton from '@/components/primary-button';
import GuestLayout from '@/layouts/guest-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar correo" />

            <div className="mb-4 text-sm text-pizarra">
                Gracias por registrarse. Antes de comenzar, verifique su correo electrónico con el enlace que le acabamos de enviar. Si no lo recibió,
                podemos enviarle otro.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    Se envió un nuevo enlace de verificación al correo electrónico que indicó al registrarse.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>Reenviar el correo de verificación</PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-pizarra underline hover:text-tinta focus:ring-2 focus:ring-azul focus:ring-offset-2"
                    >
                        Cerrar sesión
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
