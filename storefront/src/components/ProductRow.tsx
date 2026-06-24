'use client';
import { useRef } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Product } from '@/lib/api';
import ProductCard from '@/components/ProductCard';

interface Props {
  title: string;
  emoji?: string;
  products: Product[];
  tenantId: number;
  seeAllHref: string;
  accentBar?: boolean;
}

export default function ProductRow({ title, emoji, products, tenantId, seeAllHref, accentBar = false }: Props) {
  const scrollRef = useRef<HTMLDivElement>(null);

  const scroll = (dir: 'left' | 'right') => {
    if (!scrollRef.current) return;
    scrollRef.current.scrollBy({ left: dir === 'right' ? 300 : -300, behavior: 'smooth' });
  };

  return (
    <section className={accentBar ? 'rounded-2xl overflow-hidden border border-gray-100' : ''}>
      {accentBar && (
        <div className="flex items-center justify-between px-4 py-3"
          style={{ background: 'linear-gradient(135deg, var(--brand-color,#f68b1e) 0%, var(--brand-dark,#d97706) 100%)' }}>
          <h2 className="text-base font-extrabold text-white flex items-center gap-2">
            {emoji && <span>{emoji}</span>}
            {title}
          </h2>
          <Link href={seeAllHref}
            className="text-sm font-semibold text-white/90 hover:text-white flex items-center gap-0.5 transition">
            See All <ChevronRight size={14} />
          </Link>
        </div>
      )}
      {!accentBar && (
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-extrabold text-gray-900 flex items-center gap-2">
            {emoji && <span>{emoji}</span>}
            {title}
          </h2>
          <Link href={seeAllHref}
            className="text-sm font-semibold hover:underline flex items-center gap-1"
            style={{ color: 'var(--brand-color)' }}>
            See All <ChevronRight size={14} />
          </Link>
        </div>
      )}

      <div className="relative group">
        {/* Scroll left */}
        <button onClick={() => scroll('left')}
          className="absolute left-0 top-1/2 -translate-y-1/2 z-10 -translate-x-3 w-8 h-8 rounded-full bg-white shadow-md border border-gray-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-gray-50">
          <ChevronLeft size={16} />
        </button>

        {/* Scrollable row */}
        <div ref={scrollRef}
          className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide scroll-smooth">
          {products.map((p) => (
            <div key={p.id} className="w-44 shrink-0">
              <ProductCard product={p} tenantId={tenantId} />
            </div>
          ))}
        </div>

        {/* Scroll right */}
        <button onClick={() => scroll('right')}
          className="absolute right-0 top-1/2 -translate-y-1/2 z-10 translate-x-3 w-8 h-8 rounded-full bg-white shadow-md border border-gray-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-gray-50">
          <ChevronRight size={16} />
        </button>
      </div>
    </section>
  );
}
