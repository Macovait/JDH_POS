/**
 * API client — talks to the PHP backend at /api/v1/store/*
 * All calls include the tenant ID via the X-Tenant-ID header.
 */

const BASE = typeof window === 'undefined'
  ? (process.env.PHP_API_URL ?? 'http://localhost/JDH_POS/public/api/v1')
  : (process.env.NEXT_PUBLIC_API_URL ?? '/api/v1');

export interface StoreSettings {
  store_name: string;
  currency: string;
  primary_color: string;
  store_logo?: string;
  whatsapp_number?: string;
  site_title?: string;
  site_description?: string;
  seo_title?: string;
  seo_description?: string;
  online_store_enabled: string;
  announcement_bar_text?: string;
  announcement_bar_enabled?: string;
  announcement_bar_color?: string;
  show_deal_spotlight?: string;
  show_newsletter?: string;
  show_recently_viewed?: string;
  deal_of_the_day_product_id?: string;
  // Header
  header_style?: string;
  header_sticky?: string;
  header_top_bar?: string;
  header_top_bar_text?: string;
  header_top_bar_bg?: string;
  header_top_bar_text_color?: string;
  header_show_cart?: string;
  header_show_account?: string;
  header_show_search?: string;
  header_show_wishlist?: string;
  header_transparent_home?: string;
  header_bg_color?: string;
  header_text_color?: string;
  header_mobile_style?: string;
  header_dropdown_style?: string;
  // Social
  facebook_url?: string;
  instagram_url?: string;
  twitter_url?: string;
  tiktok_url?: string;
  youtube_url?: string;
  linkedin_url?: string;
  pinterest_url?: string;
  // Contact
  store_email?: string;
  store_phone?: string;
  store_address?: string;
}

export interface TenantInfo {
  tenant_id: number;
  name: string;
  slug: string;
  currency: string;
  logo?: string;
  primary_color: string;
  whatsapp?: string;
  online_store_enabled: boolean;
}

export interface Product {
  id: number;
  name: string;
  sku: string;
  price: number;
  selling_price?: number;
  effective_price: number;
  has_discount: boolean;
  discount_pct: number;
  image?: string;
  description?: string;
  category_id?: number;
  category_name?: string;
  featured?: boolean;
  is_new?: boolean;
  in_stock: boolean;
  is_low_stock?: boolean;
  stock_quantity: number;
  avg_rating?: number;
  review_count?: number;
  currency?: string;
  reviews?: Review[];
  related_products?: Product[];
}

export interface Category {
  id: number;
  name: string;
  image?: string;
  description?: string;
  product_count: number;
}

export interface Banner {
  id: number;
  title: string;
  subtitle?: string;
  image_url?: string;
  link_url?: string;
  button_text?: string;
}

export interface Block {
  id: number;
  name: string;
  content: string;
  bg_color?: string;
  text_color?: string;
  padding?: string;
}

export interface TypedBlock {
  id: number;
  name: string;
  type: string;
  props: Record<string, unknown>;
  bg_color?: string;
  text_color?: string;
  padding?: string;
  section_class?: string;
  display_order?: number;
  is_active?: boolean;
  _fallback_html?: string;
}

export interface Review {
  id: number;
  customer_name: string;
  rating: number;
  comment: string;
  review_text: string;
  created_at: string;
}

export interface CartItem {
  product_id: number;
  name: string;
  price: number;
  quantity: number;
  image?: string;
}

export interface WishlistItem {
  id: number;
  name: string;
  price: number;
  image?: string;
}

export interface OrderPayload {
  customer_name: string;
  customer_phone: string;
  customer_email?: string;
  delivery_address?: string;
  notes?: string;
  payment_method: 'cash' | 'mpesa' | 'card';
  coupon_code?: string;
  items: { product_id: number; quantity: number }[];
}

export interface OrderResult {
  order_id: number;
  order_number: string;
  uuid: string;
  total: number;
  discount: number;
  status: string;
  track_url: string;
  message: string;
}

export interface TimelineEvent {
  status: string;
  label: string;
  timestamp: string;
  notes?: string;
}

export interface TrackedOrder {
  order_number: string;
  uuid: string;
  customer_name: string;
  customer_phone?: string;
  customer_email?: string;
  status: string;
  payment_status: string;
  payment_method: string;
  total: number;
  tax_amount?: number;
  shipping_amount?: number;
  discount: number;
  delivery_address?: string;
  shipping_address?: string;
  billing_address?: string;
  notes?: string;
  placed_at?: string;
  shipped_at?: string;
  delivered_at?: string;
  created_at: string;
  updated_at?: string;
  items: { product_name: string; quantity: number; unit_price: number; subtotal: number }[];
  status_info: { label: string; icon: string; color: string };
  timeline: TimelineEvent[];
}

async function apiFetch<T>(
  path: string,
  tenantId: number,
  options: RequestInit = {}
): Promise<T> {
  const url = `${BASE}${path}`;
  const res = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-ID': String(tenantId),
      ...(options.headers ?? {}),
    },
    next: { revalidate: 60 },
  });
  const json = await res.json();
  if (!res.ok || json.success === false) {
    throw new Error(json.error?.message ?? 'API error');
  }
  return json.data as T;
}

export const storeApi = {
  getTenant: (tenantId: number) =>
    apiFetch<TenantInfo>('/store/tenant', tenantId),

  getSettings: (tenantId: number) =>
    apiFetch<StoreSettings>('/store/settings', tenantId),

  getBanners: (tenantId: number) =>
    apiFetch<Banner[]>('/store/banners', tenantId),

  getBlocks: (tenantId: number) =>
    apiFetch<Block[]>('/store/blocks', tenantId),

  getTypedBlocks: (tenantId: number) =>
    apiFetch<TypedBlock[]>('/store-blocks', tenantId),

  getCategories: (tenantId: number) =>
    apiFetch<Category[]>('/store/categories', tenantId),

  getProducts: (tenantId: number, params: Record<string, string | number | undefined> = {}) => {
    const filtered = Object.fromEntries(
      Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])
    );
    const qs = new URLSearchParams(filtered).toString();
    return apiFetch<{ products: Product[]; total: number; page: number; pages: number }>(
      `/store/products${qs ? '?' + qs : ''}`,
      tenantId
    );
  },

  getProduct: (tenantId: number, id: number) =>
    apiFetch<Product>(`/store/products/${id}`, tenantId),

  placeOrder: (tenantId: number, payload: OrderPayload) =>
    apiFetch<OrderResult>('/store/orders', tenantId, {
      method: 'POST',
      body: JSON.stringify(payload),
      next: { revalidate: 0 },
    }),

  trackOrder: (tenantId: number, uuid: string) =>
    apiFetch<TrackedOrder>(`/store/orders/${uuid}`, tenantId),

  validateCart: (tenantId: number, items: { product_id: number; quantity: number }[]) =>
    apiFetch<{ items: unknown[]; errors: unknown[]; valid: boolean }>(
      '/store/cart/validate',
      tenantId,
      { method: 'POST', body: JSON.stringify({ items }), next: { revalidate: 0 } }
    ),

  validateCoupon: (tenantId: number, code: string) =>
    apiFetch<{ code: string; discount_type: string; discount_value: number }>(
      '/store/coupons/validate',
      tenantId,
      { method: 'POST', body: JSON.stringify({ code }), next: { revalidate: 0 } }
    ),
};
