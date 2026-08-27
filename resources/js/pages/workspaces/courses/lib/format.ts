/**
 * Formatting shared across the Courses pages. The design guide's rule is that
 * all numeric data renders as `font-mono tabular-nums`; these produce the
 * strings that go inside those spans.
 */

/** A clip's length as m:ss, or h:mm:ss once past an hour. */
export function clock(seconds: number): string {
    const s = Math.floor(seconds % 60);
    const m = Math.floor((seconds / 60) % 60);
    const h = Math.floor(seconds / 3600);
    const mm = h > 0 ? String(m).padStart(2, '0') : String(m);

    return `${h > 0 ? `${h}:` : ''}${mm}:${String(s).padStart(2, '0')}`;
}

/** A total, written the way a duration is read rather than as a clock. */
export function duration(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);

    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

export function fileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    if (bytes < 1024 * 1024 * 1024)
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

export function shortDate(value: string | null): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

/** Pluralise a count without a library, e.g. `3 lessons` / `1 lesson`. */
export function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}
