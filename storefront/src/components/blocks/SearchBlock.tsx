'use client';

import { useState } from 'react';
import type { SearchProps } from '@/lib/block-types';
import { Search } from 'lucide-react';

interface Props extends SearchProps {
  tenantId: number;
}

export default function SearchBlock({
  placeholder = 'Search products...',
  button_text = 'Search',
  scope = 'products',
  layout = 'inline',
  show_categories = false,
  class_name,
  tenantId,
}: Props) {
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (query.trim()) params.set('q', query.trim());
    if (show_categories && category) params.set('category', category);
    const base = scope === 'products' ? `/${tenantId}/products` : `/${tenantId}/products`;
    window.location.href = `${base}?${params.toString()}`;
  };

  return (
    <div className={`max-w-3xl mx-auto px-4 ${class_name || ''}`}>
      <form
        onSubmit={handleSubmit}
        className={`flex items-center gap-2 ${
          layout === 'compact' ? 'max-w-md mx-auto' : ''
        }`}
      >
        {show_categories && (
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="hidden sm:block px-3 py-2.5 text-sm border border-gray-200 rounded-xl bg-white text-gray-700 focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          >
            <option value="">All</option>
            <option value="">Categories</option>
          </select>
        )}
        <div className="relative flex-1">
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={placeholder}
            className="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl text-gray-800 placeholder-gray-400 focus:ring-2 focus:ring-orange-500 focus:border-transparent"
          />
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        </div>
        <button
          type="submit"
          className="px-5 py-2.5 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition shrink-0"
        >
          {button_text}
        </button>
      </form>
    </div>
  );
}
