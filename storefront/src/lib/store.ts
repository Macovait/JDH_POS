/**
 * Global state — cart + tenant context
 * Uses Zustand with localStorage persistence
 */
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { CartItem, TenantInfo, WishlistItem } from './api';

interface CartStore {
  items: CartItem[];
  wishlist: WishlistItem[];
  tenantId: number;
  tenant: TenantInfo | null;
  setTenant: (t: TenantInfo) => void;
  addItem: (item: CartItem) => void;
  removeItem: (productId: number) => void;
  updateQty: (productId: number, qty: number) => void;
  clearCart: () => void;
  toggleWishlist: (item: WishlistItem) => void;
  total: () => number;
  count: () => number;
}

export const useCartStore = create<CartStore>()(
  persist(
    (set, get) => ({
      items: [],
      wishlist: [],
      tenantId: 0,
      tenant: null,

      setTenant: (tenant) =>
        set({ tenant, tenantId: tenant.tenant_id }),

      addItem: (item) =>
        set((state) => {
          const existing = state.items.find((i) => i.product_id === item.product_id);
          if (existing) {
            return {
              items: state.items.map((i) =>
                i.product_id === item.product_id
                  ? { ...i, quantity: i.quantity + item.quantity }
                  : i
              ),
            };
          }
          return { items: [...state.items, item] };
        }),

      removeItem: (productId) =>
        set((state) => ({
          items: state.items.filter((i) => i.product_id !== productId),
        })),

      updateQty: (productId, qty) =>
        set((state) => ({
          items:
            qty <= 0
              ? state.items.filter((i) => i.product_id !== productId)
              : state.items.map((i) =>
                  i.product_id === productId ? { ...i, quantity: qty } : i
                ),
        })),

      clearCart: () => set({ items: [] }),

      total: () =>
        get().items.reduce((sum, i) => sum + i.price * i.quantity, 0),

      count: () =>
        get().items.reduce((sum, i) => sum + i.quantity, 0),

      toggleWishlist: (item) =>
        set((state) => {
          const exists = state.wishlist.some((i) => i.id === item.id);
          return { wishlist: exists ? state.wishlist.filter((i) => i.id !== item.id) : [...state.wishlist, item] };
        }),
    }),
    { name: 'jdh-cart' }
  )
);
