import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { ChevronRight, Layers, Sparkles, Trophy } from 'lucide-react';
import { useState } from 'react';
import { defaultConfig, type ReportKind, reportsUrl } from './types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    slug: string;
}

interface Choice {
    kind: ReportKind;
    title: string;
    description: string;
    icon: typeof Trophy;
    badge?: string;
    disabled?: boolean;
}

const CHOICES: Choice[] = [
    {
        kind: 'top_performers',
        title: 'Discover top performers',
        description:
            'Find your top ads and best creative elements using any type of breakdown.',
        icon: Trophy,
    },
    {
        kind: 'custom_groups',
        title: 'Compare custom groups',
        description:
            'Use your naming convention or ad settings to compare different creative groups to each other.',
        icon: Layers,
    },
    {
        kind: 'categorization',
        title: 'Creative categorization',
        description:
            'Automatically sort ads into groups like New, Winners and Losers. Rules are customizable.',
        icon: Sparkles,
        badge: 'Coming soon',
        disabled: true,
    },
];

export default function CreateReportModal({ open, onOpenChange, slug }: Props) {
    const [creating, setCreating] = useState(false);

    const choose = (choice: Choice) => {
        if (choice.disabled || creating) return;
        setCreating(true);
        router.post(
            reportsUrl(slug),
            {
                name: 'Untitled report',
                kind: choice.kind,
                config: defaultConfig(choice.kind, []),
            },
            { onFinish: () => setCreating(false) },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl gap-0 p-0">
                <div className="px-6 pt-6 pb-4 text-center">
                    <p className="text-[11px] font-medium tracking-wider text-gray-400 uppercase">
                        Create report
                    </p>
                    <DialogTitle className="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-50">
                        What would you like to do? 🔍
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Choose a report type to get started.
                    </DialogDescription>
                </div>

                <div className="flex flex-col gap-3 px-6 pb-6">
                    {CHOICES.map((choice) => {
                        const Icon = choice.icon;
                        return (
                            <button
                                key={choice.kind}
                                type="button"
                                disabled={choice.disabled || creating}
                                onClick={() => choose(choice)}
                                className={`group flex items-center gap-4 rounded-xl border border-black/8 bg-white px-4 py-4 text-left transition-colors dark:border-white/8 dark:bg-zinc-900 ${
                                    choice.disabled
                                        ? 'cursor-not-allowed opacity-55'
                                        : 'hover:border-emerald-400/60 hover:bg-emerald-50/40 dark:hover:bg-emerald-500/5'
                                }`}
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <Icon className="h-5 w-5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-2">
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {choice.title}
                                        </span>
                                        {choice.badge && (
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                                {choice.badge}
                                            </span>
                                        )}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {choice.description}
                                    </span>
                                </span>
                                <ChevronRight className="h-4 w-4 shrink-0 text-gray-300 group-hover:text-emerald-500 dark:text-gray-600" />
                            </button>
                        );
                    })}
                </div>
            </DialogContent>
        </Dialog>
    );
}
