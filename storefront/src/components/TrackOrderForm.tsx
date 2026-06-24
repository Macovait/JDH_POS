'use client';
import { useState, useEffect } from 'react';
import { Search, Package, Truck, CheckCircle, Clock, XCircle, Loader2 } from 'lucide-react';
import { storeApi, type TrackedOrder } from '@/lib/api';

interface Props { tenantId: number; initialOrder?: string }

const STATUS_STEPS = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

const STATUS_ICONS: Record<string, React.ReactNode> = {
  pending:    <Clock size={20} />,
  confirmed:  <CheckCircle size={20} />,
  processing: <Package size={20} />,
  shipped:    <Truck size={20} />,
  delivered:  <CheckCircle size={20} />,
  cancelled:  <XCircle size={20} />,
};

const STATUS_LABELS: Record<string, string> = {
  pending:    'Order Received',
  confirmed:  'Order Confirmed',
  processing: 'Being Prepared',
  shipped:    'Out for Delivery',
  delivered:  'Delivered',
  cancelled:  'Cancelled',
};

export default function TrackOrderForm({ tenantId, initialOrder }: Props) {
  const [input, setInput] = useState(initialOrder ?? '');
  const [loading, setLoading] = useState(false);
  const [order, setOrder] = useState<TrackedOrder | null>(null);
  const [error, setError] = useState('');

  const handleTrack = async (e?: React.FormEvent) => {
    e?.preventDefault();
    const q = input.trim();
    if (!q) return;
    setLoading(true);
    setError('');
    setOrder(null);
    try {
      const data = await storeApi.trackOrder(tenantId, q);
      setOrder(data);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Order not found. Check the number and try again.');
      // Clear stale query param so refresh doesn't re-trigger
      if (window.location.search.includes('order=')) {
        window.history.replaceState({}, '', window.location.pathname);
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (initialOrder) handleTrack();
  }, []);

  const currentStep = order ? STATUS_STEPS.indexOf(order.status) : -1;

  return (
    <div className="space-y-6">
      {/* Search form */}
      <form onSubmit={handleTrack} className="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        <label htmlFor="order-input" className="block text-sm font-semibold text-gray-700 mb-2">Order Number or UUID</label>
        <div className="flex gap-3">
          <input
            id="order-input"
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="e.g. ORD-20240101-001"
            className="flex-1 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40"
          />
          <button type="submit" disabled={loading}
            className="px-5 py-3 rounded-xl text-white font-bold text-sm flex items-center gap-2 transition hover:opacity-90 disabled:opacity-60"
            style={{ background: 'var(--brand-color, #f68b1e)' }}>
            {loading ? <Loader2 size={16} className="animate-spin" /> : <Search size={16} />}
            Track
          </button>
        </div>
      </form>

      {/* Error */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-2xl px-5 py-4 text-sm flex items-center gap-2">
          <XCircle size={16} /> {error}
        </div>
      )}

      {/* Result */}
      {order && (
        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          {/* Header */}
          <div className="px-6 py-5 border-b" style={{ background: 'var(--brand-color, #f68b1e)' }}>
            <div className="flex items-start justify-between text-white">
              <div>
                <p className="text-sm opacity-80">Order Number</p>
                <p className="text-xl font-extrabold">#{order.order_number}</p>
              </div>
              <div className="text-right">
                <p className="text-sm opacity-80">Total</p>
                <p className="text-xl font-extrabold">KES {Number(order.total).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</p>
              </div>
            </div>
          </div>

          <div className="p-6 space-y-8">
            {/* Status badge + progress */}
            <div className="flex items-center gap-3">
              <span className={`inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full ${
                order.status === 'cancelled'
                  ? 'bg-red-100 text-red-700'
                  : order.status === 'delivered'
                  ? 'bg-green-100 text-green-700'
                  : 'bg-blue-100 text-blue-700'
              }`}>
                {STATUS_ICONS[order.status]}
                {order.status_info?.label ?? order.status}
              </span>
              <span className="text-xs text-gray-400">
                Updated {order.updated_at ? new Date(order.updated_at).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }) : '—'}
              </span>
            </div>

            {/* Progress steps */}
            {order.status !== 'cancelled' ? (
              <div>
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Order Progress</p>
                <div className="flex items-start gap-0">
                  {STATUS_STEPS.map((step, i) => {
                    const done = i <= currentStep;
                    const active = i === currentStep;
                    return (
                      <div key={step} className="flex-1 flex flex-col items-center relative">
                        {/* Line */}
                        {i < STATUS_STEPS.length - 1 && (
                          <div className={`absolute top-4 left-1/2 w-full h-0.5 ${done && i < currentStep ? 'bg-brand' : 'bg-gray-200'}`}
                               style={done && i < currentStep ? { background: 'var(--brand-color)' } : {}} />
                        )}
                        {/* Circle */}
                        <div className={`w-8 h-8 rounded-full flex items-center justify-center z-10 transition-all ${
                          done ? 'text-white' : 'bg-gray-100 text-gray-400'
                        } ${active ? 'ring-4 ring-orange-100' : ''}`}
                             style={done ? { background: 'var(--brand-color)' } : {}}>
                          {STATUS_ICONS[step]}
                        </div>
                        <p className={`text-[10px] text-center mt-1.5 leading-tight font-medium ${done ? 'text-gray-700' : 'text-gray-400'}`}>
                          {STATUS_LABELS[step]}
                        </p>
                      </div>
                    );
                  })}
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-2 bg-red-50 text-red-700 rounded-xl p-4 text-sm font-medium">
                <XCircle size={18} /> This order was cancelled.
              </div>
            )}

            {/* Order details grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div className="bg-gray-50 rounded-xl p-4">
                <p className="text-gray-400 text-xs mb-1">Customer</p>
                <p className="font-semibold text-gray-800">{order.customer_name}</p>
                {order.customer_phone && <p className="text-gray-500 text-xs mt-0.5">{order.customer_phone}</p>}
                {order.customer_email && <p className="text-gray-500 text-xs mt-0.5">{order.customer_email}</p>}
              </div>
              <div className="bg-gray-50 rounded-xl p-4">
                <p className="text-gray-400 text-xs mb-1">Payment</p>
                <p className="font-semibold text-gray-800 capitalize">{order.payment_method}</p>
                <p className={`text-xs font-medium mt-0.5 inline-block px-2 py-0.5 rounded-full ${
                  order.payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                }`}>
                  {order.payment_status}
                </p>
              </div>
              <div className="bg-gray-50 rounded-xl p-4">
                <p className="text-gray-400 text-xs mb-1">Ordered On</p>
                <p className="font-semibold text-gray-800">
                  {order.placed_at ? new Date(order.placed_at).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' }) : '—'}
                </p>
              </div>
              <div className="bg-gray-50 rounded-xl p-4">
                <p className="text-gray-400 text-xs mb-1">Order ID</p>
                <p className="font-semibold text-gray-800">#{order.order_number}</p>
              </div>
            </div>

            {/* Delivery address */}
            {(order.delivery_address || order.shipping_address) && (
              <div className="bg-gray-50 rounded-xl p-4">
                <p className="text-gray-400 text-xs mb-1 flex items-center gap-1.5">
                  <Truck size={12} /> Delivery Address
                </p>
                <p className="text-sm text-gray-800 whitespace-pre-line">{order.delivery_address || order.shipping_address}</p>
              </div>
            )}

            {/* Cost breakdown */}
            <div className="border border-gray-100 rounded-xl p-4">
              <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Order Summary</p>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between text-gray-600">
                  <span>Subtotal</span>
                  <span>KES {Number(order.total - (order.tax_amount || 0) - (order.shipping_amount || 0) + (order.discount || 0)).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</span>
                </div>
                {(order.discount || 0) > 0 && (
                  <div className="flex justify-between text-green-600">
                    <span>Discount</span>
                    <span>- KES {Number(order.discount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</span>
                  </div>
                )}
                {(order.tax_amount || 0) > 0 && (
                  <div className="flex justify-between text-gray-600">
                    <span>Tax</span>
                    <span>KES {Number(order.tax_amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</span>
                  </div>
                )}
                {(order.shipping_amount || 0) > 0 && (
                  <div className="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span>KES {Number(order.shipping_amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</span>
                  </div>
                )}
                <div className="border-t pt-2 flex justify-between font-extrabold text-gray-900">
                  <span>Total</span>
                  <span>KES {Number(order.total).toLocaleString('en-KE', { minimumFractionDigits: 2 })}</span>
                </div>
              </div>
            </div>

            {/* Items */}
            {order.items && order.items.length > 0 && (
              <div>
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Items Ordered</p>
                <div className="space-y-2">
                  {order.items.map((item: { product_name: string; quantity: number; unit_price: number }, i: number) => (
                    <div key={i} className="flex justify-between items-center text-sm bg-gray-50 rounded-xl px-4 py-3">
                      <span className="text-gray-700">{item.product_name} <span className="text-gray-400">× {item.quantity}</span></span>
                      <span className="font-semibold text-gray-800">
                        KES {(item.unit_price * item.quantity).toLocaleString('en-KE', { minimumFractionDigits: 2 })}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Shipping timeline */}
            {order.timeline && order.timeline.length > 0 && (
              <div>
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Shipping Timeline</p>
                <div className="relative border-l-2 border-gray-200 ml-2 space-y-4">
                  {order.timeline.map((event, i) => {
                    const isDone = STATUS_STEPS.indexOf(event.status) <= currentStep || event.status === order.status;
                    return (
                      <div key={i} className="relative pl-5">
                        <span
                          className={`absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full border-2 border-white ${
                            isDone ? 'bg-brand' : 'bg-gray-300'
                          }`}
                          style={isDone ? { background: 'var(--brand-color)' } : {}}
                        />
                        <p className="text-sm font-semibold text-gray-800">{event.label}</p>
                        <p className="text-xs text-gray-500">
                          {new Date(event.timestamp).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' })}
                        </p>
                        {event.notes && <p className="text-xs text-gray-500 mt-0.5">{event.notes}</p>}
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Notes */}
            {order.notes && (
              <div className="bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm text-amber-800">
                <p className="font-semibold text-xs mb-1">Order Notes</p>
                <p className="text-xs">{order.notes}</p>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
