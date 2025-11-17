import { FacebookAccount } from '@/types/models/FacebookAccount';

export interface AdAccount {
    id: number;
    name: string;
    facebook_account_id: number;
    currency: string;
    country_code: string;
    status: number;
    facebook_accounts: FacebookAccount[]
}
