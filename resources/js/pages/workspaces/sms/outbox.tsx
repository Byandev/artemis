import { Head } from '@inertiajs/react';

import { StatusBadge } from '@/components/ui/status-badge';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';

type Message = {
    id: number;
    to_number: string;
    from_number: string;
    message: string;
    status: string;
    sent_at: string | null;
    delivered_at: string | null;
    created_at: string;
};

interface Props {
    workspace: Workspace;
    messages: PaginatedData<Message>;
}

function formatTimestamp(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function SmsOutbox({ messages }: Props) {
    return (
        <AppLayout>
            <Head title="Outbox" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Outbox</h1>
                    <p className="text-sm text-muted-foreground">
                        Messages you've sent — newest first.
                    </p>
                </div>

                {messages.data.length === 0 ? (
                    <Card className="p-10 text-center text-sm text-muted-foreground">
                        No messages sent yet. Head to Send SMS to fire your first message.
                    </Card>
                ) : (
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Created</TableHead>
                                    <TableHead>To</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Sent</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {messages.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatTimestamp(m.created_at)}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {m.to_number}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate">
                                            {m.message}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={m.status} />
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {m.sent_at ? (
                                                formatTimestamp(m.sent_at)
                                            ) : (
                                                <span className="text-muted-foreground/50">—</span>
                                            )}
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