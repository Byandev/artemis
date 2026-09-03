import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * Fills for the initials chip. Picked by member id so someone keeps the same
 * colour between the roster, the header and the matrix — it is an aid to
 * scanning, never the thing that identifies them: the name or its initials sit
 * on top of every chip.
 */
const FILLS = [
    'bg-blue-500',
    'bg-emerald-500',
    'bg-amber-500',
    'bg-violet-500',
    'bg-rose-500',
    'bg-teal-500',
    'bg-indigo-500',
    'bg-orange-500',
];

const SIZES = {
    sm: 'h-6 w-6 rounded-md text-[9px]',
    md: 'h-8 w-8 rounded-lg text-[11px]',
    lg: 'h-10 w-10 rounded-xl text-[12px]',
} as const;

export default function MemberAvatar({
    id,
    name,
    size = 'md',
    className,
}: {
    id: number;
    name: string;
    size?: keyof typeof SIZES;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center font-medium text-white select-none',
                FILLS[id % FILLS.length],
                SIZES[size],
                className,
            )}
            title={name}
        >
            {getInitials(name)}
        </span>
    );
}
