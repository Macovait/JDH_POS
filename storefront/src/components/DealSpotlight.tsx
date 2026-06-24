'use client';
import { useEffect, useState } from 'react';
import { Timer, ShoppingCart, TrendingDown } from 'lucide-react';
import { useCartStore } from '@/lib/store';
import type { Product } from '@/lib/api';
import toast from 'react-hot-toast';

interface Props {
  product: Product;
  tenantId: number;
  currency?: string;
  endHour?: number; // 24h format, default 23
}

function pad(n: number) { return n.toString().padStart(2, '0'); }

export default function DealSpotlight({ product, tenantId, currency = 'KES', endHour = 23 }: Props) {
  const addItem = useCartStore((s) => s.addItem);
  const [timeLeft, setTimeLeft] = useState({ h: 0, m: 0, s: 0 });

  useEffect(() => {
    const tick = () => {
      const now = new Date();
      const end = new Date(now);
      end.setHours(endHour, 59, 59, 999);
      if (end <= now) end.setDate(end.getDate() + 1);
      const diff = end.getTime() - now.getTime();
      setTimeLeft({
        h: Math.floor(diff / 3600000),
        m: Math.floor((diff % 3600000) / 60000),
        s: Math.floor((diff % 60000) / 1000),
      });
    };
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [endHour]);

  const handleAdd = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    addItem({ product_id: product.id, name: product.name, price: product.effective_price, quantity: 1, image: product.image });
    toast.success('Added to cart');
  };

  return (
    <section className="rounded-2xl overflow-hidden border border-gray-100 bg-white">
      <div className="flex flex-col md:flex-row">
        {/* Left: Image */}
        <a href={`/${tenantId}/products/${product.id}`}
          className="relative md:w-5/12 aspect-square md:aspect-auto bg-gray-50 flex items-center justify-center overflow-hidden group">
          {product.image ? (
            <img src={product.image} alt={product.name} loading="lazy"
              className="w-full h-full object-contain p-4 group-hover:scale-105 transition-transform duration-500" />
          ) : (
            <div className="text-6xl opacity-20">🛍️</div>
          )}
          <div className="absolute top-3 left-3 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded-full flex items-center gap-1">
            <TrendingDown size={10} /> DEAL OF THE DAY
          </div>
        </a>

        {/* Right: Info */}
        <div className="flex-1 p-5 md:p-6 flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2 text-red-500 text-xs font-bold uppercase tracking-wide mb-2">
              <Timer size={14} /> Ends in {pad(timeLeft.h)}:{pad(timeLeft.m)}:{pad(timeLeft.s)}
            </div>
            <h3 className="text-lg md:text-xl font-extrabold text-gray-900 leading-snug mb-2">
              {product.name}
            </h3>
            {product.description && (
              <p className="text-sm text-gray-500 line-clamp-2 mb-3">{product.description}</p>
            )}
            <div className="flex items-baseline gap-3 mb-3">
              <span className="text-2xl font-extrabold text-gray-900">
                {currency} {product.effective_price.toLocaleString()}
              </span>
              {product.has_discount && (
                <>
                  <span className="text-sm text-gray-400 line-through">
                    {currency} {product.price.toLocaleString()}
                  </span>
                  <span className="bg-red-50 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">
                    -{product.discount_pct}%
                  </span>
                </>
              )}
            </div>
            {product.avg_rating && product.avg_rating > 0 && (
              <div className="flex items-center gap-1 text-sm text-amber-500 font-semibold mb-4">
                {'★'.repeat(Math.round(product.avg_rating))}
                <span className="text-gray-400 font-normal text-xs">({product.review_count ?? 0} reviews)</span>
              </div>
            )}
          </div>

          <div className="flex items-center gap-3">
            <button onClick={handleAdd}
              className="flex-1 flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-white font-bold text-sm transition hover:opacity-90"
              style={{ background: 'var(--brand-color, #f68b1e)' }}>
              <ShoppingCart size={16} /> Add to Cart
            </button>
            <a href={`/${tenantId}/products/${product.id}`}
              className="px-5 py-3 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
              View Details
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}
