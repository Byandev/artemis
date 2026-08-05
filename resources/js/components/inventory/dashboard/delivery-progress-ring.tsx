/**
 * One PO line's delivery progress as a small radial meter: how much of what was
 * ordered on that line has landed.
 *
 * A meter rather than a two-slice pie — there is a single ratio here and the
 * unfilled arc is its remainder, not a second category. The track is a lighter
 * step of the fill's own ramp so the state reads around the whole ring, and the
 * percentage sits beside it in ink: the arc carries identity, the text carries
 * the value. The fill is the same blue the movement chart uses for arriving
 * stock — same entity, same hue.
 */
interface Props {
    ordered: number;
    delivered: number;
}

const SIZE = 24;
const STROKE = 3.5;
const RADIUS = (SIZE - STROKE) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

export default function DeliveryProgressRing({ ordered, delivered }: Props) {
    const rate = ordered > 0 ? (delivered / ordered) * 100 : 0;
    // The arc is clamped so an over-delivered line cannot wrap past the ring;
    // the printed figure stays truthful.
    const swept = Math.max(0, Math.min(100, rate));
    const dash = (swept / 100) * CIRCUMFERENCE;

    return (
        <div
            className="flex items-center gap-1.5"
            title={`${delivered.toLocaleString('en-PH')} of ${ordered.toLocaleString('en-PH')} delivered`}
        >
            {/* -rotate-90 starts the arc at 12 o'clock rather than 3. */}
            <svg
                width={SIZE}
                height={SIZE}
                viewBox={`0 0 ${SIZE} ${SIZE}`}
                className="shrink-0 -rotate-90"
                role="img"
                aria-label={`${Math.round(rate)}% delivered`}
            >
                <circle
                    cx={SIZE / 2}
                    cy={SIZE / 2}
                    r={RADIUS}
                    fill="none"
                    strokeWidth={STROKE}
                    className="stroke-[#cfe0f5] dark:stroke-[#20344d]"
                />
                {swept > 0 && (
                    <circle
                        cx={SIZE / 2}
                        cy={SIZE / 2}
                        r={RADIUS}
                        fill="none"
                        strokeWidth={STROKE}
                        strokeLinecap="round"
                        strokeDasharray={`${dash} ${CIRCUMFERENCE - dash}`}
                        className="stroke-[#2a78d6] dark:stroke-[#3987e5]"
                    />
                )}
            </svg>
            {/* tabular-nums: this one IS a column of numbers, so the digits align
                down the table. */}
            <span className="text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                {Math.round(rate)}%
            </span>
        </div>
    );
}
