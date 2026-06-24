'use client';
import { useEffect, useState } from 'react';
import { Clock } from 'lucide-react';
import type { Product } from '@/lib/api';

interface Props {
  tenantId: number;
  excludeId?: number;
}

const STORAGE_KEY = 'recently_viewed';

export function recordView(product: Product) {
  if (typeof window === 'undefined') return;
  const raw = localStorage.getItem(STORAGE_KEY);
  const items: Product[] = raw ? JSON.parse(raw) : [];
  const filtered = items.filter((p) => p.id !== product.id);
  const updated = [product, ...filtered].slice(0, 12);
  localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
}

export default function RecentlyViewed({ tenantId, excludeId }: Props) {
  const [items, setItems] = useState<Product[]>([]);

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed: Product[] = JSON.parse(raw);
      setItems(excludeId ? parsed.filter((p) => p.id !== excludeId) : parsed);
    }
  }, [excludeId]);

  if (items.length === 0) return null;

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-extrabold text-gray-900 flex items-center gap-2">
          <Clock size={18} className="text-brand" />
          Recently Viewed
        </h2>
        <a
          href={`/${tenantId}/products`}
          className="text-sm font-semibold hover:underline"
          style={{ color: 'var(--brand-color)' }}
        >
          See All
        </a>
      </div>
      <div className="flex gap-3 overflow-x-auto scrollbar-hide pb-2">
        {items.slice(0, 8).map((product) => (
          <a
            key={product.id}
            href={`/${tenantId}/products/${product.id}`}
            className="block w-36 shrink-0 bg-white rounded-xl border border-gray-100 hover:shadow-md transition overflow-hidden group"
          >
            <div className="aspect-square bg-gray-50 overflow-hidden">
              {product.image ? (
                <img
                  src={product.image}
                  alt={product.name}
                  loading="lazy"
                  className="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform"
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center text-3xl opacity-20">🛍️</div>
              )}
            </div>
            <div className="p-2.5">
              <p className="text-xs font-semibold text-gray-800 line-clamp-2 leading-snug">{product.name}</p>
              <p className="text-sm font-extrabold mt-1" style={{ color: 'var(--brand-color)' }}>
                {product.currency ?? 'KES'} {product.effective_price.toLocaleString()}
              </p>
            </div>
          </a>
        ))}
      </div>
    </section>
  );
}
