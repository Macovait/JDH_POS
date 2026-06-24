'use client';
import { useEffect, useState } from 'react';
import { X, Trash2, ShoppingBag } from 'lucide-react';
import { useCartStore } from '@/lib/store';
import CheckoutModal from './CheckoutModal';

interface Props { tenantId: number; currency: string }

export default function CartDrawer({ tenantId, currency }: Props) {
  const [open, setOpen] = useState(false);
  const [checkoutOpen, setCheckoutOpen] = useState(false);
  const { items, removeItem, updateQty, total } = useCartStore();

  useEffect(() => {
    const handler = () => setOpen((o) => !o);
    document.addEventListener('toggle-cart', handler);
    return () => document.removeEventListener('toggle-cart', handler);
  }, []);

  const fmt = (n: number) =>
    `${currency} ${n.toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;

  return (
    <>
      {/* Overlay */}
      {open && (
        <div className="fixed inset-0 bg-black/40 z-40" onClick={() => setOpen(false)} />
      )}

      {/* Drawer */}
      <div className={`fixed top-0 right-0 h-full w-full max-w-sm bg-white z-50 shadow-2xl flex flex-col transition-transform duration-300 ${open ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="flex items-center justify-between px-4 py-4 border-b">
          <h2 className="font-bold text-lg flex items-center gap-2">
            <ShoppingBag size={20} /> Your Cart
          </h2>
          <button onClick={() => setOpen(false)} className="text-gray-400 hover:text-gray-600">
            <X size={22} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3">
          {items.length === 0 ? (
            <div className="text-center py-16 text-gray-400">
              <ShoppingBag size={48} className="mx-auto mb-3 opacity-30" />
              <p>Your cart is empty</p>
              <button onClick={() => setOpen(false)}
                className="mt-4 text-sm font-medium text-brand hover:underline">
                Continue Shopping
              </button>
            </div>
          ) : (
            items.map((item) => (
              <div key={item.product_id} className="flex gap-3 items-center bg-gray-50 rounded-xl p-3">
                {item.image && (
                  <img src={item.image} alt={item.name}
                    className="w-14 h-14 object-cover rounded-lg shrink-0" />
                )}
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-sm text-gray-900 truncate">{item.name}</p>
                  <p className="text-brand font-bold text-sm">{fmt(item.price)}</p>
                  <div className="flex items-center gap-2 mt-1">
                    <button onClick={() => updateQty(item.product_id, item.quantity - 1)}
                      className="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-sm font-bold hover:bg-gray-300">−</button>
                    <span className="text-sm font-semibold w-6 text-center">{item.quantity}</span>
                    <button onClick={() => updateQty(item.product_id, item.quantity + 1)}
                      className="w-6 h-6 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-sm font-bold hover:bg-gray-300">+</button>
                  </div>
                </div>
                <button onClick={() => removeItem(item.product_id)}
                  className="text-gray-300 hover:text-red-400 shrink-0">
                  <Trash2 size={16} />
                </button>
              </div>
            ))
          )}
        </div>

        {items.length > 0 && (
          <div className="px-4 py-4 border-t space-y-3">
            <div className="flex justify-between font-bold text-gray-900">
              <span>Total</span>
              <span className="text-brand">{fmt(total())}</span>
            </div>
            <button
              onClick={() => { setOpen(false); setCheckoutOpen(true); }}
              className="w-full py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90"
              style={{ background: 'var(--brand-color, #f68b1e)' }}>
              Proceed to Checkout
            </button>
          </div>
        )}
      </div>

      <CheckoutModal
        open={checkoutOpen}
        onClose={() => setCheckoutOpen(false)}
        tenantId={tenantId}
        currency={currency}
      />
    </>
  );
}
