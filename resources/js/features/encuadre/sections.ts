/** The blocks of the "formato de encuadre", in the order the client goes through them. */
export const SECTIONS = [
    {
        key: 'necesidad',
        title: 'Identificación de la necesidad',
        short: 'Necesidad',
        hint: 'Qué servicio requiere y cuáles son sus características.',
    },
    {
        key: 'condiciones',
        title: 'Condiciones de prestación del servicio',
        short: 'Condiciones',
        hint: 'Modalidad, lugar, horario, fecha y logística.',
    },
    {
        key: 'cotizacion',
        title: 'Información para la cotización',
        short: 'Cotización',
        hint: 'Insumos técnicos y económicos para estructurar la oferta.',
    },
    {
        key: 'cierre',
        title: 'Validación y cierre de la solicitud',
        short: 'Cierre',
        hint: 'Cómo resolver dudas antes de preparar la propuesta y cuándo espera recibirla.',
    },
] as const;

export type SectionKey = (typeof SECTIONS)[number]['key'];

/** "Validación de requisitos" was its own block; questions saved with it belong to the closing one now. */
export const sectionKey = (key: string | null) => (key === 'validacion' ? 'cierre' : key);

export const sectionOf = (key: string | null) => SECTIONS.find((s) => s.key === sectionKey(key)) ?? null;
