import { CARD_HOVER, CARD_PAD, MUTED } from '../../lib/ui';
import { type NameSuggestion } from '../types';

interface Props {
    suggestions: NameSuggestion[];
    /** The name currently in the field, so the matching card reads as chosen. */
    picked: string;
    onPick: (name: string) => void;
}

/**
 * The "Pick a name" grid. Each card is the candidate name over the one-line
 * reason the model gave for it — the reason is what makes the set worth
 * reading, so it is never truncated away.
 */
export default function NameSuggestions({
    suggestions,
    picked,
    onPick,
}: Props) {
    if (suggestions.length === 0) {
        return null;
    }

    return (
        <div className="mt-5">
            <p className="mb-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                Pick a name
            </p>

            <div className="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                {suggestions.map((suggestion) => {
                    const chosen = suggestion.name === picked;

                    return (
                        <button
                            key={suggestion.name}
                            type="button"
                            aria-pressed={chosen}
                            onClick={() => onPick(suggestion.name)}
                            className={`${CARD_HOVER} ${CARD_PAD} text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 ${
                                chosen
                                    ? 'border-emerald-600/40 bg-emerald-500/[0.04] dark:border-emerald-400/40'
                                    : ''
                            }`}
                        >
                            <p className="text-[15px] font-medium text-gray-800 dark:text-gray-100">
                                {suggestion.name}
                            </p>
                            {suggestion.rationale && (
                                <p className={`mt-1.5 ${MUTED}`}>
                                    {suggestion.rationale}
                                </p>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
