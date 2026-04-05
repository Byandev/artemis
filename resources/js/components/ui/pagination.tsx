import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';

interface PaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (newPage: number) => void
}

const Pagination = ({ currentPage, totalPages, onPageChange }: PaginationProps) => {
    const pagesAroundCurrent = useMemo(() => {
        const start = Math.min(
            Math.max(currentPage - 1, 1),
            Math.max(totalPages - 2, 1),
        );
        return Array.from({ length: Math.min(3, totalPages) }, (_, i) => start + i);
    }, [currentPage, totalPages]);

    const navBtn = "inline-flex items-center justify-center h-8 gap-1.5 px-3 rounded-lg border text-[11px]! font-medium tracking-wide transition-all duration-150 disabled:pointer-events-none disabled:opacity-35 select-none";
    const navBtnActive = "border-black/10 bg-white text-gray-600 shadow-[0_1px_2px_rgba(0,0,0,0.06)] hover:bg-gray-50 hover:border-black/15 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-300 dark:shadow-none dark:hover:bg-zinc-700";

    return (
        <div className="flex items-center justify-center gap-1.5">
            <button
                onClick={() => onPageChange(currentPage - 1)}
                disabled={currentPage === 1}
                className={`${navBtn} ${navBtnActive}`}
            >
                <ChevronLeft className="h-3.5 w-3.5" />
                <span>Prev</span>
            </button>

            <div className="flex items-center gap-1">
                {currentPage > 3 && (
                    <span className="flex h-8 w-8 items-center justify-center text-[11px]! text-gray-300 dark:text-gray-600">…</span>
                )}
                {pagesAroundCurrent.map((page) => (
                    <button
                        key={page}
                        onClick={() => onPageChange(page)}
                        className={`inline-flex h-8 w-8 items-center justify-center rounded-lg border text-[11px]! font-medium tracking-wide transition-all duration-150 select-none ${
                            currentPage === page
                                ? 'border-brand-500/30 bg-brand-500/[0.08] text-brand-600 dark:border-brand-400/20 dark:text-brand-400 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]'
                                : 'border-black/8 bg-transparent text-gray-400 hover:border-black/12 hover:bg-gray-50 hover:text-gray-700 dark:border-white/8 dark:text-gray-500 dark:hover:bg-zinc-800 dark:hover:text-gray-300'
                        }`}
                    >
                        {page}
                    </button>
                ))}
                {currentPage < totalPages - 2 && (
                    <span className="flex h-8 w-8 items-center justify-center text-[11px]! text-gray-300 dark:text-gray-600">…</span>
                )}
            </div>

            <button
                onClick={() => onPageChange(currentPage + 1)}
                disabled={currentPage === totalPages}
                className={`${navBtn} ${navBtnActive}`}
            >
                <span>Next</span>
                <ChevronRight className="h-3.5 w-3.5" />
            </button>
        </div>
    );
};

export default Pagination;
