import { notFound } from 'next/navigation';
import Link from 'next/link';
import { storeApi } from '@/lib/api';
import AddToCartButton from '@/components/AddToCartButton';
import ProductGrid from '@/components/ProductGrid';
import StarRating from '@/components/StarRating';
import ProductViewTracker from '@/components/ProductViewTracker';
import RecentlyViewed from '@/components/RecentlyViewed';

interface Props {
  params: { tenant: string; id: string };
}

export async function generateMetadata({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  try {
    const product = await storeApi.getProduct(tenantId, parseInt(params.id));
    return { title: product.name, description: product.description?.slice(0, 160) };
  } catch {
    return { title: 'Product' };
  }
}

export default async function ProductPage({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  const productId = parseInt(params.id, 10);

  let product;
  try {
    product = await storeApi.getProduct(tenantId, productId);
  } catch {
    notFound();
  }

  const currency = product.currency ?? 'KES';
  const fmt = (n: number) => `${currency} ${Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;

  return (
    <>
      <ProductViewTracker product={product} />
      <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Breadcrumb */}
      <nav className="text-xs text-gray-400 mb-6 flex items-center gap-1.5">
        <Link href={`/${tenantId}`} className="hover:text-brand">Home</Link>
        <span>/</span>
        <Link href={`/${tenantId}/products`} className="hover:text-brand">Products</Link>
        <span>/</span>
        <span className="text-gray-600 font-medium">{product.name}</span>
      </nav>

      {/* Main */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-10 mb-12">
        {/* Image */}
        <div className="rounded-2xl overflow-hidden bg-gray-50 flex items-center justify-center aspect-square">
          {product.image ? (
            <img src={product.image} alt={product.name} className="w-full h-full object-contain p-4" />
          ) : (
            <span className="text-8xl opacity-20">🛍️</span>
          )}
        </div>

        {/* Info */}
        <div className="flex flex-col">
          {product.category_name && (
            <Link href={`/${tenantId}/products?category=${product.category_id}`}
              className="text-xs font-semibold uppercase tracking-wider mb-2"
              style={{ color: 'var(--brand-color)' }}>
              {product.category_name}
            </Link>
          )}
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 mb-3">{product.name}</h1>

          {product.avg_rating ? (
            <div className="flex items-center gap-2 mb-4">
              <StarRating rating={product.avg_rating} />
              <span className="text-sm text-gray-500">({product.review_count} reviews)</span>
            </div>
          ) : null}

          <div className="flex items-baseline gap-3 mb-4">
            <span className="text-3xl font-extrabold" style={{ color: 'var(--brand-color)' }}>
              {fmt(product.effective_price)}
            </span>
            {product.has_discount && (
              <span className="text-lg text-gray-400 line-through">{fmt(product.price)}</span>
            )}
            {product.has_discount && (
              <span className="text-sm font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full">
                -{product.discount_pct}% OFF
              </span>
            )}
          </div>

          {product.sku && (
            <p className="text-xs text-gray-400 mb-3">SKU: {product.sku}</p>
          )}

          <div className={`inline-flex items-center gap-1.5 text-sm font-semibold mb-6 ${product.in_stock ? 'text-green-600' : 'text-red-500'}`}>
            <span className={`w-2 h-2 rounded-full ${product.in_stock ? 'bg-green-500' : 'bg-red-400'}`} />
            {product.in_stock ? 'In Stock' : 'Out of Stock'}
          </div>

          {product.description && (
            <p className="text-sm text-gray-600 leading-relaxed mb-6">{product.description}</p>
          )}

          <AddToCartButton product={product} />
        </div>
      </div>

      {/* Reviews */}
      {product.reviews && product.reviews.length > 0 && (
        <section className="mb-12">
          <h2 className="text-xl font-bold text-gray-900 mb-5">Customer Reviews</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {product.reviews.map((review: { id: number; customer_name: string; rating: number; comment: string; created_at: string }) => (
              <div key={review.id} className="bg-white border border-gray-100 rounded-2xl p-4">
                <div className="flex items-center justify-between mb-2">
                  <span className="font-semibold text-sm text-gray-800">{review.customer_name}</span>
                  <StarRating rating={review.rating} size="sm" />
                </div>
                <p className="text-sm text-gray-600 leading-relaxed">{review.comment}</p>
                <p className="text-[11px] text-gray-400 mt-2">
                  {new Date(review.created_at).toLocaleDateString('en-KE', { year: 'numeric', month: 'short', day: 'numeric' })}
                </p>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Related products */}
      {product.related_products && product.related_products.length > 0 && (
        <section>
          <h2 className="text-xl font-bold text-gray-900 mb-5">You May Also Like</h2>
          <ProductGrid products={product.related_products} tenantId={tenantId} />
        </section>
      )}

      {/* Recently Viewed */}
      <RecentlyViewed tenantId={tenantId} excludeId={productId} />
    </div>
    </>
  );
}
