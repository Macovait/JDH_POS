/**
 * Type definitions for Jakababa POS Mobile
 */

// Product Types
export interface Product {
  id: number;
  name: string;
  description?: string;
  sku?: string;
  barcode?: string;
  price: number;
  cost_price?: number;
  quantity: number;
  min_stock?: number;
  category_id?: number;
  category_name?: string;
  tax_rate?: number;
  is_active: boolean;
  image_url?: string;
  created_at: string;
  updated_at: string;
}

export interface Category {
  id: number;
  name: string;
  description?: string;
  parent_id?: number;
  icon?: string;
  image_url?: string;
  product_count?: number;
}

// Cart Types
export interface CartItem extends Product {
  cart_quantity: number;
  cart_total: number;
}

// Sale Types
export interface Sale {
  id: number;
  receipt_number: string;
  customer_id?: number;
  customer_name?: string;
  user_id: number;
  user_name: string;
  branch_id: number;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total_amount: number;
  payment_method: 'cash' | 'card' | 'mpesa' | 'bank_transfer' | 'credit' | 'mixed';
  status: 'completed' | 'pending' | 'cancelled' | 'refunded';
  created_at: string;
}

export interface SaleItem {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: number;
  total_price: number;
  discount_amount?: number;
}

export interface SaleDetail extends Sale {
  items: SaleItem[];
  payments: Payment[];
}

export interface Payment {
  id: number;
  method: string;
  amount: number;
  reference?: string;
  status: string;
}

// Customer Types
export interface Customer {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  loyalty_points?: number;
  total_spent?: number;
  visit_count?: number;
  last_visit?: string;
}

// Branch Types
export interface Branch {
  id: number;
  name: string;
  address?: string;
  phone?: string;
  email?: string;
  is_active: boolean;
  manager_name?: string;
}

// User Types
export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'cashier' | 'staff';
  branch_id?: number;
  is_active: boolean;
  permissions: string[];
}

// Auth Types
export interface AuthState {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: User | null;
  tenant: Tenant | null;
  token: string | null;
}

export interface Tenant {
  id: number;
  name: string;
  email: string;
  phone?: string;
  address?: string;
  logo_url?: string;
  currency: string;
  tax_rate: number;
  plan: string;
  plan_features: string[];
}

// Theme Types
export interface Theme {
  colors: {
    primary: string;
    primaryDark: string;
    secondary: string;
    background: string;
    surface: string;
    text: string;
    textSecondary: string;
    border: string;
    error: string;
    success: string;
    warning: string;
  };
  spacing: {
    xs: number;
    sm: number;
    md: number;
    lg: number;
    xl: number;
  };
  fonts: {
    regular: string;
    medium: string;
    bold: string;
  };
}

// API Types
export interface ApiResponse<T> {
  data: T;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message?: string;
}

export interface ApiError {
  error: string;
  code?: string;
  details?: Record<string, string[]>;
}

// Sync Types
export interface SyncQueueItem {
  id: string;
  type: 'sale' | 'product_update' | 'stock_adjustment';
  payload: Record<string, any>;
  status: 'pending' | 'syncing' | 'failed' | 'completed';
  retry_count: number;
  created_at: string;
  last_attempt?: string;
  error_message?: string;
}

// Report Types
export interface SalesReport {
  period: string;
  start_date: string;
  end_date: string;
  summary: {
    total_sales: number;
    total_transactions: number;
    total_items: number;
    average_transaction: number;
  };
  daily_breakdown: Array<{
    date: string;
    sales: number;
    transactions: number;
  }>;
  by_category: Array<{
    category_id: number;
    category_name: string;
    sales: number;
    count: number;
  }>;
  by_payment_method: Array<{
    method: string;
    amount: number;
    count: number;
  }>;
  top_products: Array<{
    product_id: number;
    product_name: string;
    quantity: number;
    revenue: number;
  }>;
}

// Settings Types
export interface AppSettings {
  printer_enabled: boolean;
  printer_type?: 'bluetooth' | 'usb' | 'network';
  printer_address?: string;
  receipt_footer?: string;
  auto_sync: boolean;
  sync_interval: number;
  dark_mode: boolean;
  language: string;
  currency: string;
  tax_inclusive: boolean;
}

// Checkout Types
export interface CheckoutData {
  items: CartItem[];
  customer_id?: number;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  payment_method: string;
  tendered_amount: number;
  change_amount: number;
  notes?: string;
}

// Navigation Types
export type RootStackParamList = {
  Login: undefined;
  Main: undefined;
  ProductDetail: {productId: number};
  SaleDetail: {saleId: number};
  Checkout: {cart: CartItem[]; total: number};
  Receipt: {sale: SaleDetail};
};

export type MainTabParamList = {
  POS: undefined;
  Products: undefined;
  Sales: undefined;
  Reports: undefined;
  Settings: undefined;
};
