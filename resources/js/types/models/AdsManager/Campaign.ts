import { AdAccount } from '@/types/models/AdAccount';


export interface Campaign {
    id: number;
    name: string;
    effective_status: string;
    daily_budget: number;
    ad_account_id: number;
    ad_account: AdAccount[];
    created_at: string;
    updated_at: string;
    start_time: string | null;
    end_time: string | null;
    status: string | null;
}
