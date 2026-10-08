import { cn } from '@/lib/utils';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { TaskStatus } from '../types';

type Props = {
    status: TaskStatus;
    disabled: boolean;
    indent?: boolean;
    autoOpen?: boolean;
    onOpened?: () => void;
    onCreate: (payload: Record<string, unknown>) => Promise<void>;
    onError: (cause: unknown) => void;
};

/** The "+ New task" affordance that sits at the foot of every status group. */
export function TaskGroupAdder({
    status,
    disabled,
    indent = true,
    autoOpen = false,
    onOpened,
    onCreate,
    onError,
}: Props) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (autoOpen) {
            setOpen(true);
            onOpened?.();
        }
    }, [autoOpen, onOpened]);

    async function submit(event: React.FormEvent) {
        event.preventDefault();

        if (name.trim() === '' || saving) {
            return;
        }

        setSaving(true);

        try {
            await onCreate({ name: name.trim(), status_id: status.id });
            setName('');
        } catch (cause) {
            onError(cause);
        } finally {
            setSaving(false);
        }
    }

    if (!open) {
        return (
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen(true)}
                className={cn(
                    'flex items-center gap-1 rounded text-[0.6875rem] font-medium text-muted-foreground transition-colors duration-200 ease-out hover:text-foreground disabled:opacity-40',
                    indent ? 'w-full px-4 py-2' : 'px-1.5 py-0.5',
                )}
            >
                <Plus className="size-3.5" />
                New task
            </button>
        );
    }

    return (
        <form onSubmit={submit} className={cn(indent ? 'px-4 py-1.5' : 'py-0')}>
            <input
                autoFocus
                value={name}
                onChange={(event) => setName(event.target.value)}
                onBlur={() => {
                    if (name.trim() === '') {
                        setOpen(false);
                    }
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        setName('');
                        setOpen(false);
                    }
                }}
                placeholder={`Task name, then Enter — lands in ${status.name}`}
                aria-label={`New task in ${status.name}`}
                className={cn(
                    'rounded-md border border-emerald-500 bg-white px-2 text-sm ring-2 ring-emerald-500/15 transition-all duration-200 ease-out outline-none dark:bg-zinc-900',
                    indent ? 'h-8 w-full' : 'h-6 w-56 text-xs',
                )}
            />
        </form>
    );
}
