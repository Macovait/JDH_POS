import { storeApi } from '@/lib/api';
import type { CategoriesGridProps } from '@/lib/block-types';

interface Props extends CategoriesGridProps {
  tenantId: number;
}

export default async function CategoriesGridBlock({ tenantId, title, category_ids, limit = 8, layout = 'grid', show_product_count = true }: Props) {
  let categories: Awaited<ReturnType<typeof storeApi.getCategories>> = [];

  try {
    categories = await storeApi.getCategories(tenantId);
    if (category_ids && category_ids.length > 0) {
      categories = categories.filter((c) => category_ids.includes(c.id));
    }
    categories = categories.slice(0, limit);
  } catch {
    categories = [];
  }

  if (categories.length === 0) return null;

  if (layout === 'carousel') {
    return (
      <div className="max-w-7xl mx-auto px-4">
        {title && <h2 className="text-lg font-extrabold text-gray-900 mb-4">{title}</h2>}
        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
          {categories.map((cat) => (
            <a key={cat.id} href={`/${tenantId}/products?category=${cat.id}`} className="shrink-0 group">
              <div className="w-32 bg-white border border-gray-100 rounded-xl p-3 flex flex-col items-center hover:shadow-sm transition">
                {cat.image ? (
                  <img src={cat.image} alt={cat.name} className="w-12 h-12 object-contain rounded-xl mb-2" />
                ) : (
                  <div className="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-2xl mb-2">🛍️</div>
                )}
                <span className="text-xs font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-500 transition">{cat.name}</span>
                {show_product_count && <span className="text-[10px] text-gray-400 mt-0.5">{cat.product_count} items</span>}
              </div>
            </a>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && <h2 className="text-lg font-extrabold text-gray-900 mb-4">{title}</h2>}
      <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
        {categories.map((cat) => (
          <a key={cat.id} href={`/${tenantId}/products?category=${cat.id}`} className="group">
            <div className="flex flex-col items-center gap-2 bg-white border border-gray-100 rounded-2xl p-3 hover:shadow-md hover:border-orange-200 transition">
              {cat.image ? (
                <img src={cat.image} alt={cat.name} className="w-12 h-12 object-contain rounded-xl" />
              ) : (
                <div className="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-2xl">🛍️</div>
              )}
              <span className="text-xs font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-500 transition">{cat.name}</span>
              {show_product_count && <span className="text-[10px] text-gray-400">{cat.product_count}</span>}
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}
