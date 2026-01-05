import { Order } from './Orders';

export interface ParcelJourneyNotification {
    id: number;
    order_id: number;
    parcel_journey_id: number;
    type: 'sms' | 'chat';
    status: 'pending' | 'sent' | 'delivered' | 'failed';
    receiver_name: string;
    receiver_identity: string;
    message: string;
    created_at: string;
    updated_at: string;
    order?: Order;
}
