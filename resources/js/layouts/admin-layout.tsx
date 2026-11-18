import { BrandLogo } from '@/components/brand/brand-logo';
import FlashAlert from '@/components/ui/flashalert';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Contact,
    ExternalLink,
    LogOut,
    Mail,
    Menu,
    ScrollText,
    Settings,
    Users,
    Workflow,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

type NavItem = { routeName: string; label: string; icon: LucideIcon; description: string };
type NavGroup = { label: string; items: NavItem[] };

/** Single source for the sidebar and the dashboard shortcuts. */
export const adminNavigation: NavGroup[] = [
    {
        label: 'Operación',
        items: [
            {
                routeName: 'dashboard.quotation-orders.index',
                label: 'Cotizaciones',
                icon: ScrollText,
                description: 'Solicitudes recibidas y su estado',
            },
            { routeName: 'dashboard.clients.index', label: 'Clientes', icon: Contact, description: 'Quién ha cotizado y su historial' },
        ],
    },
    {
        label: 'Catálogo',
        items: [
            {
                routeName: 'dashboard.business-unit.index',
                label: 'Unidades de negocio',
                icon: BriefcaseBusiness,
                description: 'Unidades y su código de cotización',
            },
            {
                routeName: 'dashboard.business-unit.decision-tree',
                label: 'Árbol de decisión',
                icon: Workflow,
                description: 'Preguntas del cotizador',
            },
        ],
    },
    {
        label: 'Sistema',
        items: [
            { routeName: 'dashboard.users.index', label: 'Usuarios', icon: Users, description: 'Cuentas con acceso al panel' },
            {
                routeName: 'dashboard.welcome-broadcast.create',
                label: 'Correos de bienvenida',
                icon: Mail,
                description: 'Invitar clientes a la plataforma',
            },
            { routeName: 'dashboard.configurations.edit', label: 'Configuración', icon: Settings, description: 'Notificaciones y datos de contacto' },
        ],
    },
];

/** Active when the current URL is the item's path or nested below it. */
function isActive(currentPath: string, routeName: string): boolean {
    const path = new URL(route(routeName)).pathname;
    return currentPath === path || currentPath.startsWith(`${path}/`);
}

function Sidebar({ currentPath, onNavigate }: { currentPath: string; onNavigate: () => void }) {
    const { auth } = usePage<PageProps>().props;

    return (
        // The whole column scrolls as one piece (with no visible bar) only when the screen is too short for it.
        <div className="flex min-h-full flex-col">
            <Link href={route('dashboard')} onClick={onNavigate} className="block px-6 pb-4 pt-4">
                <BrandLogo inverse />
                <span className="mt-1.5 block font-display text-sm font-semibold text-white/90">Panel de cotizaciones</span>
            </Link>

            <nav className="flex-1 space-y-3 px-3 pb-3 pt-1" aria-label="Administración">
                {adminNavigation.map((group) => (
                    <div key={group.label}>
                        <p className="px-3 pb-1 text-xs font-semibold text-white/90">{group.label}</p>
                        <ul className="space-y-0.5">
                            {group.items.map(({ routeName, label, icon: Icon }) => {
                                const active = isActive(currentPath, routeName);
                                return (
                                    <li key={routeName}>
                                        <Link
                                            href={route(routeName)}
                                            onClick={onNavigate}
                                            aria-current={active ? 'page' : undefined}
                                            className={cn(
                                                'flex items-center gap-3 rounded-control px-3 py-1.5 font-display text-sm font-semibold transition-colors',
                                                active ? 'bg-white text-azul' : 'text-white hover:bg-white/10',
                                            )}
                                        >
                                            <Icon className={cn('h-4 w-4 shrink-0', active ? 'text-azul' : 'text-white/90')} aria-hidden />
                                            {label}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="space-y-0.5 border-t border-white/25 px-3 py-2">
                <a
                    href={route('home')}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-3 rounded-control px-3 py-1.5 font-display text-sm font-semibold text-white transition-colors hover:bg-white/10"
                >
                    <ExternalLink className="h-4 w-4 text-white/90" aria-hidden />
                    Ver sitio público
                </a>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex w-full items-center gap-3 rounded-control px-3 py-1.5 text-left font-display text-sm font-semibold text-white transition-colors hover:bg-white/10"
                >
                    <LogOut className="h-4 w-4 text-white/90" aria-hidden />
                    <span className="min-w-0 flex-1">
                        Cerrar sesión
                        {auth.user && <span className="block truncate font-sans text-xs font-normal text-white/90">{auth.user.email}</span>}
                    </span>
                </Link>
            </div>
        </div>
    );
}

type AdminLayoutProps = {
    children: ReactNode;
    /** Browser tab title. */
    title?: string;
};

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    const [sidebarOpen, setSidebarOpen] = useState(false);

    useEffect(() => {
        if (!sidebarOpen) return;
        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && setSidebarOpen(false);
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [sidebarOpen]);

    return (
        <div className="min-h-screen bg-nube text-tinta">
            {title && <Head title={title} />}

            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-white focus:px-4 focus:py-2 focus:shadow"
            >
                Saltar al contenido
            </a>

            {/* Desktop sidebar, in the brand blue of the public site's panels. */}
            <aside className="field-azul scrollbar-none fixed inset-y-0 left-0 z-30 hidden w-64 overflow-y-auto overscroll-contain lg:block">
                <Sidebar currentPath={currentPath} onNavigate={() => undefined} />
            </aside>

            {/* Mobile drawer */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Menú">
                    <button type="button" className="absolute inset-0 bg-tinta/50" aria-label="Cerrar menú" onClick={() => setSidebarOpen(false)} />
                    <aside className="field-azul scrollbar-none animate-slide-in-left relative h-full w-72 max-w-[85vw] overflow-y-auto overscroll-contain shadow-xl">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(false)}
                            className="absolute right-3 top-5 rounded-control p-1.5 text-white hover:bg-white/10"
                            aria-label="Cerrar menú"
                        >
                            <X className="h-5 w-5" aria-hidden />
                        </button>
                        <Sidebar currentPath={currentPath} onNavigate={() => setSidebarOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="flex min-h-screen min-w-0 flex-col lg:pl-64">
                <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-linea bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-control p-2 text-tinta hover:bg-nube"
                        aria-label="Abrir menú"
                        aria-expanded={sidebarOpen}
                    >
                        <Menu className="h-5 w-5" aria-hidden />
                    </button>
                    <Link href={route('dashboard')} className="font-display text-sm font-bold text-azul">
                        Panel de cotizaciones
                    </Link>
                </header>

                <main id="contenido" className="w-full min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
                    <div className="mx-auto mb-5 w-full max-w-7xl empty:hidden">
                        <FlashAlert />
                    </div>
                    {children}
                </main>

                <footer className="border-t border-linea px-4 py-5 text-center text-xs text-pizarra sm:px-6 lg:px-10">
                    © {new Date().getFullYear()} Training Corporation. Todos los derechos reservados.
                </footer>
            </div>
        </div>
    );
}
