import AdminLayout, { useAdminNavigation } from '@/layouts/admin-layout';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const firstName = auth.user?.name.split(' ')[0];
    const navigation = useAdminNavigation();

    return (
        <AdminLayout title="Panel">
            <div className="mx-auto w-full max-w-7xl space-y-10">
                <header>
                    <h1 className="font-display text-[clamp(1.75rem,1.35rem+1.2vw,2.5rem)] font-extrabold tracking-tight text-azul">
                        {firstName ? `Bienvenido/a, ${firstName}` : 'Bienvenido/a'}
                    </h1>
                    <p className="mt-2 max-w-2xl text-pizarra">
                        Gestione las solicitudes de cotización, el catálogo que ve el cliente y la configuración del sistema.
                    </p>
                </header>

                {navigation.map((group) => (
                    <section key={group.label} aria-labelledby={`group-${group.label}`}>
                        <h2 id={`group-${group.label}`} className="mb-3 text-sm font-semibold text-pizarra">
                            {group.label}
                        </h2>
                        <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {group.items.map(({ routeName, label, description, icon: Icon }) => (
                                <li key={routeName}>
                                    <Link
                                        href={route(routeName)}
                                        className="hover: group flex h-full items-start gap-4 rounded-[10px] border-2 border-linea bg-white p-5 transition hover:border-linea"
                                    >
                                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-control bg-nube text-pizarra transition-colors group-hover:bg-nube group-hover:text-azul-hondo">
                                            <Icon className="h-5 w-5" aria-hidden />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-semibold text-tinta">{label}</span>
                                            <span className="mt-0.5 block text-sm leading-relaxed text-pizarra">{description}</span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AdminLayout>
    );
}
