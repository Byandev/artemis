import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import axios from 'axios';
import { Minus, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    BTN_PRIMARY,
    FIELD_ERROR,
    ICON_BTN,
    INPUT,
    LABEL,
    SECTION_BORDER,
    TEXTAREA,
} from '../../lib/ui';
import { type PromptSettings } from '../types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    baseUrl: string;
    /** The small caps line above the title — "Name generation", "Product image". */
    eyebrow: string;
    promptLabel: string;
    countLabel: string;
    prompt: string;
    defaultPrompt: string;
    count: number;
    maxCount: number;
    /** The two settings keys this dialog owns; the other pair is left alone. */
    promptKey: 'naming_prompt' | 'packshot_prompt';
    countKey: 'name_count' | 'packshot_count';
    onSaved: (settings: PromptSettings) => void;
}

/**
 * One step's prompt and how many things it should produce.
 *
 * Deliberately one dialog per step rather than one holding both: the naming
 * prompt and the image prompt are opened from different places and ask for
 * unrelated things, so seeing the other one is only noise. The settings live
 * in a single row, and the endpoint takes partial updates, so each dialog
 * sends only its own pair.
 */
export default function ConfigurePromptDialog({
    open,
    onOpenChange,
    baseUrl,
    eyebrow,
    promptLabel,
    countLabel,
    prompt: savedPrompt,
    defaultPrompt,
    count: savedCount,
    maxCount,
    promptKey,
    countKey,
    onSaved,
}: Props) {
    const [prompt, setPrompt] = useState(savedPrompt);
    const [count, setCount] = useState(savedCount);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Reopening has to reload from what was last saved, or an abandoned edit
    // would look like it stuck.
    useEffect(() => {
        if (!open) return;
        setPrompt(savedPrompt);
        setCount(savedCount);
        setError(null);
    }, [open, savedPrompt, savedCount]);

    const onDefault = prompt.trim() === defaultPrompt.trim();

    function step(by: number) {
        setCount((prev) => Math.min(maxCount, Math.max(1, prev + by)));
    }

    async function save() {
        setSaving(true);
        setError(null);

        try {
            const response = await axios.put<PromptSettings>(
                `${baseUrl}/prompt-settings`,
                { [promptKey]: prompt, [countKey]: count },
            );

            onSaved(response.data);
            onOpenChange(false);
        } catch {
            setError('Could not save. Try again.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden rounded-[14px] p-0 sm:max-w-lg">
                <div className={`border-b ${SECTION_BORDER} px-5 pt-5 pb-4`}>
                    <p className={LABEL}>{eyebrow}</p>
                    <DialogHeader className="mt-1 gap-0">
                        <DialogTitle className="text-[17px] font-semibold text-gray-900 dark:text-gray-100">
                            Configure prompt
                        </DialogTitle>
                    </DialogHeader>
                </div>

                <div className="space-y-5 px-5 py-4">
                    <div>
                        <div className="mb-1.5 flex items-center justify-between gap-3">
                            <label className="text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                {promptLabel}
                            </label>
                            <button
                                type="button"
                                onClick={() => setPrompt(defaultPrompt)}
                                disabled={onDefault}
                                className="text-[12px] font-medium text-emerald-600 transition-colors hover:text-emerald-700 disabled:pointer-events-none disabled:opacity-40 dark:text-emerald-400"
                            >
                                Reset to default
                            </button>
                        </div>
                        <textarea
                            rows={5}
                            className={TEXTAREA}
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                        />
                    </div>

                    <div>
                        <p className="mb-1.5 text-[13px] font-medium text-gray-700 dark:text-gray-200">
                            {countLabel}
                        </p>
                        <div className="flex items-center gap-2.5">
                            <button
                                type="button"
                                onClick={() => step(-1)}
                                disabled={count <= 1}
                                aria-label="Fewer"
                                className={`h-9 w-9 ${ICON_BTN}`}
                            >
                                <Minus className="h-3.5 w-3.5" />
                            </button>
                            <input
                                type="number"
                                min={1}
                                max={maxCount}
                                value={count}
                                onChange={(e) =>
                                    setCount(
                                        Math.min(
                                            maxCount,
                                            Math.max(
                                                1,
                                                Number(e.target.value) || 1,
                                            ),
                                        ),
                                    )
                                }
                                className={`${INPUT} w-20 text-center font-mono tabular-nums`}
                            />
                            <button
                                type="button"
                                onClick={() => step(1)}
                                disabled={count >= maxCount}
                                aria-label="More"
                                className={`h-9 w-9 ${ICON_BTN}`}
                            >
                                <Plus className="h-3.5 w-3.5" />
                            </button>
                            <span className={LABEL}>Max {maxCount}</span>
                        </div>
                    </div>

                    {error && <p className={FIELD_ERROR}>{error}</p>}
                </div>

                <div
                    className={`flex items-center justify-end border-t ${SECTION_BORDER} px-5 py-4`}
                >
                    <button
                        type="button"
                        onClick={save}
                        disabled={saving}
                        className={BTN_PRIMARY}
                    >
                        {saving ? 'Saving…' : 'Done'}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
