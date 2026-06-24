import type { Category } from '@/lib/api';

interface Props { categories: Category[]; tenantId: number }

export default function CategoryGrid({ categories, tenantId }: Props) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
      {categories.map((cat) => (
        <a key={cat.id} href={`/${tenantId}/products?category=${cat.id}`}
          className="group flex flex-col items-center gap-2 p-3 bg-white rounded-2xl border border-gray-100 hover:border-brand hover:shadow-md transition text-center">
          {cat.image ? (
            <img src={cat.image} alt={cat.name}
              className="w-14 h-14 object-cover rounded-xl group-hover:scale-105 transition" />
          ) : (
            <div className="w-14 h-14 rounded-xl flex items-center justify-center text-2xl bg-orange-50 group-hover:bg-orange-100 transition">
              🛍️
            </div>
          )}
          <span className="text-xs font-semibold text-gray-700 group-hover:text-brand transition leading-tight">
            {cat.name}
          </span>
          <span className="text-[10px] text-gray-400">{cat.product_count} items</span>
        </a>
      ))}
    </div>
  );
}
