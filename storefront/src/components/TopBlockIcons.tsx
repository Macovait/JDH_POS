'use client';
import { Phone, Package, Store } from 'lucide-react';

interface Props { whatsapp?: string | null }

export default function TopBlockIcons({ whatsapp }: Props) {
  const items = [
    {
      icon: <Phone size={22} className="text-green-500" />,
      title: 'WhatsApp',
      subtitle: 'Text To Order',
      href: whatsapp ? `https://wa.me/${whatsapp.replace(/\D/g, '')}` : '#',
      bg: 'bg-green-50',
      external: true,
    },
    {
      icon: <Package size={22} className="text-orange-500" />,
      title: 'Best Deals',
      subtitle: 'Daily Discounts',
      href: '#deals',
      bg: 'bg-orange-50',
      external: false,
    },
    {
      icon: <Store size={22} className="text-blue-500" />,
      title: 'Visit Store',
      subtitle: 'Browse All Products',
      href: '#categories',
      bg: 'bg-blue-50',
      external: false,
    },
  ];

  return (
    <div className="grid grid-cols-3 gap-3 mt-3">
      {items.map((item, i) => (
        <a
          key={i}
          href={item.href}
          target={item.external ? '_blank' : undefined}
          rel={item.external ? 'noopener noreferrer' : undefined}
          className="flex items-center gap-3 bg-white border border-gray-100 rounded-xl px-4 py-3 hover:shadow-md hover:border-orange-200 transition group"
        >
          <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${item.bg} group-hover:scale-110 transition`}>
            {item.icon}
          </div>
          <div className="min-w-0">
            <div className="text-sm font-bold text-gray-800 truncate">{item.title}</div>
            <div className="text-[11px] text-gray-400 truncate">{item.subtitle}</div>
          </div>
        </a>
      ))}
    </div>
  );
}
