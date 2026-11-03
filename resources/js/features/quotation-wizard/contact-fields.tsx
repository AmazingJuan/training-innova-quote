import { corporate } from '@/components/public/links';
import { cn } from '@/lib/utils';
import type { ContactData } from './types';

export type ContactErrors = Partial<Record<keyof ContactData | 'privacy_accepted', string>>;

type Field = {
    key: keyof ContactData;
    label: string;
    type: string;
    autoComplete: string;
    required: boolean;
    placeholder?: string;
    hint?: string;
    wide?: boolean;
};

// The client data of the "formato de encuadre": first the organization, then who receives the proposal.
const ORGANIZATION: Field[] = [
    {
        key: 'company',
        label: 'Razón social',
        type: 'text',
        autoComplete: 'organization',
        required: true,
        placeholder: 'Ej.: Construcciones Andinas S.A.S.',
        wide: true,
    },
    { key: 'nit', label: 'NIT', type: 'text', autoComplete: 'off', required: true, placeholder: 'Ej.: 900123456-7' },
    {
        key: 'economic_activity',
        label: 'Actividad económica',
        type: 'text',
        autoComplete: 'off',
        required: true,
        placeholder: 'Ej.: construcción de obras civiles',
    },
    {
        key: 'address',
        label: 'Dirección',
        type: 'text',
        autoComplete: 'street-address',
        required: true,
        placeholder: 'Ej.: Cra. 50 # 10-20, Medellín',
        wide: true,
    },
];

const PERSON: Field[] = [
    { key: 'name', label: 'Nombre del contacto', type: 'text', autoComplete: 'name', required: true },
    { key: 'role', label: 'Cargo', type: 'text', autoComplete: 'organization-title', required: true, placeholder: 'Ej.: Coordinadora HSEQ' },
    {
        key: 'email',
        label: 'Correo electrónico',
        type: 'email',
        autoComplete: 'email',
        required: true,
        placeholder: 'nombre@empresa.com',
        hint: 'A este correo se enviará la propuesta.',
    },
    { key: 'phone', label: 'Teléfono', type: 'tel', autoComplete: 'tel', required: true, placeholder: 'Ej.: 300 123 4567' },
];

type ContactFieldsProps = {
    data: ContactData;
    errors: ContactErrors;
    onChange: (field: keyof ContactData, value: string) => void;
};

export const CONTACT_GROUPS = [
    { title: 'Datos de la organización', fields: ORGANIZATION },
    { title: 'Persona de contacto', fields: PERSON },
];

/** The client data of the encuadre, in two groups: the organization and the person who receives the proposal. */
export function ContactFields({ data, errors, onChange }: ContactFieldsProps) {
    return (
        <div className="grid gap-10">
            {CONTACT_GROUPS.map((group) => (
                <fieldset key={group.title} className="min-w-0">
                    <legend className="mb-5 font-display text-lg font-bold text-tinta">{group.title}</legend>
                    <div className="grid grid-cols-1 gap-x-5 gap-y-6 sm:grid-cols-2">
                        {group.fields.map((field) => {
                            const id = `contact-${field.key}`;
                            const error = errors[field.key];
                            return (
                                <div key={field.key} className={cn('scroll-mt-28', field.wide && 'sm:col-span-2')}>
                                    <label htmlFor={id} className="label">
                                        {field.label} {!field.required && <span className="label-optional">(opcional)</span>}
                                    </label>
                                    <input
                                        id={id}
                                        type={field.type}
                                        autoComplete={field.autoComplete}
                                        required={field.required}
                                        maxLength={field.key === 'nit' || field.key === 'phone' ? 30 : 255}
                                        value={data[field.key]}
                                        onChange={(e) => onChange(field.key, e.target.value)}
                                        placeholder={field.placeholder}
                                        aria-invalid={error ? true : undefined}
                                        aria-describedby={[field.hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined}
                                        className="control"
                                    />
                                    {field.hint && (
                                        <p id={`${id}-hint`} className="mt-2 text-sm text-pizarra">
                                            {field.hint}
                                        </p>
                                    )}
                                    {error && (
                                        <p id={`${id}-error`} className="field-error mt-2">
                                            {error}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </fieldset>
            ))}
        </div>
    );
}

/** Authorization to process the data, next to the send button. */
export function PrivacyConsent({ accepted, error, onChange }: { accepted: boolean; error?: string; onChange: (accepted: boolean) => void }) {
    return (
        <div id="privacy" className="scroll-mt-28">
            <label className="flex cursor-pointer items-start gap-3 text-base">
                <input
                    type="checkbox"
                    checked={accepted}
                    onChange={(e) => onChange(e.target.checked)}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? 'privacy-error' : undefined}
                    className={cn(
                        'mt-1 h-5 w-5 shrink-0 rounded border-2 border-gris-marca text-azul focus:ring-0 focus:ring-offset-0',
                        error && 'border-error',
                    )}
                />
                <span>
                    Autorizo el tratamiento de mis datos para responder esta solicitud, partiendo del {' '}
                    <a href={corporate.privacy} target="_blank" rel="noopener noreferrer" classNsegún lame="link">
                        manual de protección de datos personales
                    </a>
                    .
                </span>
            </label>
            {error && (
                <p id="privacy-error" className="field-error mt-2 pl-8">
                    {error}
                </p>
            )}
        </div>
    );
}

/** Same rules as the server, to fail fast without a round trip. */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE = /^[0-9+\s().-]{7,30}$/;

export function validateContact(data: ContactData, privacyAccepted: boolean): ContactErrors {
    const errors: ContactErrors = {};
    if (!data.company.trim()) errors.company = 'Indique la razón social de la organización.';
    if (!data.nit.trim()) errors.nit = 'Indique el NIT de la organización.';
    if (!data.economic_activity.trim()) errors.economic_activity = 'Indique la actividad económica de la organización.';
    if (!data.address.trim()) errors.address = 'Indique la dirección de la organización.';
    if (!data.name.trim()) errors.name = 'Indique el nombre del contacto.';
    if (!data.role.trim()) errors.role = 'Indique el cargo del contacto.';
    if (!data.email.trim()) errors.email = 'Indique el correo electrónico del contacto.';
    else if (!EMAIL.test(data.email.trim())) errors.email = 'El correo electrónico debe tener la forma nombre@empresa.com.';
    if (!data.phone.trim()) errors.phone = 'Indique un teléfono de contacto.';
    else if (!PHONE.test(data.phone.trim())) errors.phone = 'El teléfono solo puede contener números, espacios y los signos + ( ) - .';
    if (!privacyAccepted) errors.privacy_accepted = 'Debe autorizar el tratamiento de sus datos para enviar la solicitud.';
    return errors;
}
