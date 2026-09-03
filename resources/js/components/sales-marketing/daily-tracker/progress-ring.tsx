import { cn } from '@/lib/utils';

/** The ring's colour says how far along the member is, the number says exactly. */
function toneFor(percent: number): string {
    if (percent >= 100) return 'stroke-emerald-500';
    if (percent > 0) return 'stroke-amber-400';

    return 'stroke-gray-200 dark:stroke-zinc-700';
}

/**
 * A member's completion as a ring with the figure inside it.
 *
 * The percentage is always printed, so the colour is a second reading of the
 * same fact rather than the only one.
 */
export default function ProgressRing({
    percent,
    size = 36,
    className,
}: {
    percent: number;
    size?: number;
    className?: string;
}) {
    const clamped = Math.min(100, Math.max(0, percent));
    const stroke = 3;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;

    return (
        <div
            className={cn('relative shrink-0', className)}
            style={{ width: size, height: size }}
            role="img"
            aria-label={`${clamped}% complete`}
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    className="stroke-black/5 dark:stroke-white/10"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - clamped / 100)}
                    className={cn(
                        'transition-[stroke-dashoffset] duration-300',
                        toneFor(clamped),
                    )}
                />
            </svg>
            <span className="absolute inset-0 flex items-center justify-center font-mono! text-[9px]! font-medium text-gray-500 dark:text-gray-400">
                {clamped}%
            </span>
        </div>
    );
}
