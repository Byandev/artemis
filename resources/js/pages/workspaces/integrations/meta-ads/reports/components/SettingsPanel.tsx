import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Settings2 } from 'lucide-react';
import { type ReportView } from '../types';

/** Gallery presentation settings: card size, items-loaded, hide thumbnails. */
export function SettingsPanel({
    view,
    onChange,
}: {
    view: ReportView;
    onChange: (view: ReportView) => void;
}) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    title="Gallery settings"
                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-black/10 bg-white px-2.5 text-xs font-medium text-gray-700 hover:border-emerald-400 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                >
                    <Settings2 className="h-3.5 w-3.5" />
                </button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-64 space-y-4 p-4">
                <p className="text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                    Settings
                </p>

                <div className="space-y-1.5">
                    <label className="text-xs text-gray-600 dark:text-gray-300">
                        Cards size
                    </label>
                    <input
                        type="range"
                        min={1}
                        max={3}
                        step={1}
                        value={view.cardSize}
                        onChange={(e) =>
                            onChange({
                                ...view,
                                cardSize: Number(e.target.value),
                            })
                        }
                        className="w-full accent-emerald-500"
                    />
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs text-gray-600 dark:text-gray-300">
                        Items loaded in gallery
                    </label>
                    <Input
                        type="number"
                        min={1}
                        max={200}
                        value={view.itemsLoaded}
                        onChange={(e) =>
                            onChange({
                                ...view,
                                itemsLoaded: Math.max(
                                    1,
                                    Number(e.target.value) || 1,
                                ),
                            })
                        }
                        className="h-8 text-xs"
                    />
                </div>

                <label className="flex cursor-pointer items-center gap-2">
                    <Checkbox
                        checked={view.hideThumbnails}
                        onCheckedChange={(c) =>
                            onChange({ ...view, hideThumbnails: !!c })
                        }
                    />
                    <span className="text-xs text-gray-600 dark:text-gray-300">
                        Hide thumbnails
                    </span>
                </label>
            </PopoverContent>
        </Popover>
    );
}
