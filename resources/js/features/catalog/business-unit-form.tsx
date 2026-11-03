import { FormActions, FormCard, FormField } from '@/components/admin/form';
import { adminFieldInputClass } from '@/components/admin/styles';
import type { BusinessUnit } from '@/features/catalog/types';
import { useForm } from '@inertiajs/react';

export function BusinessUnitForm({ businessUnit }: { businessUnit?: BusinessUnit }) {
    const { data, setData, post, put, processing, errors } = useForm({
        display_name: businessUnit?.display_name ?? '',
        abbreviation: businessUnit?.abbreviation ?? '',
    });

    const submit = () =>
        businessUnit ? put(route('dashboard.business-unit.update', businessUnit.id)) : post(route('dashboard.business-unit.store'));

    return (
        <FormCard onSubmit={submit}>
            <FormField
                id="display_name"
                label="Nombre"
                required
                error={errors.display_name}
                hint="También define la dirección pública del cotizador para esta unidad."
            >
                <input
                    type="text"
                    value={data.display_name}
                    onChange={(e) => setData('display_name', e.target.value)}
                    className={adminFieldInputClass}
                    placeholder="Ej.: Consultoría"
                />
            </FormField>

            <FormField
                id="abbreviation"
                label="Abreviado"
                required
                error={errors.abbreviation}
                hint="Letras y números, sin espacios. Prefijo de los códigos de cotización (ej. CNN-V-2601)."
            >
                <input
                    type="text"
                    value={data.abbreviation}
                    onChange={(e) => setData('abbreviation', e.target.value.toUpperCase().replace(/\s+/g, ''))}
                    className={`${adminFieldInputClass} max-w-xs font-display font-semibold uppercase`}
                    placeholder="CNN"
                    maxLength={32}
                />
            </FormField>

            {businessUnit && (
                <div className="rounded-control bg-nube px-4 py-3 text-sm text-tinta ring-1 ring-inset ring-linea">
                    <span className="font-semibold text-tinta">Correlativo actual: </span>
                    <SequenceLabel unit={businessUnit} />
                    <span className="text-pizarra"> · solo lectura</span>
                </div>
            )}

            <FormActions
                processing={processing}
                submitLabel={businessUnit ? 'Guardar cambios' : 'Crear unidad'}
                cancelHref={route('dashboard.business-unit.index')}
            />
        </FormCard>
    );
}

/** "CNN-V-2607" style preview of the last code issued this year. */
export function SequenceLabel({ unit }: { unit: BusinessUnit }) {
    if (unit.quotation_seq_year == null) {
        return <span className="text-pizarra">sin cotizaciones aún</span>;
    }

    const year = String(unit.quotation_seq_year).padStart(2, '0');
    const value = String(unit.quotation_seq_value ?? 0).padStart(2, '0');

    return <span className="font-display font-semibold">{`${unit.abbreviation}-V-${year}${value}`}</span>;
}
