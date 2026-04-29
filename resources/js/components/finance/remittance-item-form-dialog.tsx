import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, Footer, inputCls } from '@/components/finance/account-form-dialog';
import { useForm } from '@inertiajs/react';
import React, { useEffect } from 'react';

export interface FinanceRemittanceItem {
    id: number;
    waybill_number: string;
    order_number: string | null;
    shipping_date: string | null;
    sender_city: string | null;
    destination_city: string | null;
    package_billing_weight: number | string;
    item_value: number | string;
    value_added_fee: number | string;
    receivable_freight: number | string;
    total_shipping_cost: number | string;
    cod: number | string;
    cod_commission_rate: number | string;
    cod_commission: number | string;
    cod_commission_vat_fee: number | string;
    shipping_customer_code: string | null;
    signing_time: string | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: FinanceRemittanceItem | null;
    workspaceSlug: string;
    remittanceId: number;
}

const dateOnly = (v: string | null) => (v ? String(v).slice(0, 10) : '');

export function RemittanceItemFormDialog({ open, onOpenChange, item, workspaceSlug, remittanceId }: Props) {
    const { data, setData, put, processing, errors, reset, clearErrors } = useForm({
        waybill_number: '',
        order_number: '',
        shipping_date: '',
        sender_city: '',
        destination_city: '',
        package_billing_weight: '0',
        item_value: '0',
        value_added_fee: '0',
        receivable_freight: '0',
        total_shipping_cost: '0',
        cod: '0',
        cod_commission_rate: '0',
        cod_commission: '0',
        cod_commission_vat_fee: '0',
        shipping_customer_code: '',
        signing_time: '',
    });

    useEffect(() => {
        if (open && item) {
            setData({
                waybill_number: item.waybill_number ?? '',
                order_number: item.order_number ?? '',
                shipping_date: dateOnly(item.shipping_date),
                sender_city: item.sender_city ?? '',
                destination_city: item.destination_city ?? '',
                package_billing_weight: String(item.package_billing_weight ?? 0),
                item_value: String(item.item_value ?? 0),
                value_added_fee: String(item.value_added_fee ?? 0),
                receivable_freight: String(item.receivable_freight ?? 0),
                total_shipping_cost: String(item.total_shipping_cost ?? 0),
                cod: String(item.cod ?? 0),
                cod_commission_rate: String(item.cod_commission_rate ?? 0),
                cod_commission: String(item.cod_commission ?? 0),
                cod_commission_vat_fee: String(item.cod_commission_vat_fee ?? 0),
                shipping_customer_code: item.shipping_customer_code ?? '',
                signing_time: dateOnly(item.signing_time),
            });
        } else if (!open) {
            reset();
            clearErrors();
        }
    }, [open, item]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!item) return;
        put(`/workspaces/${workspaceSlug}/finance/remittances/${remittanceId}/items/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Edit Remittance Item
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            Update the values for this waybill row.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4 max-h-[70vh] overflow-y-auto">
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Waybill Number" required error={errors.waybill_number}>
                                <input type="text" value={data.waybill_number} onChange={(e) => setData('waybill_number', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Order Number" error={errors.order_number}>
                                <input type="text" value={data.order_number} onChange={(e) => setData('order_number', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Shipping Date" error={errors.shipping_date}>
                                <input type="date" value={data.shipping_date} onChange={(e) => setData('shipping_date', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Signing Time" error={errors.signing_time}>
                                <input type="date" value={data.signing_time} onChange={(e) => setData('signing_time', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Sender City" error={errors.sender_city}>
                                <input type="text" value={data.sender_city} onChange={(e) => setData('sender_city', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Destination City" error={errors.destination_city}>
                                <input type="text" value={data.destination_city} onChange={(e) => setData('destination_city', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Package Billing Weight" error={errors.package_billing_weight}>
                                <input type="number" step="0.01" value={data.package_billing_weight} onChange={(e) => setData('package_billing_weight', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Item Value" error={errors.item_value}>
                                <input type="number" step="0.01" value={data.item_value} onChange={(e) => setData('item_value', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Value Added Fee" error={errors.value_added_fee}>
                                <input type="number" step="0.01" value={data.value_added_fee} onChange={(e) => setData('value_added_fee', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="Receivable Freight" error={errors.receivable_freight}>
                                <input type="number" step="0.01" value={data.receivable_freight} onChange={(e) => setData('receivable_freight', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Total Shipping Cost" error={errors.total_shipping_cost}>
                                <input type="number" step="0.01" value={data.total_shipping_cost} onChange={(e) => setData('total_shipping_cost', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="COD" error={errors.cod}>
                                <input type="number" step="0.01" value={data.cod} onChange={(e) => setData('cod', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <Field label="COD Commission Rate" error={errors.cod_commission_rate}>
                                <input type="number" step="0.0001" value={data.cod_commission_rate} onChange={(e) => setData('cod_commission_rate', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="COD Commission" error={errors.cod_commission}>
                                <input type="number" step="0.01" value={data.cod_commission} onChange={(e) => setData('cod_commission', e.target.value)} className={inputCls} />
                            </Field>
                            <Field label="COD Commission VAT" error={errors.cod_commission_vat_fee}>
                                <input type="number" step="0.01" value={data.cod_commission_vat_fee} onChange={(e) => setData('cod_commission_vat_fee', e.target.value)} className={inputCls} />
                            </Field>
                        </div>

                        <Field label="Shipping Customer Code" error={errors.shipping_customer_code}>
                            <input type="text" value={data.shipping_customer_code} onChange={(e) => setData('shipping_customer_code', e.target.value)} className={inputCls} />
                        </Field>
                    </div>

                    <Footer processing={processing} isEditing={true} onCancel={() => onOpenChange(false)} />
                </form>
            </DialogContent>
        </Dialog>
    );
}
