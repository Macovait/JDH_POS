import { ChevronRight } from 'lucide-react';
import type { Category } from '@/lib/api';

interface Props { categories: Category[]; tenantId: number }

export default function HomeSidebar({ categories, tenantId }: Props) {
  return (
    <nav className="bg-white rounded-2xl border border-gray-100 overflow-hidden h-full">
      <div className="px-4 py-3 border-b font-bold text-sm text-gray-700 uppercase tracking-wide"
           style={{ background: 'var(--brand-color)', color: '#fff' }}>
        All Categories
      </div>
      <ul className="py-1">
        {categories.slice(0, 14).map((cat) => (
          <li key={cat.id}>
            <a href={`/${tenantId}/products?category=${cat.id}`}
              className="flex items-center justify-between px-4 py-2.5 text-sm text-gray-700 hover:bg-orange-50 hover:text-brand transition group">
              <span className="flex items-center gap-2">
                {cat.image ? (
                  <img src={cat.image} alt="" className="w-5 h-5 object-contain rounded" />
                ) : (
                  <span className="w-5 h-5 rounded bg-gray-100 flex items-center justify-center text-[10px]">🛍️</span>
                )}
                {cat.name}
              </span>
              <ChevronRight size={13} className="text-gray-300 group-hover:text-brand transition" />
            </a>
          </li>
        ))}
        {categories.length > 14 && (
          <li>
            <a href={`/${tenantId}/products`}
              className="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold hover:bg-orange-50 transition"
              style={{ color: 'var(--brand-color)' }}>
              See all categories <ChevronRight size={13} />
            </a>
          </li>
        )}
      </ul>
    </nav>
  );
}
