import { Page } from '@/types/models/Page';

export interface PageDailyBudgetRecord {
    id: number;
    workspace_id: number;
    page_id: number;
    date: string;
    budget: string;
    created_at: string;
    updated_at: string;

    page?: Page;
}
