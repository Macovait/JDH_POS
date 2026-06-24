import type { Banner } from '@/lib/api';

interface Props {
  banners: Banner[];
  tenantId: number;
}

export default function PromoBannerGrid({ banners, tenantId }: Props) {
  // Use banners from index 2 onwards (0-1 are used by Hero and mid banner)
  const promos = banners.slice(2, 5);
  if (promos.length === 0) return null;

  const gridCols = promos.length === 1 ? 'grid-cols-1' : promos.length === 2 ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3';

  return (
    <section>
      <div className={`grid ${gridCols} gap-3 md:gap-4`}>
        {promos.map((banner, i) => (
          <a
            key={i}
            href={banner.link_url ?? `/${tenantId}/products`}
            className="group relative rounded-2xl overflow-hidden bg-gray-100 aspect-[16/7] hover:shadow-lg transition"
          >
            {banner.image_url ? (
              <img
                src={banner.image_url}
                alt={banner.title ?? ''}
                loading="lazy"
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-4xl opacity-20">🎁</div>
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
            {banner.title && (
              <div className="absolute bottom-0 left-0 right-0 p-4">
                <p className="text-white font-extrabold text-sm md:text-base drop-shadow">{banner.title}</p>
                {banner.subtitle && <p className="text-white/80 text-xs">{banner.subtitle}</p>}
              </div>
            )}
          </a>
        ))}
      </div>
    </section>
  );
}
