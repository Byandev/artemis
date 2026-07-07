/** Centered muted placeholder for widgets with no data in the window. */
export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-full min-h-[200px] items-center justify-center">
            <p className="font-mono text-[11px] text-gray-400 dark:text-gray-600">
                {message}
            </p>
        </div>
    );
}
