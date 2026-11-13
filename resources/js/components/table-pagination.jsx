import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** "Showing 11–20 of 42" with previous/next buttons. Pairs with useTableControls. */
export default function TablePagination({
    page,
    pageCount,
    perPage,
    filteredCount,
    onPageChange,
}) {
    if (filteredCount === 0) {
        return null;
    }

    const first = (page - 1) * perPage + 1;
    const last = Math.min(page * perPage, filteredCount);

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col items-center justify-between gap-2 text-sm sm:flex-row"
        >
            <p className="text-muted-foreground" aria-live="polite">
                Showing {first}–{last} of {filteredCount}
            </p>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(page - 1)}
                    disabled={page <= 1}
                >
                    <ChevronLeft />
                    Previous
                </Button>
                <span className="px-1 tabular-nums">
                    Page {page} of {pageCount}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(page + 1)}
                    disabled={page >= pageCount}
                >
                    Next
                    <ChevronRight />
                </Button>
            </div>
        </nav>
    );
}
