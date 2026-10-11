import { Link } from '@inertiajs/react';
export function PageLinks({
    path,
    current,
    last,
    view,
}: {
    path: string;
    current: number;
    last: number;
    view?: string;
}) {
    const href = (page: number) =>
        `${path}?page=${page}${view ? `&view=${view}` : ''}`;

    return (
        <nav
            aria-label="Paginación"
            className="flex flex-wrap items-center gap-4"
        >
            {current > 1 && (
                <Link
                    className="inline-flex min-h-11 items-center underline"
                    href={href(current - 1)}
                >
                    Anterior
                </Link>
            )}
            <span>
                Página {current} de {last}
            </span>
            {current < last && (
                <Link
                    className="inline-flex min-h-11 items-center underline"
                    href={href(current + 1)}
                >
                    Siguiente
                </Link>
            )}
        </nav>
    );
}
