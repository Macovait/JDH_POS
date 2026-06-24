'use client';
import { useEffect, useState } from 'react';
import { Zap } from 'lucide-react';
import type { Product } from '@/lib/api';
import ProductCard from '@/components/ProductCard';

interface Props { products: Product[]; tenantId: number }

function useCountdown(targetHour: number) {
  const getRemaining = () => {
    const now = new Date();
    const end = new Date(now);
    end.setHours(targetHour, 0, 0, 0);
    if (end <= now) end.setDate(end.getDate() + 1);
    const diff = end.getTime() - now.getTime();
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    return { h, m, s };
  };
  const [time, setTime] = useState(getRemaining);
  useEffect(() => {
    const id = setInterval(() => setTime(getRemaining()), 1000);
    return () => clearInterval(id);
  }, []);
  return time;
}

function Digit({ n }: { n: number }) {
  return (
    <span className="bg-gray-900 text-white text-sm font-bold px-2 py-0.5 rounded-md min-w-[2rem] text-center tabular-nums">
      {String(n).padStart(2, '0')}
    </span>
  );
}

export default function FlashSaleStrip({ products, tenantId }: Props) {
  const { h, m, s } = useCountdown(24);

  const discounted = products.filter((p) => p.has_discount);
  if (discounted.length === 0) return null;

  return (
    <section className="rounded-2xl overflow-hidden border border-red-100">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-red-500 to-orange-500 text-white">
        <div className="flex items-center gap-2 font-extrabold text-base">
          <Zap size={18} fill="currentColor" />
          Flash Sales
        </div>
        <div className="flex items-center gap-1.5 text-sm">
          <span className="opacity-80 text-xs">Ends in</span>
          <Digit n={h} />
          <span className="font-bold">:</span>
          <Digit n={m} />
          <span className="font-bold">:</span>
          <Digit n={s} />
        </div>
      </div>

      {/* Products */}
      <div className="bg-red-50 px-4 py-3">
        <div className="flex gap-3 overflow-x-auto pb-1 scrollbar-hide">
          {discounted.slice(0, 8).map((p) => (
            <div key={p.id} className="w-40 shrink-0">
              <ProductCard product={p} tenantId={tenantId} />
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
