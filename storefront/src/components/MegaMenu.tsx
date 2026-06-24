'use client';
import { useState } from 'react';
import Link from 'next/link';
import { ChevronRight } from 'lucide-react';
import type { Category } from '@/lib/api';

interface Props { categories: Category[]; tenantId: number }

const ICON_MAP: Record<string, string> = {
  phone: '📱', tablet: '📱', laptop: '💻', computer: '💻',
  tv: '📺', audio: '🔊', electronic: '📺', appliance: '🔌',
  home: '🏠', furniture: '🪑', kitchen: '🍳', office: '📎',
  health: '💊', beauty: '💄', cosmetic: '💄', perfume: '🌸',
  fashion: '👕', cloth: '👕', shoe: '👟', bag: '👜', watch: '⌚',
  jewelry: '💍', sport: '⚽', toy: '🧸', baby: '👶', kid: '🧸',
  book: '📚', game: '🎮', food: '🍔', drink: '🥤', grocery: '🛒',
  car: '🚗', tool: '🔧', pet: '🐕', garden: '🌱', default: '🛍️',
};

function pickIcon(name: string): string {
  const low = name.toLowerCase();
  for (const [key, icon] of Object.entries(ICON_MAP)) {
    if (low.includes(key)) return icon;
  }
  return ICON_MAP.default;
}

export default function MegaMenu({ categories, tenantId }: Props) {
  const [hovered, setHovered] = useState<number | null>(null);
  const active = categories.find((c) => c.id === hovered);

  return (
    <div className="relative flex h-full">
      {/* Sidebar */}
      <nav className="w-56 bg-white rounded-l-xl border border-gray-200 overflow-hidden flex flex-col shadow-sm">
        <div className="px-4 py-2.5 border-b bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
          All Categories
        </div>
        <ul className="flex-1 overflow-y-auto py-1">
          {categories.slice(0, 16).map((cat) => (
            <li key={cat.id}>
              <Link
                href={`/${tenantId}/products?category=${cat.id}`}
                className={`flex items-center justify-between px-3 py-2 text-[13px] transition group ${
                  hovered === cat.id
                    ? 'bg-orange-50 text-orange-600 font-semibold'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
                onMouseEnter={() => setHovered(cat.id)}
                onMouseLeave={() => setHovered(null)}
              >
                <span className="flex items-center gap-2.5">
                  <span className="text-base w-5 text-center">{pickIcon(cat.name)}</span>
                  <span className="truncate">{cat.name}</span>
                </span>
                <ChevronRight size={13} className={`shrink-0 transition ${hovered === cat.id ? 'text-orange-400' : 'text-gray-300'}`} />
              </Link>
            </li>
          ))}
          {categories.length > 16 && (
            <li>
              <Link href={`/${tenantId}/products`}
                className="flex items-center gap-2 px-3 py-2 text-[13px] font-semibold text-orange-500 hover:bg-orange-50 transition">
                See all categories <ChevronRight size={13} />
              </Link>
            </li>
          )}
        </ul>
      </nav>

      {/* Flyout panel */}
      {active && (
        <div
          className="hidden lg:block absolute left-56 top-0 ml-0 w-[600px] bg-white rounded-r-xl border border-l-0 border-gray-200 shadow-lg z-20 h-full overflow-hidden"
          onMouseEnter={() => setHovered(active.id)}
          onMouseLeave={() => setHovered(null)}
        >
          <div className="p-5 h-full">
            <div className="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100">
              {active.image ? (
                <img src={active.image} alt="" className="w-10 h-10 object-contain rounded-lg bg-gray-50 p-1" />
              ) : (
                <div className="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-lg">{pickIcon(active.name)}</div>
              )}
              <div>
                <h3 className="font-bold text-gray-800 text-sm">{active.name}</h3>
                <p className="text-[11px] text-gray-400">{active.product_count ?? 0} products</p>
              </div>
              <Link href={`/${tenantId}/products?category=${active.id}`}
                className="ml-auto text-[11px] font-bold text-orange-500 hover:underline px-3 py-1 bg-orange-50 rounded-lg transition">
                Shop Now →
              </Link>
            </div>
            <p className="text-xs text-gray-500 leading-relaxed">
              {active.description || `Browse our collection of ${active.name} products.`}
            </p>
            <div className="mt-4 grid grid-cols-3 gap-3">
              {categories.filter((c) => c.id !== active.id).slice(0, 6).map((c) => (
                <Link key={c.id} href={`/${tenantId}/products?category=${c.id}`}
                  className="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 border border-transparent hover:border-gray-100 transition">
                  <span className="text-sm">{pickIcon(c.name)}</span>
                  <span className="text-[11px] font-medium text-gray-600 truncate">{c.name}</span>
                </Link>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
