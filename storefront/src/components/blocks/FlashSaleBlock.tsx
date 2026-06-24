import { storeApi } from '@/lib/api';
import ProductCard from '@/components/ProductCard';
import FlashSaleStrip from '@/components/FlashSaleStrip';
import type { FlashSaleProps } from '@/lib/block-types';

interface Props extends FlashSaleProps {
  tenantId: number;
}

export default async function FlashSaleBlock({ tenantId, title = 'Flash Sale', end_time, product_ids, limit = 6, show_timer = true }: Props) {
  let products: Awaited<ReturnType<typeof storeApi.getProducts>>['products'] = [];

  try {
    const res = await storeApi.getProducts(tenantId, { per_page: limit, sort: 'popular' });
    products = res.products.filter((p) => p.has_discount);
    if (product_ids && product_ids.length > 0) {
      products = products.filter((p) => product_ids.includes(p.id));
    }
  } catch {
    products = [];
  }

  if (products.length === 0) return null;

  // If there are enough products, use the full FlashSaleStrip component for the countdown timer
  if (show_timer && end_time) {
    return (
      <div className="max-w-7xl mx-auto px-4">
        <FlashSaleStrip products={products} tenantId={tenantId} />
      </div>
    );
  }

  // Simple grid without countdown
  return (
    <div className="max-w-7xl mx-auto px-4">
      <h2 className="text-lg font-extrabold text-gray-900 mb-4">{title}</h2>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        {products.map((p) => (
          <ProductCard key={p.id} product={p} tenantId={tenantId} showAddToCart />
        ))}
      </div>
    </div>
  );
}
