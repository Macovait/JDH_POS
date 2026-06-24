import type { FeaturedItemsProps } from '@/lib/block-types';

export default function FeaturedItemsBlock({
  title,
  items,
  layout = 'grid',
  columns = 4,
  gap = 'md',
  show_arrows = true,
  show_dots = true,
}: FeaturedItemsProps) {
  const colClass = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
  };

  const gapClass = {
    sm: 'gap-3',
    md: 'gap-4',
    lg: 'gap-6',
  };

  const itemCard = (item: typeof items[0], index: number) => (
    <a
      key={index}
      href={item.link || '#'}
      className="group block bg-white border border-gray-100 rounded-2xl overflow-hidden hover:shadow-md transition"
    >
      {item.image && (
        <div className="aspect-[4/3] overflow-hidden bg-gray-50">
          <img
            src={item.image}
            alt={item.title}
            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          />
        </div>
      )}
      <div className="p-4">
        {item.badge && (
          <span className="inline-block mb-2 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white bg-orange-500 rounded-full">
            {item.badge}
          </span>
        )}
        <h3 className="text-sm font-bold text-gray-900 group-hover:text-orange-500 transition">{item.title}</h3>
        {item.subtitle && <p className="text-xs text-gray-500 mt-0.5">{item.subtitle}</p>}
      </div>
    </a>
  );

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && <h2 className="text-lg font-extrabold text-gray-900 mb-4">{title}</h2>}
      {layout === 'grid' && (
        <div className={`grid ${colClass[columns]} ${gapClass[gap]}`}>
          {items.map(itemCard)}
        </div>
      )}
      {layout === 'list' && (
        <div className={`flex flex-col ${gapClass[gap]}`}>
          {items.map(itemCard)}
        </div>
      )}
      {layout === 'slider' && (
        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
          {items.map((item, i) => (
            <div key={i} className="shrink-0 w-64">
              {itemCard(item, i)}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
