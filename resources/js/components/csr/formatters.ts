export const peso = (n: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);

export const pesoCompact = (n: number) => {
    const v = Number(n) || 0;
    if (v >= 1_000_000) return `₱${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `₱${(v / 1_000).toFixed(1)}K`;
    return `₱${v.toFixed(0)}`;
};

export const formatCallTime = (seconds: number) => {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (x: number) => x.toString().padStart(2, '0');
    return h > 0 ? `${h}h ${pad(m)}m` : `${pad(m)}m ${pad(sec)}s`;
};

export const shortDate = (d: string) =>
    new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

export const fullDate = (d: string) =>
    new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });

export const pct = (value: number, total: number) =>
    total > 0 ? `${((value / total) * 100).toFixed(0)}%` : '—';
