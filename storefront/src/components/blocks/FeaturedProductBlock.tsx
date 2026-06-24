'use client';

import type { FeaturedProductProps } from '@/lib/block-types';
import { Star, ShoppingCart } from 'lucide-react';

interface Props extends FeaturedProductProps {}

// Placeholder product data until API integration
const placeholderProduct = {
  name: 'Premium Wireless Headphones',
  price: 129.99,
  original_price: 199.99,
  image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop',
  description:
    'Experience crystal-clear audio with our premium wireless headphones. Featuring active noise cancellation, 30-hour battery life, and plush memory foam ear cushions for all-day comfort.',
  rating: 4.8,
  reviews_count: 124,
};

export default function FeaturedProductBlock({
  badge = 'Featured',
  show_description = true,
  show_reviews = true,
  image_position = 'left',
}: Props) {
  const product = placeholderProduct;
  const isLeft = image_position === 'left';

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        <div
          className={`flex flex-col ${
            isLeft ? 'md:flex-row' : 'md:flex-row-reverse'
          }`}
        >
          <div className="md:w-1/2 relative">
            <img
              src={product.image}
              alt={product.name}
              className="w-full h-64 md:h-full object-cover"
            />
            {badge && (
              <span
                className="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-bold text-white"
                style={{ background: 'var(--brand-color, #f97316)' }}
              >
                {badge}
              </span>
            )}
          </div>

          <div className="md:w-1/2 p-8 md:p-12 flex flex-col justify-center">
            <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900 mb-3">
              {product.name}
            </h2>

            {show_reviews && (
              <div className="flex items-center gap-2 mb-4">
                <div className="flex items-center">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star
                      key={i}
                      className={`w-4 h-4 ${
                        i < Math.floor(product.rating)
                          ? 'text-amber-400 fill-amber-400'
                          : 'text-gray-300'
                      }`}
                    />
                  ))}
                </div>
                <span className="text-sm text-gray-500">
                  {product.rating} ({product.reviews_count} reviews)
                </span>
              </div>
            )}

            {show_description && (
              <p className="text-gray-600 text-sm leading-relaxed mb-6">
                {product.description}
              </p>
            )}

            <div className="flex items-center gap-3 mb-6">
              <span className="text-3xl font-extrabold text-gray-900">
                ${product.price.toFixed(2)}
              </span>
              {product.original_price && (
                <span className="text-lg text-gray-400 line-through">
                  ${product.original_price.toFixed(2)}
                </span>
              )}
            </div>

            <div className="flex flex-wrap gap-3">
              <button
                className="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white transition hover:opacity-90"
                style={{ background: 'var(--brand-color, #f97316)' }}
              >
                <ShoppingCart className="w-4 h-4" />
                Add to Cart
              </button>
              <a
                href={`/product/${product.name.toLowerCase().replace(/\s+/g, '-')}`}
                className="inline-flex items-center px-6 py-3 rounded-xl text-sm font-bold border border-gray-200 text-gray-700 hover:bg-gray-50 transition"
              >
                View Details
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
