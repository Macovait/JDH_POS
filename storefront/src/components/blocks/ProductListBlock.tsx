import { storeApi } from '@/lib/api';
import ProductCard from '@/components/ProductCard';
import type { ProductListProps } from '@/lib/block-types';

interface Props extends ProductListProps {
  tenantId: number;
}

export default async function ProductListBlock({
  tenantId,
  title,
  product_ids,
  category_ids,
  sort = 'popular',
  limit = 5,
  show_add_to_cart = true,
  show_rating = true,
  style = 'compact',
}: Props) {
  let products: Awaited<ReturnType<typeof storeApi.getProducts>>['products'] = [];

  try {
    const params: Record<string, string | number> = { per_page: limit, sort };
    if (product_ids && product_ids.length > 0) {
      params.ids = product_ids.join(',');
    }
    if (category_ids && category_ids.length > 0) {
      params.category_ids = category_ids.join(',');
    }
    const res = await storeApi.getProducts(tenantId, params);
    products = res.products;
  } catch {
    products = [];
  }

  if (products.length === 0) return null;

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && <h2 className="text-lg font-extrabold text-gray-900 mb-4">{title}</h2>}
      <div className={`flex flex-col gap-3 ${style === 'mini' ? 'max-w-md' : ''}`}>
        {products.map((product) => (
          <div
            key={product.id}
            className={`flex items-center gap-4 bg-white border border-gray-100 rounded-xl p-3 hover:shadow-sm transition ${
              style === 'detailed' ? 'flex-col sm:flex-row' : ''
            }`}
          >
            <a href={`/${tenantId}/products/${product.id}`} className="shrink-0">
              <img
                src={product.image || '/placeholder.png'}
                alt={product.name}
                className={`object-cover rounded-lg bg-gray-50 ${
                  style === 'mini' ? 'w-14 h-14' : style === 'detailed' ? 'w-24 h-24 sm:w-32 sm:h-32' : 'w-16 h-16'
                }`}
              />
            </a>
            <div className="flex-1 min-w-0">
              <a href={`/${tenantId}/products/${product.id}`} className="block text-sm font-semibold text-gray-900 hover:text-orange-500 transition truncate">
                {product.name}
              </a>
              {show_rating && product.avg_rating !== undefined && (
                <div className="text-xs text-amber-500 mt-0.5">
                  {'★'.repeat(Math.round(product.avg_rating))}{'☆'.repeat(5 - Math.round(product.avg_rating))}
                  <span className="text-gray-400 ml-1">({product.review_count || 0})</span>
                </div>
              )}
              {style === 'detailed' && product.description && (
                <p className="text-xs text-gray-500 mt-1 line-clamp-2">{product.description}</p>
              )}
              <div className="mt-1 flex items-center gap-2">
                <span className="text-sm font-bold text-gray-900">${Number(product.effective_price).toFixed(2)}</span>
                {product.has_discount && product.selling_price !== undefined && (
                  <span className="text-xs text-gray-400 line-through">${Number(product.price).toFixed(2)}</span>
                )}
              </div>
            </div>
            {show_add_to_cart && (
              <button
                type="button"
                className="px-4 py-2 text-xs font-semibold text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition shrink-0"
                onClick={() => {
                  window.location.href = `/${tenantId}/products/${product.id}`;
                }}
              >
                Add
              </button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
