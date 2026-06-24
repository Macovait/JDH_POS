import Link from 'next/link';
import type { Category } from '@/lib/api';

interface Props {
  categories: Category[];
  tenantId: number;
  activeCategory?: number;
  className?: string;
  mobile?: boolean;
}

export default function CategorySidebar({ categories, tenantId, activeCategory, className }: Props) {
  return (
    <div className={`bg-white rounded-2xl border border-gray-100 p-4 ${className || ''}`}>
      <h3 className="font-bold text-sm text-gray-700 mb-3 uppercase tracking-wide">Categories</h3>
      <ul className="space-y-1">
        <li>
          <Link href={`/${tenantId}/products`}
            className={`flex items-center justify-between px-3 py-2 rounded-lg text-sm transition ${
              !activeCategory
                ? 'font-bold text-white'
                : 'text-gray-600 hover:bg-gray-50 hover:text-brand'
            }`}
            style={!activeCategory ? { background: 'var(--brand-color)' } : {}}>
            <span>All Products</span>
          </Link>
        </li>
        {categories.map((cat) => (
          <li key={cat.id}>
            <Link href={`/${tenantId}/products?category=${cat.id}`}
              className={`flex items-center justify-between px-3 py-2 rounded-lg text-sm transition ${
                activeCategory === cat.id
                  ? 'font-bold text-white'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-brand'
              }`}
              style={activeCategory === cat.id ? { background: 'var(--brand-color)' } : {}}>
              <span>{cat.name}</span>
              <span className={`text-[10px] px-1.5 py-0.5 rounded-full ${
                activeCategory === cat.id ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-400'
              }`}>
                {cat.product_count}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
