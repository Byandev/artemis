import { useEffect, useRef, useState } from "react";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { Label } from "@/components/ui/label";
import Hook = flatpickr.Options.Hook;
import DateOption = flatpickr.Options.DateOption;
import { CalendarDays, ArrowRight, X } from 'lucide-react';

type PropsType = {
    id: string;
    mode?: "single" | "multiple" | "range" | "time";
    onChange?: Hook | Hook[];
    /**
     * A single date, or `[from, to]` in `mode="range"`. The component already
     * normalises an array at runtime, and flatpickr's own `defaultDate` accepts
     * both — the type just used to say otherwise.
     */
    defaultDate?: DateOption | DateOption[];
    label?: string;
    placeholder?: string;
    fullWidth?: boolean;
    /** Show a clear (×) button when a date is selected. Defaults to true. */
    clearable?: boolean;
    compact?: boolean;
};

const fmt     = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
const fmtYear = (d: Date) => d.getFullYear().toString();

export default function DatePicker({ id, mode, onChange, label, defaultDate, placeholder, fullWidth, compact, clearable = true }: PropsType) {
    const fpRef    = useRef<flatpickr.Instance | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const [selectedDates, setSelectedDates] = useState<Date[]>(() => {
        if (!defaultDate) return [];
        const arr = Array.isArray(defaultDate) ? defaultDate : [defaultDate];
        return arr.map((d) => new Date(d as string)).filter((d) => !isNaN(d.getTime()));
    });

    useEffect(() => {
        if (!inputRef.current) return;

        const dialogRoot = inputRef.current.closest('[data-slot="dialog-content"]');

        const wrappedOnChange: Hook = (dates, dateStr, instance) => {
            setSelectedDates([...dates]);
            if (typeof onChange === 'function') onChange(dates, dateStr, instance);
            else if (Array.isArray(onChange)) onChange.forEach((fn) => fn(dates, dateStr, instance));
        };

        const instance = flatpickr(inputRef.current, {
            mode: mode || "single",
            monthSelectorType: "static",
            dateFormat: "Y-m-d",
            defaultDate,
            appendTo: (dialogRoot ?? document.body) as HTMLElement,
            disableMobile: true,
            onChange: wrappedOnChange,
        });

        fpRef.current = Array.isArray(instance) ? instance[0] : instance;

        if (fpRef.current?.calendarContainer) {
            fpRef.current.calendarContainer.style.zIndex = '100000';
        }

        return () => { fpRef.current?.destroy(); fpRef.current = null; };
    }, [mode, id, defaultDate]);

    const isRange  = mode === 'range';
    const hasStart = selectedDates.length >= 1;
    const hasEnd   = selectedDates.length >= 2;
    const sameYear = hasEnd && fmtYear(selectedDates[0]) === fmtYear(selectedDates[1]);

    const heightCls   = compact ? 'h-7' : 'h-9';
    const iconCellCls = compact ? 'w-7' : 'w-9';
    const iconSizeCls = compact ? 'h-3 w-3' : 'h-3.5 w-3.5';
    const padCls      = compact ? 'px-2' : 'px-3';
    const textCls     = compact ? 'text-[11px]' : 'text-[13px]';

    return (
        <div className={fullWidth ? 'w-full' : undefined}>
            {label && <Label htmlFor={id}>{label}</Label>}

            <div
                onClick={() => fpRef.current?.open()}
                className={`relative items-center ${heightCls} rounded-[10px] border border-black/8 dark:border-white/8 bg-white dark:bg-zinc-900 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none hover:border-black/14 dark:hover:border-white/14 transition-all duration-150 cursor-pointer select-none ${fullWidth ? 'flex w-full' : 'inline-flex shrink-0 min-w-max'}`}
            >
                {/* Hidden input flatpickr binds to */}
                <input
                    ref={inputRef}
                    id={id}
                    readOnly
                    className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                />

                {/* Icon cell */}
                <span className={`relative z-10 pointer-events-none flex items-center justify-center ${iconCellCls} h-full border-r border-black/6 dark:border-white/6 bg-stone-50 dark:bg-white/3 shrink-0 rounded-l-[9px]`}>
                    <CalendarDays className={`${iconSizeCls} text-gray-400 dark:text-gray-500`} />
                </span>

                {/* Date display */}
                <div className={`relative z-10 pointer-events-none flex items-center ${padCls} whitespace-nowrap`}>
                    {isRange ? (
                        hasStart ? (
                            <div className="flex items-center gap-1.5">
                                <span className="flex items-baseline gap-1">
                                    <span className={`${textCls} font-medium text-gray-700 dark:text-gray-200`}>
                                        {fmt(selectedDates[0])}
                                    </span>
                                    {!sameYear && (
                                        <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                                            {fmtYear(selectedDates[0])}
                                        </span>
                                    )}
                                </span>
                                <ArrowRight className="h-3 w-3 text-gray-300 dark:text-gray-600 shrink-0" />
                                {hasEnd ? (
                                    <span className="flex items-baseline gap-1">
                                        <span className={`${textCls} font-medium text-gray-700 dark:text-gray-200`}>
                                            {fmt(selectedDates[1])}
                                        </span>
                                        <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                                            {fmtYear(selectedDates[1])}
                                        </span>
                                    </span>
                                ) : (
                                    <span className={`${textCls} text-gray-300 dark:text-gray-600`}>End date</span>
                                )}
                            </div>
                        ) : (
                            <span className={`${textCls} text-gray-300 dark:text-gray-600`}>
                                {placeholder ?? 'Select range'}
                            </span>
                        )
                    ) : (
                        hasStart ? (
                            <span className="flex items-baseline gap-1.5">
                                <span className={`${textCls} font-medium text-gray-700 dark:text-gray-200`}>
                                    {fmt(selectedDates[0])}
                                </span>
                                <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                                    {fmtYear(selectedDates[0])}
                                </span>
                            </span>
                        ) : (
                            <span className={`${textCls} text-gray-300 dark:text-gray-600`}>
                                {placeholder ?? 'Select date'}
                            </span>
                        )
                    )}
                </div>

                {/* Clear button — only when a date is selected. Clears flatpickr
                    WITHOUT its own change event, then notifies the consumer's
                    onChange with empty values so the actual filter resets (not just
                    the visual display). */}
                {clearable && hasStart && (
                    <button
                        type="button"
                        aria-label="Clear date"
                        onMouseDown={(e) => e.stopPropagation()}
                        onClick={(e) => {
                            e.stopPropagation();
                            const inst = fpRef.current;
                            inst?.clear(false);
                            setSelectedDates([]);
                            const empty: Date[] = [];
                            if (typeof onChange === 'function') {
                                onChange(empty, '', inst as flatpickr.Instance);
                            } else if (Array.isArray(onChange)) {
                                onChange.forEach((fn) =>
                                    fn(empty, '', inst as flatpickr.Instance),
                                );
                            }
                        }}
                        className="relative z-10 pointer-events-auto ml-auto mr-1.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-gray-400 hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-300 transition-colors"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
