import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { BackLink } from '@/components/admin/back-link';
import { adminListShellClass, adminOutlineButtonClass } from '@/components/admin/styles';
import { SequenceLabel } from '@/features/catalog/business-unit-form';
import type { BusinessUnit } from '@/features/catalog/types';
import AdminLayout from '@/layouts/admin-layout';
import { capitalize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

type ShowPageProps = PageProps<{
    viewData: { businessUnit: BusinessUnit; questionCount: number; requestCount: number };
}>;

export default function Show() {
    const { businessUnit, questionCount, requestCount } = usePage<ShowPageProps>().props.viewData;

    return (
        <AdminLayout title={businessUnit.display_name}>
            <div className={adminListShellClass}>
                <BackLink href={route('dashboard.business-unit.index')} label="Unidades de negocio" />

                <AdminPageHeader
                    title={capitalize(businessUnit.display_name)}
                    description={
                        <>
                            Abreviado <span className="font-display font-bold">{businessUnit.abbreviation}</span> · último código{' '}
                            <SequenceLabel unit={businessUnit} />
                        </>
                    }
                >
                    <Link
                        href={route('dashboard.business-unit.edit', businessUnit.id)}
                        className={cn(adminOutlineButtonClass, 'inline-flex items-center justify-center gap-2 text-sm')}
                    >
                        <Pencil className="h-4 w-4" aria-hidden />
                        Editar
                    </Link>
                </AdminPageHeader>

                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="rounded-[10px] border-2 border-linea bg-white p-5">
                        <dt className="text-sm text-pizarra">Preguntas del formulario de solicitud</dt>
                        <dd className="mt-1 font-display text-2xl font-bold tabular-nums text-tinta">{questionCount}</dd>
                        <Link
                            href={route('dashboard.business-unit.decision-tree')}
                            className="mt-2 inline-block text-sm text-azul-hondo hover:underline"
                        >
                            Editar el formulario
                        </Link>
                    </div>
                    <div className="rounded-[10px] border-2 border-linea bg-white p-5">
                        <dt className="text-sm text-pizarra">Solicitudes de cotización</dt>
                        <dd className="mt-1 font-display text-2xl font-bold tabular-nums text-tinta">{requestCount}</dd>
                        <Link href={route('dashboard.quotation-orders.index')} className="mt-2 inline-block text-sm text-azul-hondo hover:underline">
                            Ver solicitudes
                        </Link>
                    </div>
                </dl>
            </div>
        </AdminLayout>
    );
}
