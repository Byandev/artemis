import { AdAccount } from './AdAccounts';


export interface Campaign {
    id: number;
    name: string;
    facebook_account_id: number;
    currency: string;
    country_code: string;
    status: number;
    ad_account: AdAccount[];
}
