import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { Paginated } from '@/types';

/**
 * shadcn pagination driven by a Laravel paginator. Links keep the query
 * string and visit through Inertia instead of reloading the page.
 */
export function ListPagination<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const visit = (url: string | null) => (event: MouseEvent) => {
        event.preventDefault();

        if (url) {
            router.visit(url, { preserveState: true });
        }
    };

    const pages = paginator.links.slice(1, -1);

    return (
        <Pagination>
            <PaginationContent>
                <PaginationItem>
                    <PaginationPrevious
                        href={paginator.prev_page_url ?? undefined}
                        aria-disabled={!paginator.prev_page_url}
                        className={
                            paginator.prev_page_url
                                ? undefined
                                : 'pointer-events-none opacity-50'
                        }
                        onClick={visit(paginator.prev_page_url)}
                    />
                </PaginationItem>
                {pages.map((link, index) =>
                    link.url === null ? (
                        <PaginationItem key={`gap-${index}`}>
                            <PaginationEllipsis />
                        </PaginationItem>
                    ) : (
                        <PaginationItem key={link.url}>
                            <PaginationLink
                                href={link.url}
                                isActive={link.active}
                                onClick={visit(link.url)}
                            >
                                {link.label}
                            </PaginationLink>
                        </PaginationItem>
                    ),
                )}
                <PaginationItem>
                    <PaginationNext
                        href={paginator.next_page_url ?? undefined}
                        aria-disabled={!paginator.next_page_url}
                        className={
                            paginator.next_page_url
                                ? undefined
                                : 'pointer-events-none opacity-50'
                        }
                        onClick={visit(paginator.next_page_url)}
                    />
                </PaginationItem>
            </PaginationContent>
        </Pagination>
    );
}
