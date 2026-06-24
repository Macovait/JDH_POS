import { storeApi } from '@/lib/api';
import ProductCard from '@/components/ProductCard';
import type { ProductGridProps } from '@/lib/block-types';

interface Props extends ProductGridProps {
  tenantId: number;
}

export default async function ProductGridBlock({
  tenantId,
  title,
  subtitle,
  product_ids,
  category_id,
  sort = 'popular',
  limit = 8,
  columns = 4,
  show_add_to_cart = true,
  see_all_link,
}: Props) {
  let products: Awaited<ReturnType<typeof storeApi.getProducts>>['products'] = [];

  try {
    const params: Record<string, string | number> = { per_page: limit, sort };
    if (category_id) params.category = category_id;
    if (product_ids && product_ids.length > 0) {
      params.ids = product_ids.join(',');
    }
    const res = await storeApi.getProducts(tenantId, params);
    products = res.products;
  } catch {
    products = [];
  }

  if (products.length === 0) return null;

  const colClass = {
    2: 'grid-cols-2',
    3: 'grid-cols-2 md:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5',
  };

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="flex items-end justify-between mb-4">
        <div>
          <h2 className="text-lg font-extrabold text-gray-900">{title}</h2>
          {subtitle && <p className="text-xs text-gray-500 mt-0.5">{subtitle}</p>}
        </div>
        {see_all_link && (
          <a href={see_all_link} className="text-sm font-semibold hover:underline" style={{ color: 'var(--brand-color)' }}>
            See All
          </a>
        )}
      </div>
      <div className={`grid gap-3 ${colClass[columns]}`}>
        {products.map((p) => (
          <ProductCard key={p.id} product={p} tenantId={tenantId} showAddToCart={show_add_to_cart} />
        ))}
      </div>
    </div>
  );
}
