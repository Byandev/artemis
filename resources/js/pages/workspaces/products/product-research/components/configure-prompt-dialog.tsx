import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Minus, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    BTN_PRIMARY,
    ICON_BTN,
    INPUT,
    LABEL,
    SECTION_BORDER,
    TEXTAREA,
} from '../../lib/ui';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The small caps line above the title — "Name generation", "Product image". */
    eyebrow: string;
    promptLabel: string;
    countLabel: string;
    prompt: string;
    defaultPrompt: string;
    count: number;
    maxCount: number;
    onDone: (prompt: string, count: number) => void;
}

/**
 * One step's prompt and how many things it should produce.
 *
 * Deliberately one dialog per step rather than one holding both: the naming
 * prompt and the image prompt are opened from different places and ask for
 * unrelated things, so seeing the other one is only noise.
 *
 * The prompts belong to the brief being built, not to the workspace, so this
 * writes nothing on its own — Done hands the pair back to the builder, which
 * carries it on the form and persists it when the brief is saved. That also
 * makes it behave like every other field on the page: nothing is committed
 * until Save.
 */
export default function ConfigurePromptDialog({
    open,
    onOpenChange,
    eyebrow,
    promptLabel,
    countLabel,
    prompt: savedPrompt,
    defaultPrompt,
    count: savedCount,
    maxCount,
    onDone,
}: Props) {
    const [prompt, setPrompt] = useState(savedPrompt);
    const [count, setCount] = useState(savedCount);

    // Reopening has to reload from what the brief currently carries, or an
    // abandoned edit would look like it stuck.
    useEffect(() => {
        if (!open) return;
        setPrompt(savedPrompt);
        setCount(savedCount);
    }, [open, savedPrompt, savedCount]);

    const onDefault = prompt.trim() === defaultPrompt.trim();

    function step(by: number) {
        setCount((prev) => Math.min(maxCount, Math.max(1, prev + by)));
    }

    function done() {
        onDone(prompt, count);
        onOpenChange(false);
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
                </div>

                <div
                    className={`flex items-center justify-end border-t ${SECTION_BORDER} px-5 py-4`}
                >
                    <button
                        type="button"
                        onClick={done}
                        className={BTN_PRIMARY}
                    >
                        Done
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
