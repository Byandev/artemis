import {
    Field,
    Footer,
    inputCls,
} from '@/components/finance/account-form-dialog';
import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useMemo } from 'react';

interface UserOption {
    id: number;
    name: string;
}

export interface RequestFund {
    id: number;
    request_date: string;
    reference_no: string;
    requested_by: number;
    charge_to: number;
    purpose: string;
    amount_requested: number | string;
    date_needed: string | null;
    approved_by: number | null;
    status: string;
    remarks: string | null;
    requester?: UserOption | null;
    charge_to_user?: UserOption | null;
    approver?: UserOption | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    requestFund?: RequestFund | null;
    workspaceSlug: string;
    users: UserOption[];
}

const today = () => new Date().toISOString().slice(0, 10);

export function RequestFundFormDialog({
    open,
    onOpenChange,
    requestFund,
    workspaceSlug,
    users,
}: Props) {
    const isEditing = !!requestFund;
    const { auth } = usePage<SharedData>().props;
    const meId = auth?.user?.id;

    // The signed-in user sits at the top of the requester list, tagged "(Me)".
    const orderedUsers = useMemo(() => {
        const me = users.find((u) => u.id === meId);
        const rest = users.filter((u) => u.id !== meId);
        return me ? [{ id: me.id, name: `${me.name} (Me)` }, ...rest] : users;
    }, [users, meId]);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            request_date: today(),
            requested_by: (meId ?? '') as number | '',
            charge_to: '' as number | '',
            purpose: '',
            amount_requested: '',
            date_needed: '',
            remarks: '',
        });

    // Stable initial dates for the picker (only recomputed when the target record
    // changes), so selecting a date doesn't re-init flatpickr on every keystroke.
    const initialRequestDate =
        requestFund?.request_date?.slice(0, 10) ?? today();
    const initialDateNeeded = requestFund?.date_needed?.slice(0, 10) ?? '';

    useEffect(() => {
        if (!open) return;
        if (requestFund) {
            setData({
                request_date: requestFund.request_date?.slice(0, 10) ?? today(),
                requested_by: requestFund.requested_by ?? '',
                charge_to: requestFund.charge_to ?? '',
                purpose: requestFund.purpose ?? '',
                amount_requested: String(requestFund.amount_requested ?? ''),
                date_needed: requestFund.date_needed?.slice(0, 10) ?? '',
                remarks: requestFund.remarks ?? '',
            });
        } else {
            reset();
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, requestFund]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        const base = `/workspaces/${workspaceSlug}/finance/request-funds`;
        if (isEditing) {
            put(`${base}/${requestFund!.id}`, options);
        } else {
            post(base, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-lg dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Edit Fund Request'
                                : 'New Fund Request'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing ? (
                                <>
                                    <span className="font-mono text-gray-500 dark:text-gray-400">
                                        {requestFund?.reference_no}
                                    </span>{' '}
                                    · Update this fund request’s details.
                                </>
                            ) : (
                                'Create a new request for funds. A reference number is assigned automatically.'
                            )}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                label="Request Date"
                                required
                                error={errors.request_date}
                            >
                                <DatePicker
                                    key={`rd-${requestFund?.id ?? 'new'}-${open}`}
                                    id="request_date"
                                    mode="single"
                                    fullWidth
                                    clearable={false}
                                    defaultDate={initialRequestDate}
                                    placeholder="Select date"
                                    onChange={(_dates, dateStr) =>
                                        setData('request_date', dateStr)
                                    }
                                />
                            </Field>
                            <Field
                                label="Date Needed"
                                error={errors.date_needed}
                            >
                                <DatePicker
                                    key={`dn-${requestFund?.id ?? 'new'}-${open}`}
                                    id="date_needed"
                                    mode="single"
                                    fullWidth
                                    defaultDate={initialDateNeeded || undefined}
                                    placeholder="Select date"
                                    onChange={(_dates, dateStr) =>
                                        setData('date_needed', dateStr)
                                    }
                                />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <Field
                                label="Requested By"
                                required
                                error={errors.requested_by}
                            >
                                <select
                                    value={data.requested_by}
                                    onChange={(e) =>
                                        setData(
                                            'requested_by',
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                    className={inputCls}
                                >
                                    <option value="">Select…</option>
                                    {orderedUsers.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Charge To"
                                required
                                error={errors.charge_to}
                            >
                                <select
                                    value={data.charge_to}
                                    onChange={(e) =>
                                        setData(
                                            'charge_to',
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                    className={inputCls}
                                >
                                    <option value="">Select…</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>

                        <Field label="Purpose" required error={errors.purpose}>
                            <textarea
                                value={data.purpose}
                                onChange={(e) =>
                                    setData('purpose', e.target.value)
                                }
                                className={`${inputCls} min-h-[70px] resize-none py-2`}
                            />
                        </Field>

                        <Field
                            label="Amount Requested"
                            required
                            error={errors.amount_requested}
                        >
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.amount_requested}
                                onChange={(e) =>
                                    setData('amount_requested', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>

                        {isEditing && requestFund?.approver && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {requestFund.status} · approved by{' '}
                                {requestFund.approver.name}
                            </p>
                        )}

                        <Field label="Remarks" error={errors.remarks}>
                            <textarea
                                value={data.remarks ?? ''}
                                onChange={(e) =>
                                    setData('remarks', e.target.value)
                                }
                                className={`${inputCls} min-h-[60px] resize-none py-2`}
                            />
                        </Field>
                    </div>

                    <Footer
                        processing={processing}
                        isEditing={isEditing}
                        onCancel={() => onOpenChange(false)}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}
