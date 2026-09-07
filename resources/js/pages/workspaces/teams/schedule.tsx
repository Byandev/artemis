import { Can } from '@/components/can';
import PageHeader from '@/components/common/PageHeader';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Clock,
    Copy,
    Eraser,
    Loader2,
    Moon,
    Pencil,
    Save,
    Sun,
    Sunrise,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

/* ─── Types ───────────────────────────────────────────────── */

interface User {
    id: number;
    name: string;
    email: string;
}

interface Schedule {
    user_id: number;
    date: string;
    start_time: string | null;
    end_time: string | null;
}

interface Team {
    id: number;
    name: string;
    members: User[];
}

interface Props {
    workspace: Workspace;
    team: Team;
    schedules: Schedule[];
    weekStart: string;
}

/* ─── Constants ───────────────────────────────────────────── */

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as const;
const DAYS_FULL = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
] as const;

const SHIFT_PRESETS = {
    am: {
        label: 'AM Shift',
        short: 'AM',
        start: '06:00',
        end: '15:00',
        icon: Sunrise,
        badge: 'bg-amber-500/10 text-amber-700 border-amber-200 dark:bg-amber-500/15 dark:text-amber-400 dark:border-amber-500/20',
    },
    pm: {
        label: 'PM Shift',
        short: 'PM',
        start: '15:00',
        end: '00:00',
        icon: Moon,
        badge: 'bg-indigo-500/10 text-indigo-700 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-400 dark:border-indigo-500/20',
    },
    mid: {
        label: 'MID Shift',
        short: 'MID',
        start: '09:00',
        end: '18:00',
        icon: Sun,
        badge: 'bg-blue-light-500/10 text-blue-light-700 border-blue-light-200 dark:bg-blue-light-500/15 dark:text-blue-light-400 dark:border-blue-light-500/20',
    },
} as const;

type ShiftKey = keyof typeof SHIFT_PRESETS;
type CellKey = `${number}-${string}`;

function normalizeDate(d: string): string {
    return d.slice(0, 10);
}
function normalizeTime(t: string | null): string | null {
    if (!t) return null;
    return t.slice(0, 5);
}
function buildKey(userId: number, date: string): CellKey {
    return `${userId}-${normalizeDate(date)}`;
}
function getInitials(name: string) {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function formatDateShort(dateStr: string) {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function addDays(dateStr: string, days: number) {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function getWeekDates(weekStart: string): string[] {
    return Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));
}

function getWeekLabel(weekStart: string) {
    return `${formatDateShort(weekStart)} — ${formatDateShort(addDays(weekStart, 6))}`;
}

function todayStr() {
    return new Date().toISOString().split('T')[0];
}

function isCurrentWeek(weekStart: string) {
    const today = todayStr();
    return today >= weekStart && today <= addDays(weekStart, 6);
}

function formatTime12(time: string | null) {
    if (!time) return '—';
    const [h, m] = time.split(':');
    const hour = parseInt(h);
    if (hour === 0) return '12:' + m + ' MN';
    if (hour === 12) return '12:' + m + ' PM';
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const h12 = hour > 12 ? hour - 12 : hour;
    return `${h12}:${m} ${ampm}`;
}

function detectShift(
    start: string | null,
    end: string | null,
): ShiftKey | 'off' | 'custom' {
    if (!start && !end) return 'off';
    for (const [key, def] of Object.entries(SHIFT_PRESETS)) {
        if (start === def.start && end === def.end) return key as ShiftKey;
    }
    return 'custom';
}

function getShiftStyle(start: string | null, end: string | null) {
    const key = detectShift(start, end);
    if (key === 'off')
        return 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-zinc-800 dark:text-gray-400 dark:border-zinc-700';
    if (key === 'custom')
        return 'bg-brand-500/10 text-brand-700 border-brand-200 dark:bg-brand-500/15 dark:text-brand-400 dark:border-brand-500/20';
    return SHIFT_PRESETS[key].badge;
}

function getShiftLabel(start: string | null, end: string | null) {
    const key = detectShift(start, end);
    if (key === 'off') return 'OFF';
    if (key === 'custom') return 'CUSTOM';
    return SHIFT_PRESETS[key].short;
}

/* ─── Main Page ───────────────────────────────────────────── */

export default function TeamSchedule({
    workspace,
    team,
    schedules: serverSchedules,
    weekStart,
}: Props) {
    const canManageSchedule = usePermission(PERMISSIONS.ManageSchedule);
    const weekDates = useMemo(() => getWeekDates(weekStart), [weekStart]);
    const today = todayStr();

    const initialMap = useMemo(() => {
        const map = new Map<CellKey, Schedule>();
        for (const s of serverSchedules) {
            const normalized: Schedule = {
                user_id: s.user_id,
                date: normalizeDate(s.date),
                start_time: normalizeTime(s.start_time),
                end_time: normalizeTime(s.end_time),
            };
            map.set(buildKey(normalized.user_id, normalized.date), normalized);
        }
        return map;
    }, [serverSchedules]);

    const [scheduleMap, setScheduleMap] =
        useState<Map<CellKey, Schedule>>(initialMap);
    const [editingMember, setEditingMember] = useState<User | null>(null);
    const [saving, setSaving] = useState(false);
    const [pendingNav, setPendingNav] = useState<(() => void) | null>(null);
    const bypassGuard = useRef(false);

    const hasChanges = useMemo(() => {
        if (scheduleMap.size !== initialMap.size) return true;
        for (const [key, val] of scheduleMap) {
            const orig = initialMap.get(key);
            if (!orig) return true;
            if (
                orig.start_time !== val.start_time ||
                orig.end_time !== val.end_time
            )
                return true;
        }
        return false;
    }, [scheduleMap, initialMap]);

    const handleSave = useCallback(
        (onSaved?: () => void) => {
            const entries = Array.from(scheduleMap.values()).map((s) => ({
                user_id: s.user_id,
                date: s.date,
                start_time: s.start_time,
                end_time: s.end_time,
            }));
            setSaving(true);
            router.put(
                `/workspaces/${workspace.slug}/teams/${team.id}/schedule`,
                { week_start: weekStart, schedules: entries },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Schedule saved');
                        setSaving(false);
                        onSaved?.();
                    },
                    onError: () => {
                        toast.error('Failed to save');
                        setSaving(false);
                    },
                },
            );
        },
        [scheduleMap, workspace.slug, team.id, weekStart],
    );

    /* Warn before a tab close / hard reload drops unsaved edits */
    useEffect(() => {
        if (!hasChanges) return;
        const warn = (e: BeforeUnloadEvent) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [hasChanges]);

    /* Block Inertia navigation (week arrows, Back, sidebar) while dirty */
    useEffect(() => {
        if (!hasChanges) return;
        return router.on('before', (event) => {
            if (bypassGuard.current) return;

            const visit = event.detail.visit;

            // Only real page navigations count — let form submits, link
            // prefetches, background reloads and partial refreshes through.
            if (
                visit.method !== 'get' ||
                visit.prefetch ||
                visit.async ||
                visit.only.length > 0 ||
                visit.except.length > 0 ||
                visit.url.href === window.location.href
            )
                return;

            const { url, data, replace, preserveScroll, preserveState } = visit;
            setPendingNav(() => () => {
                bypassGuard.current = true;
                router.visit(url, {
                    method: 'get',
                    data,
                    replace,
                    preserveScroll,
                    preserveState,
                    onFinish: () => {
                        bypassGuard.current = false;
                    },
                });
            });

            return false;
        });
    }, [hasChanges]);

    const leavePending = useCallback(() => {
        const go = pendingNav;
        setPendingNav(null);
        go?.();
    }, [pendingNav]);

    const navigateWeek = (dir: number) => {
        router.get(
            `/workspaces/${workspace.slug}/teams/${team.id}/schedule`,
            { week: addDays(weekStart, dir * 7) },
            { preserveState: false },
        );
    };

    const goToCurrentWeek = () => {
        router.get(
            `/workspaces/${workspace.slug}/teams/${team.id}/schedule`,
            {},
            { preserveState: false },
        );
    };

    const getMemberSchedules = useCallback(
        (userId: number): Map<string, Schedule> => {
            const map = new Map<string, Schedule>();
            for (const date of weekDates) {
                const s = scheduleMap.get(buildKey(userId, date));
                if (s) map.set(date, s);
            }
            return map;
        },
        [scheduleMap, weekDates],
    );

    const updateMemberSchedules = useCallback(
        (userId: number, memberSchedules: Map<string, Schedule>) => {
            setScheduleMap((prev) => {
                const next = new Map(prev);
                for (const date of weekDates)
                    next.delete(buildKey(userId, date));
                for (const [date, schedule] of memberSchedules)
                    next.set(buildKey(userId, date), schedule);
                return next;
            });
        },
        [weekDates],
    );

    return (
        <AppLayout>
            <Head title={`${team.name} Schedule — ${workspace.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title={`${team.name} — Schedule`}
                    description="Manage weekly work schedules for each team member"
                    stackActionsOnMobile
                >
                    {hasChanges && (
                        <span className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-500/10 px-2.5 font-mono! text-[11px]! font-medium text-amber-700 dark:border-amber-500/20 dark:text-amber-400">
                            <span className="h-1.5 w-1.5 rounded-full bg-amber-500" />
                            Unsaved changes
                        </span>
                    )}
                    <Link
                        href={`/workspaces/${workspace.slug}/teams`}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-stone-100 px-3 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back
                    </Link>
                    <Can permission={PERMISSIONS.ManageSchedule}>
                        <button
                            onClick={() => handleSave()}
                            disabled={saving || !hasChanges}
                            className="flex h-8 items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700 disabled:opacity-40"
                        >
                            {saving ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Save className="h-3.5 w-3.5" />
                            )}
                            Save Schedule
                        </button>
                    </Can>
                </PageHeader>

                {/* Week Navigator */}
                <div className="mb-5 flex items-center justify-center gap-4">
                    <button
                        onClick={() => navigateWeek(-1)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <div className="flex items-center gap-3">
                        <Calendar className="h-4 w-4 text-gray-400" />
                        <p className="text-[14px] font-semibold text-gray-800 dark:text-gray-100">
                            {getWeekLabel(weekStart)}
                        </p>
                        {isCurrentWeek(weekStart) && (
                            <span className="inline-flex items-center rounded-full bg-brand-500/10 px-2 py-0.5 text-[10px] font-medium text-brand-600 dark:text-brand-400">
                                Current Week
                            </span>
                        )}
                        {!isCurrentWeek(weekStart) && (
                            <button
                                onClick={goToCurrentWeek}
                                className="rounded-md border border-black/6 px-2 py-1 text-[10px] font-medium text-gray-500 transition-colors hover:bg-stone-100 dark:border-white/6 dark:hover:bg-zinc-800"
                            >
                                Today
                            </button>
                        )}
                    </div>
                    <button
                        onClick={() => navigateWeek(1)}
                        className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>

                {/* Schedule Calendar */}
                {team.members.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-[14px] border border-black/6 bg-white py-16 dark:border-white/6 dark:bg-zinc-900">
                        <p className="text-sm text-gray-400">
                            No members in this team yet.
                        </p>
                        <Link
                            href={`/workspaces/${workspace.slug}/teams`}
                            className="mt-2 text-xs text-brand-600 hover:underline dark:text-brand-400"
                        >
                            Add members first
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                        <table className="w-full min-w-[900px] border-collapse">
                            <thead>
                                <tr>
                                    <th className="w-48 border-r border-b border-black/6 px-4 py-2 text-left dark:border-white/6">
                                        <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            Member
                                        </span>
                                    </th>
                                    {weekDates.map((date, i) => {
                                        const isToday = date === today;
                                        const isWeekend = i >= 5;
                                        const dayNum = new Date(
                                            date + 'T12:00:00',
                                        ).getDate();
                                        return (
                                            <th
                                                key={date}
                                                className={[
                                                    'border-b border-l border-black/6 px-1 py-2 text-center dark:border-white/6',
                                                    isWeekend
                                                        ? 'bg-stone-50/60 dark:bg-white/[0.02]'
                                                        : '',
                                                ].join(' ')}
                                            >
                                                <span
                                                    className={[
                                                        'block font-mono text-[10px] font-medium tracking-wider uppercase',
                                                        isToday
                                                            ? 'text-brand-600 dark:text-brand-400'
                                                            : isWeekend
                                                              ? 'text-gray-300 dark:text-gray-600'
                                                              : 'text-gray-400 dark:text-gray-500',
                                                    ].join(' ')}
                                                >
                                                    {DAYS[i]}
                                                </span>
                                                <span
                                                    className={[
                                                        'mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-semibold',
                                                        isToday
                                                            ? 'bg-brand-600 text-white'
                                                            : isWeekend
                                                              ? 'text-gray-300 dark:text-gray-600'
                                                              : 'text-gray-600 dark:text-gray-300',
                                                    ].join(' ')}
                                                >
                                                    {dayNum}
                                                </span>
                                            </th>
                                        );
                                    })}
                                    {canManageSchedule && (
                                        <th className="w-14 border-b border-l border-black/6 px-2 py-2 dark:border-white/6" />
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {team.members.map((member, memberIdx) => (
                                    <tr
                                        key={member.id}
                                        className={
                                            memberIdx < team.members.length - 1
                                                ? 'border-b border-black/4 dark:border-white/4'
                                                : ''
                                        }
                                    >
                                        {/* Member name */}
                                        <td className="border-r border-black/6 px-4 py-3 dark:border-white/6">
                                            <div className="flex items-center gap-2.5">
                                                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-[10px] font-semibold text-brand-700 dark:text-brand-400">
                                                    {getInitials(member.name)}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-[12px] font-medium text-gray-800 dark:text-gray-200">
                                                        {member.name}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        {/* Day cells */}
                                        {weekDates.map((date, i) => {
                                            const schedule = scheduleMap.get(
                                                buildKey(member.id, date),
                                            );
                                            const isWeekend = i >= 5;
                                            return (
                                                <td
                                                    key={date}
                                                    className={[
                                                        'border-l border-black/6 px-1.5 py-1.5 dark:border-white/6',
                                                        isWeekend
                                                            ? 'bg-stone-50/60 dark:bg-white/[0.02]'
                                                            : '',
                                                    ].join(' ')}
                                                >
                                                    {schedule ? (
                                                        <ShiftBadge
                                                            start={
                                                                schedule.start_time
                                                            }
                                                            end={
                                                                schedule.end_time
                                                            }
                                                        />
                                                    ) : (
                                                        <div className="flex items-center justify-center rounded-lg border border-dashed border-black/6 py-2 dark:border-white/6">
                                                            <span className="text-[10px] text-gray-300 dark:text-gray-600">
                                                                —
                                                            </span>
                                                        </div>
                                                    )}
                                                </td>
                                            );
                                        })}

                                        {/* Edit button */}
                                        {canManageSchedule && (
                                            <td className="border-l border-black/6 px-2 py-2 text-center dark:border-white/6">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setEditingMember(member)
                                                    }
                                                    className="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-brand-500/30 dark:hover:bg-brand-500/10 dark:hover:text-brand-400"
                                                    title={`Edit ${member.name}'s schedule`}
                                                >
                                                    <Pencil className="h-3 w-3" />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Legend */}
                <div className="mt-4 flex flex-wrap items-center gap-3 px-1">
                    {(['am', 'pm', 'mid'] as const).map((key) => {
                        const s = SHIFT_PRESETS[key];
                        return (
                            <div
                                key={key}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={`inline-flex h-5 items-center rounded border px-1.5 text-[9px] font-bold ${s.badge}`}
                                >
                                    {s.short}
                                </span>
                                <span className="font-mono text-[10px] text-gray-400">
                                    {formatTime12(s.start)} –{' '}
                                    {formatTime12(s.end)}
                                </span>
                            </div>
                        );
                    })}
                    <div className="flex items-center gap-1.5">
                        <span className="inline-flex h-5 items-center rounded border border-gray-200 bg-gray-100 px-1.5 text-[9px] font-bold text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400">
                            OFF
                        </span>
                        <span className="font-mono text-[10px] text-gray-400">
                            Day Off
                        </span>
                    </div>
                </div>
            </div>

            {/* Edit Schedule Modal */}
            {editingMember && (
                <EditScheduleModal
                    member={editingMember}
                    team={team}
                    weekStart={weekStart}
                    weekDates={weekDates}
                    memberSchedules={getMemberSchedules(editingMember.id)}
                    getOtherMemberSchedules={getMemberSchedules}
                    onSave={(schedules) => {
                        updateMemberSchedules(editingMember.id, schedules);
                        setEditingMember(null);
                    }}
                    onClose={() => setEditingMember(null)}
                />
            )}

            {/* Unsaved Changes Guard */}
            <AlertDialog
                open={!!pendingNav}
                onOpenChange={(open) => !open && setPendingNav(null)}
            >
                <AlertDialogContent className="max-w-[440px] border-none shadow-2xl dark:bg-zinc-900">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                            Unsaved changes
                        </AlertDialogTitle>
                        <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                            The schedule for{' '}
                            <strong>{getWeekLabel(weekStart)}</strong> has
                            changes that haven't been saved yet. Leaving this
                            page will discard them.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter className="mt-4 gap-2">
                        <AlertDialogCancel
                            disabled={saving}
                            className="h-9 rounded-lg border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Stay
                        </AlertDialogCancel>
                        <button
                            type="button"
                            onClick={leavePending}
                            disabled={saving}
                            className="h-9 rounded-lg border border-red-200 bg-red-50 px-4 font-mono! text-[12px]! font-medium text-red-600 transition-all hover:bg-red-100 disabled:opacity-50 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                        >
                            Discard
                        </button>
                        {canManageSchedule && (
                            <button
                                type="button"
                                onClick={() => handleSave(leavePending)}
                                disabled={saving}
                                className="flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700 disabled:opacity-50"
                            >
                                {saving ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                ) : (
                                    <Save className="h-3.5 w-3.5" />
                                )}
                                Save &amp; leave
                            </button>
                        )}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

/* ─── Shift Badge ─────────────────────────────────────────── */

function ShiftBadge({
    start,
    end,
}: {
    start: string | null;
    end: string | null;
}) {
    const label = getShiftLabel(start, end);
    const style = getShiftStyle(start, end);
    const isOff = !start && !end;

    return (
        <div
            className={`flex flex-col items-center rounded-lg border px-2 py-2 ${style}`}
        >
            <span className="text-[11px] font-bold">{label}</span>
            {!isOff && start && (
                <span className="mt-0.5 text-[8px] leading-tight opacity-70">
                    {formatTime12(start)} – {formatTime12(end)}
                </span>
            )}
        </div>
    );
}

/* ─── Edit Schedule Modal ─────────────────────────────────── */

function EditScheduleModal({
    member,
    team,
    weekStart,
    weekDates,
    memberSchedules,
    getOtherMemberSchedules,
    onSave,
    onClose,
}: {
    member: User;
    team: Team;
    weekStart: string;
    weekDates: string[];
    memberSchedules: Map<string, Schedule>;
    getOtherMemberSchedules: (userId: number) => Map<string, Schedule>;
    onSave: (schedules: Map<string, Schedule>) => void;
    onClose: () => void;
}) {
    const [local, setLocal] = useState<Map<string, Schedule>>(
        new Map(memberSchedules),
    );
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const otherMembers = team.members.filter((m) => m.id !== member.id);
    const today = todayStr();

    const isDirty = (() => {
        if (local.size !== memberSchedules.size) return true;
        for (const [date, val] of local) {
            const orig = memberSchedules.get(date);
            if (!orig) return true;
            if (
                orig.start_time !== val.start_time ||
                orig.end_time !== val.end_time
            )
                return true;
        }
        return false;
    })();

    const requestClose = () => {
        if (isDirty) {
            setConfirmDiscard(true);
            return;
        }
        onClose();
    };

    const setDay = (date: string, start: string | null, end: string | null) => {
        setLocal((prev) => {
            const next = new Map(prev);
            next.set(date, {
                user_id: member.id,
                date,
                start_time: start,
                end_time: end,
            });
            return next;
        });
    };

    const clearDay = (date: string) => {
        setLocal((prev) => {
            const next = new Map(prev);
            next.delete(date);
            return next;
        });
    };

    const fillDays = (days: number[], shift: ShiftKey) => {
        const def = SHIFT_PRESETS[shift];
        setLocal((prev) => {
            const next = new Map(prev);
            for (const i of days) {
                const date = weekDates[i];
                next.set(date, {
                    user_id: member.id,
                    date,
                    start_time: def.start,
                    end_time: def.end,
                });
            }
            return next;
        });
    };

    const fillAll = (shift: ShiftKey) => fillDays([0, 1, 2, 3, 4, 5, 6], shift);

    const fillAllOff = () => {
        setLocal(() => {
            const next = new Map<string, Schedule>();
            for (const date of weekDates)
                next.set(date, {
                    user_id: member.id,
                    date,
                    start_time: null,
                    end_time: null,
                });
            return next;
        });
    };

    const fillWeekdays = (shift: ShiftKey) => {
        const def = SHIFT_PRESETS[shift];
        setLocal(() => {
            const next = new Map<string, Schedule>();
            for (let i = 0; i < 7; i++) {
                const date = weekDates[i];
                if (i < 5) {
                    next.set(date, {
                        user_id: member.id,
                        date,
                        start_time: def.start,
                        end_time: def.end,
                    });
                } else {
                    next.set(date, {
                        user_id: member.id,
                        date,
                        start_time: null,
                        end_time: null,
                    });
                }
            }
            return next;
        });
    };

    const clearAll = () => setLocal(new Map());

    const copyFrom = (sourceId: number) => {
        const source = getOtherMemberSchedules(sourceId);
        const next = new Map<string, Schedule>();
        for (const [date, s] of source)
            next.set(date, { ...s, user_id: member.id });
        setLocal(next);
    };

    return (
        <>
            <div
                className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-[5vh]"
                onClick={requestClose}
            >
                <div
                    className="w-full max-w-3xl rounded-2xl bg-white shadow-2xl dark:bg-zinc-900"
                    onClick={(e) => e.stopPropagation()}
                >
                    {/* Header — with Clear All */}
                    <div className="flex items-center justify-between border-b border-black/6 px-6 py-4 dark:border-white/6">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/10 text-[13px] font-bold text-brand-700 dark:text-brand-400">
                                {getInitials(member.name)}
                            </div>
                            <div>
                                <h2 className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                                    Edit Schedule — {member.name}
                                </h2>
                                <p className="text-[12px] text-gray-400">
                                    Week of {getWeekLabel(weekStart)}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={clearAll}
                                className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 text-[11px] font-medium text-red-600 transition-all hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20"
                            >
                                <Eraser className="h-3.5 w-3.5" />
                                Clear All
                            </button>
                            <button
                                onClick={requestClose}
                                className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-stone-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    {/* Quick Fill */}
                    <div className="border-b border-black/6 px-6 py-4 dark:border-white/6">
                        <p className="mb-3 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Quick Fill
                        </p>

                        {/* Everyday (Mon – Sun) */}
                        <div className="mb-3">
                            <p className="mb-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                Everyday (Mon – Sun)
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <QuickBtn
                                    label="All AM"
                                    sub="6 AM – 3 PM"
                                    icon={Sunrise}
                                    cls="border-amber-200 bg-amber-500/5 text-amber-700 hover:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400"
                                    onClick={() => fillAll('am')}
                                />
                                <QuickBtn
                                    label="All PM"
                                    sub="3 PM – 12 MN"
                                    icon={Moon}
                                    cls="border-indigo-200 bg-indigo-500/5 text-indigo-700 hover:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400"
                                    onClick={() => fillAll('pm')}
                                />
                                <QuickBtn
                                    label="All MID"
                                    sub="9 AM – 6 PM"
                                    icon={Sun}
                                    cls="border-blue-light-200 bg-blue-light-500/5 text-blue-light-700 hover:bg-blue-light-500/10 dark:border-blue-light-500/20 dark:text-blue-light-400"
                                    onClick={() => fillAll('mid')}
                                />
                                <QuickBtn
                                    label="All OFF"
                                    sub="Day Off"
                                    icon={Moon}
                                    cls="border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
                                    onClick={fillAllOff}
                                />
                            </div>
                        </div>

                        {/* Copy from */}
                        {otherMembers.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Copy className="h-3 w-3 text-gray-400" />
                                <span className="text-[11px] text-gray-500">
                                    Copy from:
                                </span>
                                {otherMembers.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        onClick={() => copyFrom(m.id)}
                                        className="rounded-md border border-black/6 bg-stone-50 px-2 py-1 text-[10px] font-medium text-gray-500 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
                                    >
                                        {m.name}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Day-by-day */}
                    <div className="px-6 py-4">
                        <p className="mb-3 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Daily Schedule
                        </p>
                        <div className="space-y-2">
                            {weekDates.map((date, i) => (
                                <DayRow
                                    key={date}
                                    date={date}
                                    dayLabel={DAYS_FULL[i]}
                                    isToday={date === today}
                                    isWeekend={i >= 5}
                                    schedule={local.get(date) ?? null}
                                    onSet={(start, end) =>
                                        setDay(date, start, end)
                                    }
                                    onClear={() => clearDay(date)}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-2 border-t border-black/6 px-6 py-4 dark:border-white/6">
                        <button
                            type="button"
                            onClick={requestClose}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={() => onSave(local)}
                            className="flex h-9 items-center gap-1.5 rounded-lg bg-brand-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700"
                        >
                            <Save className="h-3.5 w-3.5" />
                            Apply Changes
                        </button>
                    </div>
                </div>
            </div>

            <AlertDialog open={confirmDiscard} onOpenChange={setConfirmDiscard}>
                <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                            Discard changes?
                        </AlertDialogTitle>
                        <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                            Your edits to <strong>{member.name}</strong>'s week
                            haven't been applied yet. Closing now will lose
                            them.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter className="mt-4 gap-2">
                        <AlertDialogCancel className="h-9 rounded-lg border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">
                            Keep editing
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                setConfirmDiscard(false);
                                onClose();
                            }}
                            className="h-9 rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                        >
                            Discard
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

/* ─── Quick Button ────────────────────────────────────────── */

function QuickBtn({
    label,
    sub,
    icon: Icon,
    cls,
    onClick,
}: {
    label: string;
    sub: string;
    icon: React.ComponentType<{ className?: string }>;
    cls: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 transition-all ${cls}`}
        >
            <Icon className="h-3.5 w-3.5" />
            <div className="text-left">
                <span className="block text-[11px] leading-tight font-semibold">
                    {label}
                </span>
                <span className="block text-[9px] leading-tight opacity-60">
                    {sub}
                </span>
            </div>
        </button>
    );
}

/* ─── Day Row ─────────────────────────────────────────────── */

function DayRow({
    date,
    dayLabel,
    isToday,
    isWeekend,
    schedule,
    onSet,
    onClear,
}: {
    date: string;
    dayLabel: string;
    isToday: boolean;
    isWeekend: boolean;
    schedule: Schedule | null;
    onSet: (start: string | null, end: string | null) => void;
    onClear: () => void;
}) {
    const [showCustom, setShowCustom] = useState(false);
    const currentShift = schedule
        ? detectShift(schedule.start_time, schedule.end_time)
        : null;
    const [customStart, setCustomStart] = useState(
        currentShift === 'custom' ? (schedule?.start_time ?? '09:00') : '09:00',
    );
    const [customEnd, setCustomEnd] = useState(
        currentShift === 'custom' ? (schedule?.end_time ?? '18:00') : '18:00',
    );

    return (
        <div
            className={[
                'rounded-xl border p-3 transition-all',
                isToday
                    ? 'border-brand-300 bg-brand-50/30 dark:border-brand-500/30 dark:bg-brand-500/5'
                    : isWeekend
                      ? 'border-black/4 bg-stone-50/60 dark:border-white/4 dark:bg-white/[0.015]'
                      : 'border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900',
            ].join(' ')}
        >
            <div className="mb-2.5 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span
                        className={[
                            'font-mono text-[11px] font-bold tracking-wide',
                            isToday
                                ? 'text-brand-600 dark:text-brand-400'
                                : isWeekend
                                  ? 'text-gray-400 dark:text-gray-500'
                                  : 'text-gray-700 dark:text-gray-300',
                        ].join(' ')}
                    >
                        {dayLabel}
                    </span>
                    <span className="font-mono text-[10px] text-gray-400">
                        {formatDateShort(date)}
                    </span>
                    {isToday && (
                        <span className="rounded-full bg-brand-600 px-1.5 py-0.5 text-[8px] font-bold text-white">
                            TODAY
                        </span>
                    )}
                    {isWeekend && !isToday && (
                        <span className="rounded-full bg-gray-200 px-1.5 py-0.5 text-[8px] font-medium text-gray-500 dark:bg-zinc-700 dark:text-gray-400">
                            WEEKEND
                        </span>
                    )}
                </div>
                {currentShift && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="text-[10px] text-gray-400 transition-colors hover:text-red-500"
                    >
                        Clear
                    </button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-1.5">
                {(['am', 'pm', 'mid'] as const).map((key) => {
                    const def = SHIFT_PRESETS[key];
                    const isActive = currentShift === key;
                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => {
                                onSet(def.start, def.end);
                                setShowCustom(false);
                            }}
                            className={[
                                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 transition-all',
                                isActive
                                    ? `${def.badge} font-semibold ring-2 ring-current/10`
                                    : 'border-black/6 bg-white text-gray-500 hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:border-white/12 dark:hover:bg-zinc-700',
                            ].join(' ')}
                        >
                            <def.icon className="h-3.5 w-3.5" />
                            <div className="text-left">
                                <span className="block text-[11px] leading-tight font-medium">
                                    {def.short}
                                </span>
                                <span className="block text-[8px] leading-tight opacity-60">
                                    {formatTime12(def.start)} –{' '}
                                    {formatTime12(def.end)}
                                </span>
                            </div>
                        </button>
                    );
                })}

                <button
                    type="button"
                    onClick={() => {
                        onSet(null, null);
                        setShowCustom(false);
                    }}
                    className={[
                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 transition-all',
                        currentShift === 'off'
                            ? 'border-gray-200 bg-gray-100 font-semibold text-gray-500 ring-2 ring-gray-300/30 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400'
                            : 'border-black/6 bg-white text-gray-500 hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:border-white/12 dark:hover:bg-zinc-700',
                    ].join(' ')}
                >
                    <Moon className="h-3.5 w-3.5" />
                    <span className="text-[11px] font-medium">OFF</span>
                </button>

                <button
                    type="button"
                    onClick={() => setShowCustom(!showCustom)}
                    className={[
                        'inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 transition-all',
                        currentShift === 'custom'
                            ? 'border-brand-200 bg-brand-500/10 font-semibold text-brand-700 ring-2 ring-brand-300/20 dark:border-brand-500/20 dark:text-brand-400'
                            : 'border-black/6 bg-white text-gray-500 hover:border-black/12 hover:bg-stone-50 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-400 dark:hover:border-white/12 dark:hover:bg-zinc-700',
                    ].join(' ')}
                >
                    <Clock className="h-3.5 w-3.5" />
                    <span className="text-[11px] font-medium">Custom</span>
                </button>
            </div>

            {showCustom && (
                <div className="mt-2.5 flex items-end gap-2 rounded-lg border border-dashed border-black/8 bg-stone-50/50 p-3 dark:border-white/8 dark:bg-zinc-800/50">
                    <div className="flex-1">
                        <label className="mb-1 block font-mono text-[9px] font-medium tracking-wider text-gray-400 uppercase">
                            Start Time
                        </label>
                        <input
                            type="time"
                            value={customStart}
                            onChange={(e) => setCustomStart(e.target.value)}
                            className="h-8 w-full rounded-lg border border-black/8 bg-white px-2 font-mono text-[12px] text-gray-700 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500/20 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300"
                        />
                    </div>
                    <div className="flex-1">
                        <label className="mb-1 block font-mono text-[9px] font-medium tracking-wider text-gray-400 uppercase">
                            End Time
                        </label>
                        <input
                            type="time"
                            value={customEnd}
                            onChange={(e) => setCustomEnd(e.target.value)}
                            className="h-8 w-full rounded-lg border border-black/8 bg-white px-2 font-mono text-[12px] text-gray-700 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500/20 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            onSet(customStart, customEnd);
                            setShowCustom(false);
                        }}
                        className="flex h-8 items-center rounded-lg bg-brand-600 px-3 text-[11px] font-medium text-white transition-all hover:bg-brand-700"
                    >
                        Set
                    </button>
                </div>
            )}
        </div>
    );
}
