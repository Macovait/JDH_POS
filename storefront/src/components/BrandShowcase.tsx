'use client';
import { Store } from 'lucide-react';
import type { Category } from '@/lib/api';

interface Props {
  categories: Category[];
  tenantId: number;
}

export default function BrandShowcase({ categories, tenantId }: Props) {
  // Use categories as a proxy for brands/official stores
  // Show top 6 categories with product counts
  const featured = categories
    .filter((c) => c.product_count > 0)
    .slice(0, 6);

  if (featured.length === 0) return null;

  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-extrabold text-gray-900 flex items-center gap-2">
          <Store size={18} className="text-brand" />
          Popular Collections
        </h2>
        <a
          href={`/${tenantId}/products`}
          className="text-sm font-semibold hover:underline"
          style={{ color: 'var(--brand-color)' }}
        >
          See All
        </a>
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
        {featured.map((cat) => (
          <a
            key={cat.id}
            href={`/${tenantId}/products?category=${cat.id}`}
            className="group relative rounded-2xl overflow-hidden aspect-[4/3] bg-gray-100 hover:shadow-lg transition"
          >
            {cat.image ? (
              <img
                src={cat.image}
                alt={cat.name}
                loading="lazy"
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-4xl opacity-20">
                🛍️
              </div>
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
            <div className="absolute bottom-0 left-0 right-0 p-3">
              <p className="text-white text-sm font-bold truncate">{cat.name}</p>
              <p className="text-white/70 text-[10px]">{cat.product_count} products</p>
            </div>
          </a>
        ))}
      </div>
    </section>
  );
}
