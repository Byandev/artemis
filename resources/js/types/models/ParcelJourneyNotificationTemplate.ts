
export interface ParcelJourneyNotificationTemplate
{
    id: number;
    workspace_id: number;
    type: 'sms' | 'chat',
    activity: 'for-delivery' | 'arrival' | 'departure',
    receiver: 'customer' | 'rider',
    message: string
}
