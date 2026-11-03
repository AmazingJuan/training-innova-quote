import { AdminPageHeader } from '@/components/admin/admin-page-header';
import { BackLink } from '@/components/admin/back-link';
import { DetailList } from '@/components/admin/detail-list';
import { FormField } from '@/components/admin/form';
import { QuotationStatusBadge, StatusBadge } from '@/components/admin/status-badge';
import { adminFieldInputClass, adminListShellClass, adminOutlineButtonClass, adminPrimaryButtonClass } from '@/components/admin/styles';
import { Modal } from '@/components/ui/modal';
import { quotationTitle, type QuotationOrder } from '@/features/quotations/types';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate, orDash } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Upload } from 'lucide-react';
import { useState } from 'react';

function UploadProposalModal({ order, open, onOpenChange }: { order: QuotationOrder; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ quotation_url: '' });

    const close = (next: boolean) => {
        if (!next) {
            reset();
            clearErrors();
        }
        onOpenChange(next);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(route('dashboard.quotation-orders.upload-quotation-url', order.id), { onSuccess: () => close(false) });
    };

    return (
        <Modal
            open={open}
            onOpenChange={close}
            title="Enviar propuesta al cliente"
            description="Pegue el enlace público del documento. El cliente recibirá un correo con un acceso seguro."
        >
            <form onSubmit={submit} noValidate className="space-y-6">
                <FormField id="quotation_url" label="URL de la propuesta" required error={errors.quotation_url}>
                    <input
                        type="url"
                        value={data.quotation_url}
                        onChange={(e) => setData('quotation_url', e.target.value)}
                        placeholder="https://…/propuesta.pdf"
                        className={adminFieldInputClass}
                        autoFocus
                    />
                </FormField>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" onClick={() => close(false)} className={adminOutlineButtonClass}>
                        Cancelar
                    </button>
                    <button type="submit" disabled={processing || !data.quotation_url.trim()} className={adminPrimaryButtonClass}>
                        {processing ? 'Enviando…' : 'Guardar y notificar'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function Show() {
    const { quotationOrder: order } = usePage<PageProps<{ viewData: { quotationOrder: QuotationOrder } }>>().props.viewData;
    const [uploadOpen, setUploadOpen] = useState(false);
    const client = order.client;
    const answers = Object.entries(order.answers ?? {});

    return (
        <AdminLayout title={quotationTitle(order)}>
            <div className={adminListShellClass}>
                <BackLink href={route('dashboard.quotation-orders.index')} label="Cotizaciones" />

                <AdminPageHeader
                    title={quotationTitle(order)}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            Recibida el {formatDate(order.created_at)}
                            <QuotationStatusBadge generated={order.is_generated} />
                            {order.is_generated &&
                                (order.viewed_by_client ? (
                                    <StatusBadge tone="info">Vista por el cliente</StatusBadge>
                                ) : (
                                    <StatusBadge tone="neutral">Aún no vista</StatusBadge>
                                ))}
                        </span>
                    }
                >
                    {order.quotation_url ? (
                        <a
                            href={order.quotation_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(adminOutlineButtonClass, 'inline-flex items-center justify-center gap-2 text-sm')}
                        >
                            <ExternalLink className="h-4 w-4" aria-hidden />
                            Abrir propuesta
                        </a>
                    ) : (
                        <button type="button" onClick={() => setUploadOpen(true)} className={cn(adminPrimaryButtonClass, 'gap-2')}>
                            <Upload className="h-4 w-4" aria-hidden />
                            Enviar propuesta
                        </button>
                    )}
                </AdminPageHeader>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div className="space-y-5 lg:col-span-3">
                        <DetailList
                            title="Solicitud"
                            items={[
                                { label: 'Unidad de negocio', value: order.business_unit },
                                // Requests made before the encuadre picked services from the catalog.
                                ...(order.services?.length
                                    ? [
                                          { label: 'Línea de gestión', value: orDash(order.gestion_line) },
                                          {
                                              label: 'Servicios',
                                              value: (
                                                  <ul className="space-y-1">
                                                      {order.services.map((service) => (
                                                          <li key={service}>{service}</li>
                                                      ))}
                                                  </ul>
                                              ),
                                          },
                                      ]
                                    : []),
                            ]}
                        />

                        <DetailList
                            title={`Respuestas (${answers.length})`}
                            items={
                                answers.length > 0
                                    ? answers.map(([question, answer]) => ({ label: question, value: answer }))
                                    : [{ label: 'Respuestas', value: <span className="text-pizarra">Sin respuestas registradas.</span> }]
                            }
                        />
                    </div>

                    <div className="space-y-5 lg:col-span-2">
                        <DetailList
                            title="Cliente"
                            items={
                                client
                                    ? [
                                          {
                                              label: 'Nombre',
                                              value: (
                                                  <Link
                                                      href={route('dashboard.clients.show', client.id)}
                                                      className="font-medium text-azul-hondo hover:underline"
                                                  >
                                                      {client.name}
                                                  </Link>
                                              ),
                                          },
                                          {
                                              label: 'Correo',
                                              value: (
                                                  <a href={`mailto:${client.email}`} className="hover:underline">
                                                      {client.email}
                                                  </a>
                                              ),
                                          },
                                          { label: 'Teléfono', value: orDash(client.phone) },
                                          { label: 'Cargo', value: orDash(client.role) },
                                          { label: 'Razón social', value: orDash(client.company) },
                                          { label: 'NIT', value: orDash(client.nit) },
                                          { label: 'Actividad económica', value: orDash(client.economic_activity) },
                                          { label: 'Dirección', value: orDash(client.address) },
                                      ]
                                    : [{ label: 'Cliente', value: <span className="text-pizarra">Sin datos de contacto.</span> }]
                            }
                        />
                    </div>
                </div>
            </div>

            <UploadProposalModal order={order} open={uploadOpen} onOpenChange={setUploadOpen} />
        </AdminLayout>
    );
}
