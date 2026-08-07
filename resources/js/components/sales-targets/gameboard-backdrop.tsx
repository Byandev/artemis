/**
 * The board's backdrop: a faint blueprint grid under two brand glows.
 *
 * Fixed rather than scrolled, so the light stays where it is while the content
 * moves over it. The grid is masked to fade out toward the bottom, which keeps
 * it from tiling visibly behind the denser panels — and it is drawn at 3–4%
 * opacity, well under the recessive gridlines inside the charts, so the page
 * never competes with its own data.
 *
 * Everything here is decorative: `aria-hidden`, no hit area, no repaint cost
 * beyond one composited layer.
 */
export function GameboardBackdrop() {
    return (
        <div aria-hidden="true" className="pointer-events-none fixed inset-0">
            {/* Blueprint grid. */}
            <div
                className="absolute inset-0 opacity-100 dark:hidden"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, rgba(9,52,41,0.045) 1px, transparent 1px), linear-gradient(to bottom, rgba(9,52,41,0.045) 1px, transparent 1px)',
                    backgroundSize: '44px 44px',
                    maskImage:
                        'radial-gradient(120% 80% at 50% 0%, black 30%, transparent 100%)',
                    WebkitMaskImage:
                        'radial-gradient(120% 80% at 50% 0%, black 30%, transparent 100%)',
                }}
            />
            <div
                className="absolute inset-0 hidden dark:block"
                style={{
                    backgroundImage:
                        'linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px)',
                    backgroundSize: '44px 44px',
                    maskImage:
                        'radial-gradient(120% 80% at 50% 0%, black 30%, transparent 100%)',
                    WebkitMaskImage:
                        'radial-gradient(120% 80% at 50% 0%, black 30%, transparent 100%)',
                }}
            />

            {/* Brand glow, top left — the same light the header washes with. */}
            <div className="absolute -top-40 -left-32 h-[36rem] w-[36rem] rounded-full bg-[radial-gradient(circle,rgba(16,211,161,0.16),transparent_65%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(16,211,161,0.20),transparent_65%)]" />

            {/* Cooler counterweight, top right, so the wash is not one flat tint. */}
            <div className="absolute -top-52 -right-40 h-[34rem] w-[34rem] rounded-full bg-[radial-gradient(circle,rgba(99,102,241,0.12),transparent_65%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(99,102,241,0.16),transparent_65%)]" />

            {/* A low pool of brand light, so the bottom of a tall board isn't dead. */}
            <div className="absolute -bottom-56 left-1/3 h-[30rem] w-[30rem] rounded-full bg-[radial-gradient(circle,rgba(16,211,161,0.10),transparent_65%)] blur-3xl dark:bg-[radial-gradient(circle,rgba(16,211,161,0.12),transparent_65%)]" />
        </div>
    );
}
