'use client';
import { ShoppingCart, Star, Heart, Eye } from 'lucide-react';
import toast from 'react-hot-toast';
import { useCartStore } from '@/lib/store';
import { useState } from 'react';
import type { Product } from '@/lib/api';
import Image from 'next/image';
import Link from 'next/link';

interface Props {
  products: Product[];
  tenantId: number;
  className?: string;
  cols?: 2 | 3 | 4 | 5;
  showQuickView?: boolean;
  showWishlist?: boolean;
}

export default function ProductGrid({
  products,
  tenantId,
  className,
  cols = 4,
  showQuickView = false,
  showWishlist = false,
}: Props) {
  const { addItem, tenant, wishlist, toggleWishlist } = useCartStore();
  const [hoveredProduct, setHoveredProduct] = useState<number | null>(null);
  const currency = tenant?.currency ?? 'KES';

  const colClasses = {
    2: 'grid-cols-2',
    3: 'grid-cols-2 sm:grid-cols-3',
    4: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
  };

  const fmt = (n: number) =>
    `${currency} ${Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;

  const handleAdd = (p: Product) => {
    addItem({
      product_id: p.id,
      name: p.name,
      price: p.effective_price,
      quantity: 1,
      image: p.image,
    });
    toast.success(`${p.name} added to cart`, {
      icon: '🛒',
      style: {
        background: '#1e293b',
        color: '#f1f5f9',
        borderRadius: '0.75rem',
        border: '1px solid #334155',
      },
    });
    document.dispatchEvent(new CustomEvent('toggle-cart'));
  };

  const handleWishlist = (p: Product) => {
    toggleWishlist(p);
    toast.success(
      wishlist.some((item) => item.id === p.id)
        ? `${p.name} removed from wishlist`
        : `${p.name} added to wishlist`,
      {
        icon: wishlist.some((item) => item.id === p.id) ? '💔' : '❤️',
        style: {
          background: '#1e293b',
          color: '#f1f5f9',
          borderRadius: '0.75rem',
          border: '1px solid #334155',
        },
      }
    );
  };

  if (products.length === 0) {
    return (
      <div className="text-center py-16">
        <div className="text-6xl mb-4">🛍️</div>
        <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">No products found</h3>
        <p className="text-gray-500 dark:text-slate-400">Try adjusting your filters or search terms.</p>
      </div>
    );
  }

  return (
    <div className={`grid ${colClasses[cols]} gap-3 md:gap-4 ${className || ''}`}>
      {products.map((p) => {
        const isInWishlist = wishlist.some((item) => item.id === p.id);
        const isHovered = hoveredProduct === p.id;
        const reviewCount = p.review_count ?? 0; // Fix: provide default value
        const hasReviews = reviewCount > 0;

        return (
          <div
            key={p.id}
            className="group relative bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden hover:shadow-xl hover:border-brand/30 transition-all duration-300 flex flex-col"
            onMouseEnter={() => setHoveredProduct(p.id)}
            onMouseLeave={() => setHoveredProduct(null)}
          >
            {/* Wishlist Button */}
            {showWishlist && (
              <button
                onClick={() => handleWishlist(p)}
                className="absolute top-2 right-2 z-10 w-8 h-8 rounded-full bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm flex items-center justify-center shadow-md hover:scale-110 transition-transform border border-gray-200 dark:border-slate-600"
                aria-label={isInWishlist ? 'Remove from wishlist' : 'Add to wishlist'}
              >
                <Heart
                  size={16}
                  className={`transition-colors ${
                    isInWishlist
                      ? 'fill-red-500 text-red-500'
                      : 'text-gray-400 group-hover:text-red-400'
                  }`}
                />
              </button>
            )}

            {/* Image */}
            <Link
              href={`/${tenantId}/products/${p.id}`}
              className="relative block bg-gray-50 dark:bg-slate-900 overflow-hidden"
              style={{ paddingTop: '75%' }}
            >
              {p.image ? (
                <Image
                  src={p.image}
                  alt={p.name}
                  fill
                  className="object-cover group-hover:scale-105 transition-transform duration-500"
                  sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
                />
              ) : (
                <div className="absolute inset-0 flex items-center justify-center text-4xl text-gray-300 dark:text-slate-600">
                  🛍️
                </div>
              )}

              {/* Badges */}
              <div className="absolute top-2 left-2 flex flex-col gap-1">
                {p.has_discount && (
                  <span className="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg">
                    -{p.discount_pct}%
                  </span>
                )}
                {p.is_new && (
                  <span className="bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg">
                    New
                  </span>
                )}
                {p.featured && (
                  <span className="bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg">
                    Featured
                  </span>
                )}
              </div>

              {/* Out of Stock Overlay */}
              {!p.in_stock && (
                <div className="absolute inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
                  <span className="text-xs font-bold text-white bg-red-500 px-3 py-1.5 rounded-full shadow-lg">
                    Out of Stock
                  </span>
                </div>
              )}

              {/* Quick View */}
              {showQuickView && isHovered && p.in_stock && (
                <div className="absolute inset-x-0 bottom-0 p-3 bg-gradient-to-t from-black/60 to-transparent">
                  <button
                    onClick={() => handleAdd(p)}
                    className="w-full py-2 bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-xs font-bold rounded-lg hover:bg-brand hover:text-white transition-colors shadow-lg"
                  >
                    <ShoppingCart size={14} className="inline mr-1.5" />
                    Quick Add
                  </button>
                </div>
              )}
            </Link>

            {/* Info */}
            <div className="p-3 flex flex-col flex-1">
              <Link
                href={`/${tenantId}/products/${p.id}`}
                className="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2 hover:text-brand dark:hover:text-brand transition-colors leading-snug mb-1"
              >
                {p.name}
              </Link>

              {/* Rating */}
              {(p.avg_rating ?? 0) > 0 ? (
                <div className="flex items-center gap-1 mb-1.5">
                  <div className="flex items-center gap-0.5 text-yellow-400">
                    <Star size={12} fill="currentColor" className="text-yellow-400" />
                    <span className="text-xs font-medium text-gray-700 dark:text-gray-300 ml-0.5">
                      {p.avg_rating?.toFixed(1)}
                    </span>
                  </div>
                  {hasReviews && (
                    <span className="text-[10px] text-gray-400 dark:text-slate-500">
                      ({reviewCount})
                    </span>
                  )}
                </div>
              ) : (
                <div className="h-5" /> // Spacer to maintain height
              )}

              {/* Price & Add to Cart */}
              <div className="mt-auto pt-2">
                <div className="flex items-baseline gap-1.5 mb-2">
                  <span className="text-base font-extrabold text-brand">
                    {fmt(p.effective_price)}
                  </span>
                  {p.has_discount && (
                    <span className="text-xs text-gray-400 dark:text-slate-500 line-through">
                      {fmt(p.price)}
                    </span>
                  )}
                </div>

                <button
                  disabled={!p.in_stock}
                  onClick={() => handleAdd(p)}
                  className={`w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-bold text-white transition-all ${
                    p.in_stock
                      ? 'bg-brand hover:bg-brand-dark hover:shadow-lg hover:shadow-brand/20 active:scale-95'
                      : 'bg-gray-400 dark:bg-slate-600 cursor-not-allowed'
                  }`}
                >
                  <ShoppingCart size={14} />
                  {p.in_stock ? 'Add to Cart' : 'Out of Stock'}
                </button>
              </div>
            </div>
          </div>
        );
      })}
    </div>
  );
}