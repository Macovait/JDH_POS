import type { BrandShowcaseProps } from '@/lib/block-types';

interface Props extends BrandShowcaseProps {}

export default function BrandShowcaseBlock({ title = 'Our Brands', brands, layout = 'grid' }: Props) {
  if (!brands || brands.length === 0) return null;

  if (layout === 'marquee') {
    return (
      <div className="max-w-7xl mx-auto px-4">
        {title && <h2 className="text-xl font-extrabold text-gray-900 mb-4">{title}</h2>}
        <div className="flex gap-6 overflow-x-auto pb-2 scrollbar-hide">
          {brands.map((b, i) => (
            <a key={i} href={b.link || '#'} className="shrink-0 group">
              <div className="w-28 h-16 bg-white border border-gray-100 rounded-xl flex items-center justify-center px-3 hover:shadow-sm transition">
                {b.logo ? (
                  <img src={b.logo} alt={b.name} className="max-w-full max-h-full object-contain" />
                ) : (
                  <span className="text-xs font-bold text-gray-500">{b.name}</span>
                )}
              </div>
            </a>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && <h2 className="text-xl font-extrabold text-gray-900 mb-4">{title}</h2>}
      <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-4">
        {brands.map((b, i) => (
          <a key={i} href={b.link || '#'} className="group">
            <div className="bg-white border border-gray-100 rounded-xl p-4 flex flex-col items-center justify-center h-28 hover:shadow-sm transition">
              {b.logo ? (
                <img src={b.logo} alt={b.name} className="max-w-full max-h-12 object-contain mb-2" />
              ) : (
                <span className="text-2xl mb-1">🏷️</span>
              )}
              <span className="text-xs font-medium text-gray-600 text-center">{b.name}</span>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}
