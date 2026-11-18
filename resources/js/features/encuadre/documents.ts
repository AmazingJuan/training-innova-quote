/** Same limits as the server (App\Support\QuotationAttachment), to tell the client before sending. */
export const MAX_FILES = 5;
export const MAX_BYTES = 10 * 1024 * 1024;
export const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];

/** For the file picker: only the accepted types are offered. */
export const ACCEPT = EXTENSIONS.map((ext) => `.${ext}`).join(',');

const extensionOf = (file: File) => file.name.split('.').pop()?.toLowerCase() ?? '';

export const formatSize = (bytes: number) =>
    bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toLocaleString('es-CO', { maximumFractionDigits: 1 })} MB`;

/** Why these files can't be sent, or null. */
export function filesError(files: File[]): string | null {
    if (files.length > MAX_FILES) return `Adjunte máximo ${MAX_FILES} documentos.`;
    const wrongType = files.find((f) => !EXTENSIONS.includes(extensionOf(f)));
    if (wrongType) return `«${wrongType.name}» no es de un tipo permitido.`;
    const tooBig = files.find((f) => f.size > MAX_BYTES);
    if (tooBig) return `«${tooBig.name}» pesa más de ${formatSize(MAX_BYTES)}.`;
    return null;
}
