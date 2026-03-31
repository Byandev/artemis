import { useEffect, useRef, useState } from "react";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { Label } from "@/components/ui/label";
import Hook = flatpickr.Options.Hook;
import DateOption = flatpickr.Options.DateOption;
import { CalendarDays, ArrowRight } from 'lucide-react';

type PropsType = {
    id: string;
    mode?: "single" | "multiple" | "range" | "time";
    onChange?: Hook | Hook[];
    defaultDate?: DateOption;
    label?: string;
    placeholder?: string;
};

const fmt     = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
const fmtYear = (d: Date) => d.getFullYear().toString();

export default function DatePicker({ id, mode, onChange, label, defaultDate, placeholder }: PropsType) {
    const fpRef    = useRef<flatpickr.Instance | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const clearBtnRef = useRef<HTMLButtonElement | null>(null);
    const keepOpenRef = useRef<boolean>(false);

    const [selectedDates, setSelectedDates] = useState<Date[]>(() => {
        if (!defaultDate) return [];
        const arr = Array.isArray(defaultDate) ? defaultDate : [defaultDate];
        return arr.map((d) => new Date(d as string)).filter((d) => !isNaN(d.getTime()));
    });

    useEffect(() => {
        if (!inputRef.current) return;

        const wrappedOnChange: Hook = (dates, dateStr, instance) => {
            setSelectedDates([...dates]);
            if (typeof onChange === 'function') onChange(dates, dateStr, instance);
            else if (Array.isArray(onChange)) onChange.forEach((fn) => fn(dates, dateStr, instance));

            // Keep dropdown open after selecting dates so users can adjust without reopening.
            keepOpenRef.current = true;
            if (instance && !instance.isOpen) instance.open();
        };

        const instance = flatpickr(inputRef.current, {
            mode: mode || "single",
            monthSelectorType: "static",
            dateFormat: "Y-m-d",
            defaultDate,
            onChange: wrappedOnChange,
            closeOnSelect: false,
            onClose: (_dates, _dateStr, inst) => {
                if (keepOpenRef.current) {
                    keepOpenRef.current = false;
                    inst.open();
                }
            },
        });

        fpRef.current = Array.isArray(instance) ? instance[0] : instance;

        const calendar = fpRef.current?.calendarContainer;
        if (calendar && !clearBtnRef.current) {
            const footer = document.createElement('div');
            footer.className = 'flatpickr-footer mt-2 px-2 pb-2 flex justify-end';

            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Clear Selection';
            button.className = 'text-[12px] font-medium text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100';

            button.onclick = (e) => {
                e.preventDefault();
                fpRef.current?.clear();
                setSelectedDates([]);
                wrappedOnChange([], '', fpRef.current as flatpickr.Instance);
            };

            footer.appendChild(button);
            calendar.appendChild(footer);
            clearBtnRef.current = button;
        }

        return () => {
            fpRef.current?.destroy();
            fpRef.current = null;
            clearBtnRef.current = null;
            keepOpenRef.current = false;
        };
    }, [mode, id, defaultDate, onChange]);

    const isRange  = mode === 'range';
    const hasStart = selectedDates.length >= 1;
    const hasEnd   = selectedDates.length >= 2;
    const sameYear = hasEnd && fmtYear(selectedDates[0]) === fmtYear(selectedDates[1]);

    return (
        <div>
            {label && <Label htmlFor={id}>{label}</Label>}

            <div
                onClick={() => fpRef.current?.open()}
                className="relative inline-flex items-center h-9 rounded-[10px] border border-black/8 dark:border-white/8 bg-white dark:bg-zinc-900 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none hover:border-black/14 dark:hover:border-white/14 transition-all duration-150 cursor-pointer select-none"
            >
                {/* Hidden input flatpickr binds to */}
                <input
                    ref={inputRef}
                    id={id}
                    readOnly
                    className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                />

                {/* Icon cell */}
                <span className="relative z-10 pointer-events-none flex items-center justify-center w-9 h-full border-r border-black/6 dark:border-white/6 bg-stone-50 dark:bg-white/[0.03] shrink-0 rounded-l-[9px]">
                    <CalendarDays className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                </span>

                {/* Date display */}
                <div className="relative z-10 pointer-events-none flex items-center px-3">
                    {isRange ? (
                        hasStart ? (
                            <div className="flex items-center gap-1.5">
                                <span className="flex items-baseline gap-1">
                                    <span className="text-[13px] font-medium text-gray-700 dark:text-gray-200">
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
                                        <span className="text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                            {fmt(selectedDates[1])}
                                        </span>
                                        <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                                            {fmtYear(selectedDates[1])}
                                        </span>
                                    </span>
                                ) : (
                                    <span className="text-[13px] text-gray-300 dark:text-gray-600">End date</span>
                                )}
                            </div>
                        ) : (
                            <span className="text-[13px] text-gray-300 dark:text-gray-600">
                                {placeholder ?? 'Select range'}
                            </span>
                        )
                    ) : (
                        hasStart ? (
                            <span className="flex items-baseline gap-1.5">
                                <span className="text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                    {fmt(selectedDates[0])}
                                </span>
                                <span className="text-[10px] font-mono text-gray-400 dark:text-gray-500">
                                    {fmtYear(selectedDates[0])}
                                </span>
                            </span>
                        ) : (
                            <span className="text-[13px] text-gray-300 dark:text-gray-600">
                                {placeholder ?? 'Select date'}
                            </span>
                        )
                    )}
                </div>
            </div>
        </div>
    );
}
