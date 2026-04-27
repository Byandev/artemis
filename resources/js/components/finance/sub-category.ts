export type SubCategory =
    | 'ad_spent' | 'cogs' | 'subscription' | 'shipping_fee' | 'delivery_fee'
    | 'operation_expense' | 'salary' | 'transfer_fee' | 'seminar_fee' | 'rent' | 'others';

export const SUB_CATEGORIES: { value: SubCategory; label: string }[] = [
    { value: 'ad_spent', label: 'Ad Spent' },
    { value: 'cogs', label: 'COGS' },
    { value: 'subscription', label: 'Subscription' },
    { value: 'shipping_fee', label: 'Shipping Fee' },
    { value: 'delivery_fee', label: 'Delivery Fee' },
    { value: 'operation_expense', label: 'Operation Expense' },
    { value: 'salary', label: 'Salary' },
    { value: 'transfer_fee', label: 'Transfer Fee' },
    { value: 'seminar_fee', label: 'Seminar Fee' },
    { value: 'rent', label: 'Rent' },
    { value: 'others', label: 'Others' },
];

export const SUB_CATEGORY_LABEL: Record<SubCategory, string> = Object.fromEntries(
    SUB_CATEGORIES.map(s => [s.value, s.label])
) as Record<SubCategory, string>;
