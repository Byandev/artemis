import { Head, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';

type Sim = {
    id: number;
    phone_number: string;
    carrier: string;
    label: string | null;
};

interface Props {
    workspace: Workspace;
    sims: Sim[];
}

export default function SmsSend({ workspace, sims }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        sim_id: sims[0]?.id?.toString() ?? '',
        to: '',
        message: '',
    });

    const segments = Math.max(1, Math.ceil(data.message.length / 160));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/sms/send`, {
            preserveScroll: true,
            onSuccess: () => reset('to', 'message'),
        });
    };

    return (
        <AppLayout>
            <Head title="Send SMS" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Send SMS</h1>
                    <p className="text-sm text-muted-foreground">
                        Send a single message instantly through one of your SIMs.
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
                            <Label htmlFor="to">To</Label>
                            <Input
                                id="to"
                                placeholder="+639171234567 or 09171234567"
                                value={data.to}
                                onChange={(e) => setData('to', e.target.value)}
                            />
                            {errors.to && <p className="text-sm text-red-600">{errors.to}</p>}
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="message">Message</Label>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {data.message.length} chars · {segments} segment
                                    {segments !== 1 && 's'}
                                </span>
                            </div>
                            <Textarea
                                id="message"
                                rows={5}
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
                                <Send className="mr-2 size-4" />
                                {processing ? 'Sending…' : 'Send SMS'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}