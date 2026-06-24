'use client';
import { Truck, RefreshCw, ShieldCheck, Headphones } from 'lucide-react';

export default function TrustBar() {
  const items = [
    { icon: Truck, label: 'Fast Delivery', sub: 'Same-day in select areas' },
    { icon: RefreshCw, label: 'Easy Returns', sub: '7-day return policy' },
    { icon: ShieldCheck, label: 'M-Pesa Accepted', sub: 'Secure payments' },
    { icon: Headphones, label: '24/7 Support', sub: 'WhatsApp & call' },
  ];

  return (
    <div className="bg-white border-b border-gray-100">
      <div className="max-w-7xl mx-auto px-4 py-2.5">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4">
          {items.map((item) => (
            <div key={item.label} className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center shrink-0"
                   style={{ color: 'var(--brand-color, #f68b1e)' }}>
                <item.icon size={16} />
              </div>
              <div className="min-w-0">
                <p className="text-xs font-bold text-gray-800 truncate">{item.label}</p>
                <p className="text-[10px] text-gray-400 truncate hidden sm:block">{item.sub}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
