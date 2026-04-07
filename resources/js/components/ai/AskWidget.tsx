import React, { useState, useRef, useEffect } from 'react';
import { Workspace } from '@/types/models/Workspace';
import {
    Sparkles, Send, X, Loader2, ChevronDown, ChevronRight, RotateCcw,
} from 'lucide-react';
import axios from 'axios';

export interface AskWidgetSection {
    key: string;
    label: string;
    icon: React.FC<{ className?: string }>;
    color: string;
    defaultQuestion: string;
}

interface Message {
    role: 'user' | 'ai';
    text: string;
}

interface Props {
    workspace: Workspace;
    dateRange: string[];
    sections: AskWidgetSection[];
    getSectionData: (key: string) => object;
    title: string;
    secondSuggestedQuestion?: string;
    /** Full Tailwind classes for the FAB button, e.g. "bg-brand-500 shadow-brand-500/30 hover:bg-brand-600 hover:shadow-brand-500/40" */
    fabClass: string;
    /** Icon container class inside the header, e.g. "bg-brand-500/10" */
    headerIconClass: string;
    /** Icon color class inside the header, e.g. "text-brand-500" */
    headerIconTextClass: string;
}

export default function AskWidget({
    workspace,
    dateRange,
    sections,
    getSectionData,
    title,
    secondSuggestedQuestion = 'What is the single most important thing I should act on right now?',
    fabClass,
    headerIconClass,
    headerIconTextClass,
}: Props) {
    const [open, setOpen]         = useState(false);
    const [section, setSection]   = useState<string | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput]       = useState('');
    const [loading, setLoading]   = useState(false);
    const [changing, setChanging] = useState(false);
    const bottomRef               = useRef<HTMLDivElement>(null);
    const inputRef                = useRef<HTMLInputElement>(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, loading]);

    useEffect(() => {
        if (section && open) inputRef.current?.focus();
    }, [section, open]);

    const ask = async (question: string, forSection = section) => {
        const text = question.trim();
        if (!text || loading || !forSection) return;

        setChanging(false);
        setMessages(prev => [...prev, { role: 'user', text }]);
        setInput('');
        setLoading(true);

        try {
            const sectionLabel = sections.find(s => s.key === forSection)?.label ?? forSection;
            const res = await axios.post(`/workspaces/${workspace.slug}/ask`, {
                section:  sectionLabel,
                question: text,
                data:     getSectionData(forSection),
            });
            setMessages(prev => [...prev, { role: 'ai', text: res.data.answer }]);
        } catch {
            setMessages(prev => [...prev, { role: 'ai', text: 'Something went wrong. Please try again.' }]);
        } finally {
            setLoading(false);
        }
    };

    const pickSection = (key: string) => {
        setSection(key);
        setChanging(false);
    };

    const reset = () => {
        setSection(null);
        setMessages([]);
        setInput('');
        setChanging(false);
    };

    const activeSection = sections.find(s => s.key === section);
    const aiCount = messages.filter(m => m.role === 'ai').length;

    return (
        <div className="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
            {open && (
                <div className="flex h-[540px] w-[360px] flex-col overflow-hidden rounded-2xl border border-black/8 bg-white shadow-2xl shadow-black/12 dark:border-white/8 dark:bg-zinc-900">

                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-black/6 px-4 py-3 dark:border-white/6">
                        <div className="flex items-center gap-2">
                            <div className={`flex h-6 w-6 items-center justify-center rounded-lg ${headerIconClass}`}>
                                <Sparkles className={`h-3.5 w-3.5 ${headerIconTextClass}`} />
                            </div>
                            <span className="font-mono text-[12px] font-semibold text-gray-700 dark:text-gray-200">
                                {title}
                            </span>
                        </div>
                        <div className="flex items-center gap-1">
                            {section && messages.length > 0 && (
                                <button
                                    onClick={reset}
                                    title="New chat"
                                    className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-brand-500 dark:hover:bg-zinc-800 dark:hover:text-brand-500"
                                >
                                    <RotateCcw className="h-3.5 w-3.5" />
                                </button>
                            )}
                            <button
                                onClick={() => setOpen(false)}
                                className="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* Section picker */}
                    {!section && (
                        <div className="flex-1 overflow-y-auto p-4">
                            <p className="mb-1 font-mono text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                What would you like to analyze?
                            </p>
                            <p className="mb-4 font-mono text-[10px] text-gray-300 dark:text-gray-600">
                                {dateRange[0]} to {dateRange[1]}
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                {sections.map(s => {
                                    const Icon = s.icon;
                                    return (
                                        <button
                                            key={s.key}
                                            onClick={() => pickSection(s.key)}
                                            className="group flex flex-col gap-2.5 rounded-xl border border-black/5 p-3.5 text-left transition-all hover:border-brand-500/20 hover:bg-brand-500/5 hover:shadow-sm dark:border-white/5 dark:hover:border-brand-500/20 dark:hover:bg-brand-500/5"
                                        >
                                            <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${s.color}`}>
                                                <Icon className="h-4 w-4" />
                                            </div>
                                            <div>
                                                <p className="font-mono text-[11px] font-semibold text-gray-700 dark:text-gray-200">
                                                    {s.label}
                                                </p>
                                                <p className="mt-0.5 font-mono text-[10px] leading-relaxed text-gray-400 dark:text-gray-500">
                                                    {s.defaultQuestion}
                                                </p>
                                            </div>
                                            <ChevronRight className="h-3.5 w-3.5 self-end text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-600" />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Chat */}
                    {section && (
                        <>
                            <div className="flex-1 space-y-3 overflow-y-auto p-4">
                                {messages.length === 0 && !loading && activeSection && (
                                    <div className="space-y-2">
                                        <p className="font-mono text-[10px] text-gray-300 dark:text-gray-600">Suggested</p>
                                        {[activeSection.defaultQuestion, secondSuggestedQuestion].map((q, i) => (
                                            <button
                                                key={i}
                                                onClick={() => ask(q)}
                                                className="w-full rounded-xl border border-black/5 px-3 py-2.5 text-left transition-colors hover:border-brand-500/20 hover:bg-brand-500/5 dark:border-white/5 dark:hover:border-brand-500/20 dark:hover:bg-brand-500/5"
                                            >
                                                <p className="font-mono text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">{q}</p>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {messages.map((m, i) => (
                                    <div key={i} className={`flex flex-col ${m.role === 'user' ? 'items-end' : 'items-start'}`}>
                                        {m.role === 'user' ? (
                                            <div className="max-w-[80%] rounded-2xl rounded-tr-sm bg-brand-500/10 px-3 py-2">
                                                <p className="font-mono text-[11px] text-brand-700 dark:text-brand-400">{m.text}</p>
                                            </div>
                                        ) : (
                                            <div className="max-w-[95%] rounded-2xl rounded-tl-sm bg-gray-50 px-3 py-2.5 dark:bg-zinc-800">
                                                <p className="font-mono text-[11px] leading-relaxed text-gray-600 dark:text-gray-400">{m.text}</p>
                                            </div>
                                        )}
                                    </div>
                                ))}

                                {loading && (
                                    <div className="flex justify-start">
                                        <div className="rounded-2xl rounded-tl-sm bg-gray-50 px-3 py-3 dark:bg-zinc-800">
                                            <div className="flex gap-1">
                                                <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-500 [animation-delay:0ms]" />
                                                <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-500 [animation-delay:150ms]" />
                                                <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-brand-500 [animation-delay:300ms]" />
                                            </div>
                                        </div>
                                    </div>
                                )}
                                <div ref={bottomRef} />
                            </div>

                            {/* Input */}
                            <div className="border-t border-black/6 p-3 dark:border-white/6">
                                {activeSection && (
                                    <div className="mb-2 flex items-center">
                                        {changing ? (
                                            <div className="flex flex-wrap gap-1">
                                                {sections.map(s => {
                                                    const Icon = s.icon;
                                                    return (
                                                        <button
                                                            key={s.key}
                                                            onClick={() => pickSection(s.key)}
                                                            className={`flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[10px] font-medium transition-colors ${
                                                                section === s.key
                                                                    ? 'bg-brand-500/10 text-brand-700 dark:text-brand-400'
                                                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700'
                                                            }`}
                                                        >
                                                            <Icon className="h-2.5 w-2.5" />
                                                            {s.label}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-1.5">
                                                <div className={`flex h-4 w-4 items-center justify-center rounded ${activeSection.color}`}>
                                                    <activeSection.icon className="h-2.5 w-2.5" />
                                                </div>
                                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    {activeSection.label}
                                                </span>
                                                <button
                                                    onClick={() => setChanging(true)}
                                                    className="font-mono! text-[10px]! text-gray-300 underline underline-offset-2 transition-colors hover:text-brand-500 dark:text-gray-600 dark:hover:text-brand-500"
                                                >
                                                    change
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="flex items-center gap-2 rounded-xl border border-black/6 bg-gray-50 px-3 py-2 dark:border-white/6 dark:bg-zinc-800">
                                    <input
                                        ref={inputRef}
                                        type="text"
                                        value={input}
                                        onChange={e => setInput(e.target.value)}
                                        onKeyDown={e => e.key === 'Enter' && !e.shiftKey && ask(input)}
                                        placeholder={`Ask about ${activeSection?.label ?? 'your data'}…`}
                                        disabled={loading}
                                        className="flex-1 bg-transparent font-mono! text-[11px]! text-gray-700 placeholder-gray-300 outline-none disabled:opacity-50 dark:text-gray-300 dark:placeholder-gray-600"
                                    />
                                    <button
                                        onClick={() => ask(input)}
                                        disabled={!input.trim() || loading}
                                        className="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white transition-colors disabled:opacity-30 hover:bg-brand-600"
                                    >
                                        {loading
                                            ? <Loader2 className="h-3 w-3 animate-spin" />
                                            : <Send className="h-3 w-3" />
                                        }
                                    </button>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            )}

            <button
                onClick={() => setOpen(o => !o)}
                className={`relative flex h-12 w-12 items-center justify-center rounded-full text-white shadow-lg transition-all hover:scale-105 ${fabClass}`}
            >
                {open
                    ? <ChevronDown className="h-5 w-5" />
                    : <Sparkles className="h-5 w-5" />
                }
                {!open && aiCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-800 font-mono text-[9px] font-bold text-white dark:bg-white dark:text-gray-900">
                        {aiCount}
                    </span>
                )}
            </button>
        </div>
    );
}
