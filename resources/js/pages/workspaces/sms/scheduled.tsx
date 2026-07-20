import { Head, router, useForm } from '@inertiajs/react';
import { Timer, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';

type Sim = {
    id: number;
    phone_number: string;
    carrier: string;
    label: string | null;
};

type Scheduled = {
    id: number;
    to_number: string;
    message: string;
    scheduled_at: string;
};

interface Props {
    workspace: Workspace;
    scheduled: Scheduled[];
    sims: Sim[];
}

export default function SmsScheduled({ workspace, scheduled, sims }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        sim_id: sims[0]?.id?.toString() ?? '',
        to_number: '',
        message: '',
        scheduled_at: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/sms/scheduled`, {
            preserveScroll: true,
            onSuccess: () => reset('to_number', 'message', 'scheduled_at'),
        });
    };

    const cancel = (id: number) => {
        if (!confirm('Cancel this scheduled message?')) return;
        router.delete(`/workspaces/${workspace.slug}/sms/scheduled/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Scheduled" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Scheduled</h1>
                    <p className="text-sm text-muted-foreground">
                        Messages waiting to be sent.
                    </p>
                </div>

                <Card className="max-w-2xl p-6">
                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-2">
                            <Label>From SIM</Label>
                            <Select
                                value={data.sim_id}
                                onValueChange={(v) => setData('sim_id', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder={sims.length ? 'Select a SIM' : 'No active SIMs'}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {sims.map((s) => (
                                        <SelectItem key={s.id} value={s.id.toString()}>
                                            {s.phone_number} · {s.carrier.toUpperCase()}
                                            {s.label && ` · ${s.label}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.sim_id && (
                                <p className="text-sm text-red-600">{errors.sim_id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="to_number">To</Label>
                            <Input
                                id="to_number"
                                placeholder="+639171234567 or 09171234567"
                                value={data.to_number}
                                onChange={(e) => setData('to_number', e.target.value)}
                            />
                            {errors.to_number && (
                                <p className="text-sm text-red-600">{errors.to_number}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="scheduled_at">Send at</Label>
                            <Input
                                id="scheduled_at"
                                type="datetime-local"
                                value={data.scheduled_at}
                                onChange={(e) => setData('scheduled_at', e.target.value)}
                            />
                            {errors.scheduled_at && (
                                <p className="text-sm text-red-600">{errors.scheduled_at}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="message">Message</Label>
                            <Textarea
                                id="message"
                                rows={4}
                                placeholder="Type your message…"
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                className="resize-y"
                            />
                            {errors.message && (
                                <p className="text-sm text-red-600">{errors.message}</p>
                            )}
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing || !data.sim_id}>
                                <Timer className="mr-2 size-4" />
                                {processing ? 'Scheduling…' : 'Schedule message'}
                            </Button>
                        </div>
                    </form>
                </Card>

                {scheduled.length === 0 ? (
                    <Card className="p-10 text-center text-sm text-muted-foreground">
                        No scheduled messages.
                    </Card>
                ) : (
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {scheduled.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs">
                                            {new Date(m.scheduled_at).toLocaleString('en-PH')}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {m.to_number}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate">
                                            {m.message}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => cancel(m.id)}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}