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
    from_number: string;
    to_number: string;
    message: string;
    status: string;
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

export default function SmsInbox({ messages }: Props) {
    return (
        <AppLayout>
            <Head title="Inbox" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Inbox</h1>
                    <p className="text-sm text-muted-foreground">
                        Replies and inbound SMS to your SIMs.
                    </p>
                </div>

                {messages.data.length === 0 ? (
                    <Card className="p-10 text-center text-sm text-muted-foreground">
                        Your inbox is empty. When customers reply to your messages, they'll
                        show up here.
                    </Card>
                ) : (
                    <Card>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Time</TableHead>
                                    <TableHead>From</TableHead>
                                    <TableHead>SIM</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {messages.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {formatTimestamp(m.created_at)}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {m.from_number}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {m.to_number}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate">
                                            {m.message}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={m.status} />
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