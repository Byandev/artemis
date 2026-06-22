import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { HelpCircle } from 'lucide-react';
import { type ReactNode } from 'react';

/**
 * A small question-mark icon that reveals explanatory text on hover/focus.
 * Reusable across forms and settings to document non-obvious fields.
 */
export default function HelpTooltip({
    children,
    className,
    side = 'top',
}: {
    children: ReactNode;
    className?: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
}) {
    return (
        <Tooltip>
            <TooltipTrigger
                type="button"
                className={cn(
                    'inline-flex h-4 w-4 items-center justify-center rounded-full text-gray-400 transition-colors hover:text-gray-600 focus-visible:outline-none dark:text-gray-500 dark:hover:text-gray-300',
                    className,
                )}
                aria-label="More information"
            >
                <HelpCircle className="h-3.5 w-3.5" />
            </TooltipTrigger>
            <TooltipContent side={side} className="max-w-xs leading-relaxed">
                {children}
            </TooltipContent>
        </Tooltip>
    );
}
