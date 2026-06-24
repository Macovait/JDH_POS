'use client';
import { ShoppingCart, Star } from 'lucide-react';
import Link from 'next/link';
import toast from 'react-hot-toast';
import { useCartStore } from '@/lib/store';
import type { Product } from '@/lib/api';

interface Props { product: Product; tenantId: number; showAddToCart?: boolean }

export default function ProductCard({ product, tenantId, showAddToCart = true }: Props) {
  const { addItem } = useCartStore();

  const handleAdd = (e: React.MouseEvent) => {
    e.preventDefault();
    addItem({ product_id: product.id, name: product.name, price: product.effective_price, quantity: 1, image: product.image });
    toast.success(`Added to cart`);
  };

  return (
    <Link href={`/${tenantId}/products/${product.id}`}
      className="block bg-white rounded-2xl border border-gray-100 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 overflow-hidden group">
      {/* Image */}
      <div className="relative aspect-square bg-gray-50 overflow-hidden">
        {product.image ? (
          <img src={product.image} alt={product.name} loading="lazy"
            className="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300" />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-4xl opacity-20">🛍️</div>
        )}
        {product.has_discount && (
          <span className="absolute top-2 left-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
            -{product.discount_pct}%
          </span>
        )}
        {!product.in_stock && (
          <div className="absolute inset-0 bg-white/70 flex items-center justify-center">
            <span className="text-xs font-bold text-gray-500 bg-white px-2 py-1 rounded-full border">Out of Stock</span>
          </div>
        )}
        {/* Add to cart hover button */}
        {showAddToCart && product.in_stock && (
          <button onClick={handleAdd}
            className="absolute bottom-2 right-2 w-8 h-8 rounded-full text-white shadow flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
            style={{ background: 'var(--brand-color)' }}>
            <ShoppingCart size={14} />
          </button>
        )}
      </div>

      {/* Info */}
      <div className="p-2.5">
        <p className="text-xs text-gray-700 font-medium leading-snug line-clamp-2 mb-1.5">{product.name}</p>
        <div className="flex items-baseline gap-1.5 flex-wrap">
          <span className="text-sm font-extrabold" style={{ color: 'var(--brand-color)' }}>
            KES {Number(product.effective_price).toLocaleString('en-KE')}
          </span>
          {product.has_discount && (
            <span className="text-[11px] text-gray-400 line-through">
              KES {Number(product.price).toLocaleString('en-KE')}
            </span>
          )}
        </div>
        {product.avg_rating ? (
          <div className="flex items-center gap-1 mt-1">
            <Star size={10} fill="currentColor" className="text-yellow-400" />
            <span className="text-[10px] text-gray-500">{product.avg_rating}</span>
          </div>
        ) : null}
      </div>
    </Link>
  );
}
