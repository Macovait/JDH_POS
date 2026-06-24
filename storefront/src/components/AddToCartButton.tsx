'use client';
import { useState } from 'react';
import { ShoppingCart, Plus, Minus } from 'lucide-react';
import toast from 'react-hot-toast';
import { useCartStore } from '@/lib/store';
import type { Product } from '@/lib/api';

interface Props { product: Product }

export default function AddToCartButton({ product }: Props) {
  const [qty, setQty] = useState(1);
  const { addItem } = useCartStore();

  const handleAdd = () => {
    addItem({ product_id: product.id, name: product.name, price: product.effective_price, quantity: qty, image: product.image });
    toast.success(`${product.name} added to cart`);
    document.dispatchEvent(new CustomEvent('toggle-cart'));
  };

  return (
    <div className="flex flex-col gap-3">
      {/* Qty selector */}
      <div className="flex items-center gap-3">
        <span className="text-sm font-medium text-gray-600">Qty:</span>
        <div className="flex items-center border border-gray-200 rounded-xl overflow-hidden">
          <button onClick={() => setQty((q) => Math.max(1, q - 1))}
            className="w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition">
            <Minus size={14} />
          </button>
          <span className="w-10 text-center text-sm font-bold">{qty}</span>
          <button onClick={() => setQty((q) => q + 1)}
            className="w-9 h-9 flex items-center justify-center text-gray-500 hover:bg-gray-50 transition">
            <Plus size={14} />
          </button>
        </div>
      </div>

      <button
        onClick={handleAdd}
        disabled={!product.in_stock}
        className="flex items-center justify-center gap-2 py-3.5 px-8 rounded-xl text-white font-bold text-sm transition hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
        style={{ background: 'var(--brand-color, #f68b1e)' }}>
        <ShoppingCart size={16} />
        {product.in_stock ? 'Add to Cart' : 'Out of Stock'}
      </button>
    </div>
  );
}
