import { storeApi } from '@/lib/api';
import type { ProductCategoryProps } from '@/lib/block-types';

interface Props extends ProductCategoryProps {
  tenantId: number;
}

export default async function ProductCategoryBlock({
  tenantId,
  category_ids,
  layout = 'grid',
  columns = 4,
  show_count = true,
  show_image = true,
  limit = 8,
}: Props) {
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

  const colClass = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  };

  const card = (category: typeof categories[0]) => (
    <a
      key={category.id}
      href={`/${tenantId}/products?category=${category.id}`}
      className="group flex items-center gap-3 bg-white border border-gray-100 rounded-xl p-3 hover:shadow-sm transition"
    >
      {show_image && (
        <div className="w-12 h-12 shrink-0 rounded-lg bg-orange-50 flex items-center justify-center overflow-hidden">
          {category.image ? (
            <img src={category.image} alt={category.name} className="w-full h-full object-cover" />
          ) : (
            <span className="text-xl">🛍️</span>
          )}
        </div>
      )}
      <div className="min-w-0">
        <h3 className="text-sm font-semibold text-gray-900 truncate group-hover:text-orange-500 transition">{category.name}</h3>
        {show_count && (
          <p className="text-xs text-gray-500">{category.product_count} products</p>
        )}
      </div>
    </a>
  );

  return (
    <div className="max-w-7xl mx-auto px-4">
      {layout === 'grid' && (
        <div className={`grid ${colClass[columns]} gap-3`}>
          {categories.map(card)}
        </div>
      )}
      {layout === 'carousel' && (
        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
          {categories.map((cat) => (
            <div key={cat.id} className="shrink-0 w-56">
              {card(cat)}
            </div>
          ))}
        </div>
      )}
      {layout === 'list' && (
        <div className="flex flex-col gap-2">
          {categories.map(card)}
        </div>
      )}
    </div>
  );
}
