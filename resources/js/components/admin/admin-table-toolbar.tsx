import { cn } from '@/lib/utils';
import { Search, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { adminFieldInputClass, adminOutlineButtonClass } from './styles';

type AdminTableToolbarProps = {
    search: string;
    onSearch: (search: string) => void;
    searchPlaceholder?: string;
    /** Extra filters (selects) rendered next to the search box. */
    children?: ReactNode;
    className?: string;
};

export function AdminTableToolbar({ search, onSearch, searchPlaceholder = 'Buscar…', children, className }: AdminTableToolbarProps) {
    const [query, setQuery] = useState(search);
    const [appliedSearch, setAppliedSearch] = useState(search);

    // The search changed from outside (filters cleared, back button): the box follows it.
    if (search !== appliedSearch) {
        setAppliedSearch(search);
        setQuery(search);
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSearch(query.trim());
    };

    return (
        <div className={cn('flex w-full min-w-0 flex-col gap-3 md:flex-row md:items-end md:justify-between', className)}>
            <form role="search" onSubmit={submit} className="flex w-full min-w-0 gap-2 md:max-w-md">
                <div className="relative min-w-0 flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gris-marca" aria-hidden />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={searchPlaceholder}
                        aria-label="Buscar"
                        className={cn(adminFieldInputClass, 'pl-9')}
                    />
                </div>
                <button type="submit" className={cn(adminOutlineButtonClass, 'shrink-0 text-sm')}>
                    Buscar
                </button>
                {search && (
                    <button
                        type="button"
                        onClick={() => onSearch('')}
                        className={cn(adminOutlineButtonClass, 'shrink-0 px-3')}
                        aria-label="Limpiar búsqueda"
                        title="Limpiar búsqueda"
                    >
                        <X className="h-4 w-4" aria-hidden />
                    </button>
                )}
            </form>
            {children && <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-end md:w-auto">{children}</div>}
        </div>
    );
}
