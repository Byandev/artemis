export type WalletType = 'main' | 'backup';

/** Mirrors Modules\Finance\Enums\WalletType. */
export const WALLET_TYPES: { value: WalletType; label: string }[] = [
    { value: 'main', label: 'Main' },
    { value: 'backup', label: 'Backup' },
];

export interface WalletAccount {
    id: number;
    name: string;
    opening_balance: number;
    current_balance: number;
    currency: string;
    notes: string | null;
    is_active: boolean;
    is_user_wallet: boolean;
    wallet_type: WalletType | null;
}
