export interface ParcelJourney {
  id: number;
  order_id: number;
  status: string;
  rider_name: string | null;
  rider_mobile: string | null;
  rider_id: number | null;
  note: string;
  created_at: string | null; // timestamp
  updated_at: string | null; // timestamp
}