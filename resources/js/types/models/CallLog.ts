export interface CallLog {
    id: number;
    user_id: string;
    assignee_user_id: number | null;
    phone_number: string;
    type: string;
    duration: number;
    call_date: string;
    call_time: string;
    /** Whoever placed the call, resolved server-side from whichever id the
     *  syncing app supplied. Null when neither id maps to a known user. */
    called_by: string | null;
}
