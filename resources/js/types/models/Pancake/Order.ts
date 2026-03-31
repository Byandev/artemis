import { Page } from "../Page";
import { Shop } from "../Shop";
import { ShippingAddress } from '@/types/models/Pancake/ShippingAddress';
import { OrderItem } from '@/types/models/Pancake/OrderItem';

export interface Order {
  id: number;
  order_number: string;
  status: number;
  status_name: string;
  shop_id: number;
  shop: Shop;
  page_id: number;
  page: Page;
  workspace_id: number;
  total_amount: number;
  final_amount: number;
  discount: number;
  ad_id: number | null;
  fb_id: string;
  delivery_attempts: number | null;
  first_delivery_attempt: string | null; // timestamp
  inserted_at: string; // timestamp
  confirmed_at: string | null;
  returned_at: string | null;
  returning_at: string | null;
  delivered_at: string | null;
  shipped_at: string | null;
  tracking_code: string | null;
  parcel_status: string | null;
  created_at: string | null;
  updated_at: string | null;
  shipping_address?: ShippingAddress;
  items?: OrderItem[];
  cx_rts_rate?: number | null;
}
