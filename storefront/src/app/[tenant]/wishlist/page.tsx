'use client';

import { useCartStore } from '@/lib/store';
import { Heart, ShoppingCart, Trash2, ArrowLeft } from 'lucide-react';
import Image from 'next/image';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Props {
  params: { tenant: string };
}

export default function WishlistPage({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  const { wishlist, toggleWishlist, addItem, tenant } = useCartStore();
  const currency = tenant?.currency ?? 'KES';

  const fmt = (n: number) =>
    `${currency} ${Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;

  const handleAddToCart = (item: { id: number; name: string; price: number; image?: string }) => {
    addItem({
      product_id: item.id,
      name: item.name,
      price: item.price,
      quantity: 1,
      image: item.image,
    });
    toast.success(`${item.name} added to cart`, {
      icon: '🛒',
      style: {
        background: '#1e293b',
        color: '#f1f5f9',
        borderRadius: '0.75rem',
        border: '1px solid #334155',
      },
    });
  };

  const handleRemove = (item: { id: number; name: string }) => {
    toggleWishlist(item as any);
    toast.success('Removed from wishlist', {
      icon: '💔',
      style: {
        background: '#1e293b',
        color: '#f1f5f9',
        borderRadius: '0.75rem',
        border: '1px solid #334155',
      },
    });
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 min-h-screen">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Link
          href={`/${tenantId}`}
          className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 transition"
        >
          <ArrowLeft size={18} />
        </Link>
        <div>
          <h1 className="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2">
            <Heart size={24} className="text-red-500 fill-red-500" />
            My Wishlist
          </h1>
          <p className="text-sm text-gray-500 dark:text-slate-400">
            {wishlist.length} {wishlist.length === 1 ? 'item' : 'items'} saved
          </p>
        </div>
      </div>

      {wishlist.length === 0 ? (
        <div className="text-center py-16 bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700">
          <div className="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center mx-auto mb-4">
            <Heart size={32} className="text-red-300 dark:text-red-700" />
          </div>
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
            Your wishlist is empty
          </h3>
          <p className="text-sm text-gray-500 dark:text-slate-400 mb-6 max-w-sm mx-auto">
            Save your favorite products here to buy them later.
          </p>
          <Link
            href={`/${tenantId}/products`}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand text-white font-bold text-sm hover:opacity-90 transition"
          >
            <ShoppingCart size={16} />
            Browse Products
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {wishlist.map((item) => (
            <div
              key={item.id}
              className="flex items-center gap-4 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 hover:shadow-md transition"
            >
              {/* Image */}
              <Link href={`/${tenantId}/products/${item.id}`} className="shrink-0">
                <div className="w-20 h-20 rounded-xl bg-gray-50 dark:bg-slate-900 overflow-hidden flex items-center justify-center">
                  {item.image ? (
                    <Image
                      src={item.image}
                      alt={item.name}
                      width={80}
                      height={80}
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <span className="text-2xl">🛍️</span>
                  )}
                </div>
              </Link>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <Link
                  href={`/${tenantId}/products/${item.id}`}
                  className="text-sm font-semibold text-gray-900 dark:text-white hover:text-brand transition truncate block"
                >
                  {item.name}
                </Link>
                <p className="text-base font-bold text-brand mt-1">
                  {fmt(item.price)}
                </p>
              </div>

              {/* Actions */}
              <div className="flex items-center gap-2">
                <button
                  onClick={() => handleAddToCart(item)}
                  className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-brand text-white text-xs font-bold hover:opacity-90 transition"
                >
                  <ShoppingCart size={14} />
                  Add to Cart
                </button>
                <button
                  onClick={() => handleRemove(item)}
                  className="w-9 h-9 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-red-500 transition"
                  aria-label="Remove from wishlist"
                >
                  <Trash2 size={16} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
