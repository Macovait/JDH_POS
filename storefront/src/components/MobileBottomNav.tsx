'use client';
import { usePathname } from 'next/navigation';
import { useState, useEffect } from 'react';
import { Home, LayoutGrid, ShoppingCart, PackageSearch, User } from 'lucide-react';
import Link from 'next/link';
import { useCartStore } from '@/lib/store';
import type { TenantInfo } from '@/lib/api';

interface Props { tenant: TenantInfo }

export default function MobileBottomNav({ tenant }: Props) {
  const [mounted, setMounted] = useState(false);
  const count = useCartStore((s) => s.count());
  const tid = tenant.tenant_id;
  const pathname = usePathname();

  useEffect(() => { setMounted(true); }, []);

  const links = [
    { icon: Home, label: 'Home', href: `/${tid}` },
    { icon: LayoutGrid, label: 'Categories', href: `/${tid}/products` },
    { icon: ShoppingCart, label: 'Cart', href: '#', action: 'toggle-cart' },
    { icon: PackageSearch, label: 'Track', href: `/${tid}/track` },
    { icon: User, label: 'Account', href: `/${tid}/account` },
  ];

  const isActive = (href: string) => {
    if (href === '#') return false;
    return pathname === href || pathname.startsWith(href + '/');
  };

  return (
    <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-[0_-2px_8px_rgba(0,0,0,0.04)] safe-area-pb">
      <div className="flex items-center justify-around py-1.5">
        {links.map((link) => {
          const active = isActive(link.href);
          const content = (
            <>
              <div className="relative">
                <link.icon size={20} strokeWidth={active ? 2.5 : 2} />
                {mounted && link.label === 'Cart' && count > 0 && (
                  <span className="absolute -top-1.5 -right-2.5 min-w-[16px] h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1">
                    {count > 9 ? '9+' : count}
                  </span>
                )}
              </div>
              <span className="text-[10px] font-medium">{link.label}</span>
            </>
          );
          const className = `flex flex-col items-center gap-0.5 px-3 py-1 rounded-lg transition relative ${
            active ? 'text-brand' : 'text-gray-400'
          }`;
          if (link.action === 'toggle-cart') {
            return (
              <button
                key={link.label}
                onClick={() => document.dispatchEvent(new CustomEvent('toggle-cart'))}
                className={className}
                style={active ? { color: 'var(--brand-color, #f68b1e)' } : {}}
              >
                {content}
              </button>
            );
          }
          return (
            <Link
              key={link.label}
              href={link.href}
              className={className}
              style={active ? { color: 'var(--brand-color, #f68b1e)' } : {}}
            >
              {content}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
