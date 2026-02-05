import { useEffect, useMemo, useState } from 'react';

type DebouncedStateOptions = {
    delay?: number;
    trim?: boolean;
};

export function useDebouncedState(
    initialValue = '',
    options: DebouncedStateOptions = {},
) {
    const { delay = 350, trim = false } = options;

    const [value, setValue] = useState<string>(initialValue);

    const normalized = useMemo(() => {
        if (!trim) return value;
        // only normalize the debounced input (optional)
        return value.replace(/\s+/g, ' ').trimStart();
    }, [value, trim]);

    const [debounced, setDebounced] = useState<string>(normalized);

    useEffect(() => {
        const id = window.setTimeout(() => setDebounced(normalized), delay);
        return () => window.clearTimeout(id);
    }, [normalized, delay]);

    return {
        value, // bind to input
        setValue, // set from onChange
        debounced, // use in API calls
    } as const;
}
