import { useEffect, useState } from 'react';

/**
 * Tracks the `dark` class Inertia's theme handling puts on <html>. Watching the
 * class (rather than reading the stored preference) covers the "system" setting
 * and keeps the SVG fills — which cannot use CSS variables — in step with the
 * rest of the page.
 */
export function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(
        () =>
            typeof document !== 'undefined' &&
            document.documentElement.classList.contains('dark'),
    );

    useEffect(() => {
        const root = document.documentElement;
        const sync = () => setIsDark(root.classList.contains('dark'));

        sync();

        const observer = new MutationObserver(sync);
        observer.observe(root, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return isDark;
}
