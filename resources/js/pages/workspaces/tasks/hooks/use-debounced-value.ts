import { useEffect, useState } from 'react';

/**
 * Delay a rapidly changing value so typing in a filter does not fire a request
 * per keystroke.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
    const [settled, setSettled] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setSettled(value), delay);

        return () => clearTimeout(timer);
    }, [value, delay]);

    return settled;
}
