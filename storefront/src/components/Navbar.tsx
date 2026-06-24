'use client';
import { useState, useEffect, type FormEvent } from 'react';
import { ShoppingCart, Search, Menu, X, Store, User, Heart } from 'lucide-react';
import Link from 'next/link';
import { useCartStore } from '@/lib/store';
import type { TenantInfo, StoreSettings } from '@/lib/api';

interface Props { tenant: TenantInfo; settings: StoreSettings | null }

export default function Navbar({ tenant, settings }: Props) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [mounted, setMounted] = useState(false);
  const count = useCartStore((s) => s.count());
  const tid = tenant.tenant_id;

  useEffect(() => { setMounted(true); }, []);

  const handleSearch = (e: FormEvent) => {
    e.preventDefault();
    if (search.trim()) window.location.href = `/${tid}/products?q=${encodeURIComponent(search)}`;
  };

  return (
    <header className="border-b z-50 shadow-sm" style={{ background: settings?.header_bg_color || '#ffffff', position: settings?.header_sticky === '1' ? 'sticky' : 'relative', top: 0 }}>
      <div className="max-w-7xl mx-auto px-4 py-3">
        <div className="flex items-center gap-4">
          {/* Logo */}
          <Link href={`/${tid}`} className="flex items-center gap-2 shrink-0">
            {tenant.logo ? (
              <img src={tenant.logo} alt={tenant.name} className="h-9 w-auto" />
            ) : (
              <div className="w-9 h-9 rounded-lg flex items-center justify-center text-white font-bold text-lg"
                   style={{ background: tenant.primary_color }}>
                <Store size={18} />
              </div>
            )}
            <span className="text-lg font-extrabold text-gray-900 hidden sm:block">{tenant.name}</span>
          </Link>

          {/* Search */}
          {settings?.header_show_search !== '0' && (
          <form data-header-search onSubmit={handleSearch} className="flex-1 max-w-xl hidden md:flex">
            <div className="relative w-full">
              <input
                id="search"
                name="search"
                type="text"
                aria-label="Search products"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search products..."
                className="w-full border border-gray-200 rounded-lg px-4 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40"
              />
              <button type="submit" className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand">
                <Search size={16} />
              </button>
            </div>
          </form>
          )}

          {/* Actions */}
          <div className="flex items-center gap-3 ml-auto">
            <Link href={`/${tid}/track`} className="text-sm text-gray-600 hover:text-brand hidden sm:block">Track Order</Link>
            {settings?.header_show_wishlist === '1' && (
              <Link data-header-wishlist href={`/${tid}/wishlist`} className="p-2 rounded-lg text-gray-600 hover:text-brand hover:bg-gray-100 transition" aria-label="Wishlist">
                <Heart size={18} />
              </Link>
            )}
            {settings?.header_show_account === '1' && (
              <Link data-header-account href={`/${tid}/account`} className="p-2 rounded-lg text-gray-600 hover:text-brand hover:bg-gray-100 transition" aria-label="Account">
                <User size={18} />
              </Link>
            )}
            {settings?.header_show_cart !== '0' && (
            <button data-header-cart
              onClick={() => document.dispatchEvent(new CustomEvent('toggle-cart'))}
              className="relative flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-white transition"
              style={{ background: tenant.primary_color }}
            >
              <ShoppingCart size={16} />
              <span className="hidden sm:inline">Cart</span>
              {mounted && count > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-bold">
                  {count > 9 ? '9+' : count}
                </span>
              )}
            </button>
            )}
            <button className="md:hidden" onClick={() => setMenuOpen(!menuOpen)}>
              {menuOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>

        {/* Mobile search */}
        {settings?.header_show_search !== '0' && (
        <form data-header-search-mobile onSubmit={handleSearch} className="mt-2 md:hidden flex">
          <input
            id="mobile-search"
            name="mobile-search"
            type="text"
            aria-label="Search products"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search products..."
            className="flex-1 border border-gray-200 rounded-l-lg px-3 py-2 text-sm focus:outline-none"
          />
          <button type="submit" className="px-4 py-2 rounded-r-lg text-white text-sm"
                  style={{ background: tenant.primary_color }}>
            <Search size={14} />
          </button>
        </form>
        )}

        {/* Mobile menu */}
        {menuOpen && (
          <nav className="mt-3 pb-2 md:hidden border-t pt-3 flex flex-col gap-2 text-sm">
            <Link href={`/${tid}`} className="text-gray-700 hover:text-brand">Home</Link>
            <Link href={`/${tid}/products`} className="text-gray-700 hover:text-brand">All Products</Link>
            <Link href={`/${tid}/track`} className="text-gray-700 hover:text-brand">Track Order</Link>
            <Link href={`/${tid}/account`} className="text-gray-700 hover:text-brand">My Account</Link>
          </nav>
        )}
      </div>
    </header>
  );
}
