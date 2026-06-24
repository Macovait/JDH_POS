import type { PriceTableProps } from '@/lib/block-types';
import { Check } from 'lucide-react';

export default function PriceTableBlock({ tables, columns = 3, align = 'top' }: PriceTableProps) {
  const colClass = {
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  };

  const items = align === 'bottom' ? [...tables].sort((a, b) => (a.highlighted ? -1 : b.highlighted ? 1 : 0)) : tables;

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`grid gap-4 ${colClass[columns]} ${align === 'bottom' ? 'items-end' : 'items-start'}`}>
        {items.map((table, index) => (
          <div
            key={index}
            className={`relative rounded-2xl border p-6 transition hover:shadow-md ${
              table.highlighted
                ? 'border-orange-500 bg-orange-50/30 shadow-sm'
                : 'border-gray-100 bg-white'
            }`}
          >
            {table.badge && (
              <span className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white bg-orange-500 rounded-full">
                {table.badge}
              </span>
            )}
            <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wide">{table.title}</h3>
            <div className="mt-2 flex items-baseline gap-1">
              <span className="text-3xl font-extrabold text-gray-900">{table.price}</span>
              {table.period && <span className="text-sm text-gray-500">/{table.period}</span>}
            </div>
            <ul className="mt-5 space-y-2">
              {table.features.map((feature, i) => (
                <li key={i} className="flex items-start gap-2 text-sm text-gray-600">
                  <Check className="w-4 h-4 text-orange-500 shrink-0 mt-0.5" />
                  {feature}
                </li>
              ))}
            </ul>
            {table.button_text && (
              <a
                href={table.button_link || '#'}
                className={`mt-6 block w-full text-center text-sm font-semibold py-2.5 rounded-xl transition ${
                  table.highlighted
                    ? 'bg-orange-500 text-white hover:bg-orange-600'
                    : 'bg-gray-900 text-white hover:bg-gray-800'
                }`}
              >
                {table.button_text}
              </a>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
