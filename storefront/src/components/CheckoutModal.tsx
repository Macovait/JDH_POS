'use client';
import { useState } from 'react';
import { X, CheckCircle, Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { useCartStore } from '@/lib/store';
import { storeApi } from '@/lib/api';

interface Props {
  open: boolean;
  onClose: () => void;
  tenantId: number;
  currency: string;
}

export default function CheckoutModal({ open, onClose, tenantId, currency }: Props) {
  const { items, total, clearCart } = useCartStore();
  const [form, setForm] = useState({
    customer_name: '', customer_phone: '', customer_email: '',
    delivery_address: '', notes: '', payment_method: 'mpesa', coupon_code: '',
  });
  const [loading, setLoading] = useState(false);
  const [done, setDone] = useState<{ order_number: string; uuid: string } | null>(null);

  const fmt = (n: number) => `${currency} ${n.toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.customer_name || !form.customer_phone) {
      toast.error('Name and phone are required');
      return;
    }
    setLoading(true);
    try {
      const result = await storeApi.placeOrder(tenantId, {
        ...form,
        payment_method: form.payment_method as 'mpesa' | 'cash' | 'card',
        items: items.map((i) => ({ product_id: i.product_id, quantity: i.quantity })),
      });
      clearCart();
      setDone({ order_number: result.order_number, uuid: result.uuid });
      try {
        const key = `jdh_orders_${tenantId}`;
        const existing = JSON.parse(localStorage.getItem(key) ?? '[]') as { order_number: string; uuid: string; placed_at: string }[];
        const next = [
          { order_number: result.order_number, uuid: result.uuid, placed_at: new Date().toISOString() },
          ...existing.filter((o) => o.uuid !== result.uuid),
        ].slice(0, 50);
        localStorage.setItem(key, JSON.stringify(next));
      } catch {
        // ignore storage errors
      }
      toast.success('Order placed!');
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Failed to place order');
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-white w-full max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between px-5 py-4 border-b">
          <h2 className="font-bold text-lg">Checkout</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600"><X size={20} /></button>
        </div>

        {done ? (
          <div className="p-8 text-center">
            <CheckCircle size={56} className="mx-auto text-green-500 mb-4" />
            <h3 className="text-xl font-bold mb-1">Order Placed!</h3>
            <p className="text-gray-500 text-sm mb-2">Order #{done.order_number}</p>
            <p className="text-gray-500 text-sm mb-6">We&apos;ll confirm via SMS/WhatsApp shortly.</p>
            <a href={`/${tenantId}/track?order=${done.uuid}`}
              className="block w-full py-3 rounded-xl text-white font-bold text-sm text-center"
              style={{ background: 'var(--brand-color, #f68b1e)' }}>
              Track My Order
            </a>
            <button onClick={onClose} className="mt-3 text-sm text-gray-500 hover:underline w-full">
              Continue Shopping
            </button>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="p-5 space-y-4">
            {/* Order summary */}
            <div className="bg-gray-50 rounded-xl p-3 space-y-1.5 text-sm">
              {items.map((i) => (
                <div key={i.product_id} className="flex justify-between text-gray-700">
                  <span>{i.name} × {i.quantity}</span>
                  <span className="font-medium">{fmt(i.price * i.quantity)}</span>
                </div>
              ))}
              <div className="border-t pt-1.5 flex justify-between font-bold text-gray-900">
                <span>Total</span>
                <span style={{ color: 'var(--brand-color)' }}>{fmt(total())}</span>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-3">
              <label htmlFor="customer_name" className="sr-only">Full Name</label>
              <input id="customer_name" name="customer_name" required placeholder="Full Name *" value={form.customer_name}
                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40" />
              <label htmlFor="customer_phone" className="sr-only">Phone Number</label>
              <input id="customer_phone" name="customer_phone" required placeholder="Phone Number (e.g. 0712345678) *" value={form.customer_phone}
                onChange={(e) => setForm({ ...form, customer_phone: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40" />
              <label htmlFor="customer_email" className="sr-only">Email</label>
              <input id="customer_email" name="customer_email" placeholder="Email (optional)" type="email" value={form.customer_email}
                onChange={(e) => setForm({ ...form, customer_email: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40" />
              <label htmlFor="delivery_address" className="sr-only">Delivery Address</label>
              <textarea id="delivery_address" name="delivery_address" placeholder="Delivery Address" value={form.delivery_address} rows={2}
                onChange={(e) => setForm({ ...form, delivery_address: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40 resize-none" />
              <label htmlFor="notes" className="sr-only">Notes</label>
              <textarea id="notes" name="notes" placeholder="Notes (optional)" value={form.notes} rows={2}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40 resize-none" />
              <label htmlFor="coupon_code" className="sr-only">Coupon code</label>
              <input id="coupon_code" name="coupon_code" placeholder="Coupon code (optional)" value={form.coupon_code}
                onChange={(e) => setForm({ ...form, coupon_code: e.target.value })}
                className="border rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40" />
            </div>

            {/* Payment method */}
            <div>
              <p className="text-sm font-semibold text-gray-700 mb-2">Payment Method</p>
              <div className="grid grid-cols-3 gap-2">
                {[
                  { value: 'mpesa', label: 'M-Pesa' },
                  { value: 'cash', label: 'Cash on Delivery' },
                  { value: 'card', label: 'Card' },
                ].map((pm) => (
                  <button key={pm.value} type="button"
                    onClick={() => setForm({ ...form, payment_method: pm.value })}
                    className={`py-2 text-xs font-medium rounded-lg border transition ${form.payment_method === pm.value
                      ? 'border-brand text-brand bg-orange-50'
                      : 'border-gray-200 text-gray-600 hover:border-gray-300'}`}>
                    {pm.label}
                  </button>
                ))}
              </div>
            </div>

            <button type="submit" disabled={loading}
              className="w-full py-3 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 transition hover:opacity-90 disabled:opacity-60"
              style={{ background: 'var(--brand-color, #f68b1e)' }}>
              {loading ? <Loader2 size={16} className="animate-spin" /> : null}
              {loading ? 'Placing Order...' : 'Place Order'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
