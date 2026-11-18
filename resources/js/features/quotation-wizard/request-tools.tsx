import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';

type RequestToolsProps = {
    className: string;
    unitName: string;
    kept: boolean;
    onChangeUnit: (event: MouseEvent) => void;
    /** Null while there is nothing to clear. */
    onStartOver: (() => void) | null;
};

/** The request itself, out of the way of the pager: which service it is, whether it is kept, and starting over. */
export function RequestTools({ className, unitName, kept, onChangeUnit, onStartOver }: RequestToolsProps) {
    return (
        <div className={cn('gap-3 text-sm text-pizarra', className)}>
            <p>
                <span className="font-bold text-tinta">Servicio:</span> {unitName}.{' '}
                <Link href={route('quotation.select.business_unit')} onClick={onChangeUnit} className="link">
                    Cambiar tipo de servicio
                </Link>
            </p>
            {kept && <p>Las respuestas se guardan en este navegador mientras diligencia la solicitud.</p>}
            {onStartOver && (
                <p>
                    <button type="button" onClick={onStartOver} className="link">
                        Reiniciar la solicitud
                    </button>
                </p>
            )}
        </div>
    );
}
