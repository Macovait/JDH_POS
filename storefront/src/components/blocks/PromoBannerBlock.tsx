import type { PromoBannerProps } from '@/lib/block-types';

interface Props extends PromoBannerProps {
  tenantId: number;
}

export default function PromoBannerBlock({ image, title, subtitle, cta_text, cta_link, align = 'center', height = 'md', overlay_color }: Props) {
  const heightMap = { sm: 'h-32 md:h-40', md: 'h-40 md:h-56', lg: 'h-52 md:h-72' };
  const alignClass = align === 'center' ? 'items-center text-center' : align === 'right' ? 'items-end text-right' : 'items-start text-left';

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`relative rounded-2xl overflow-hidden ${heightMap[height]}`}>
        <img src={image} alt={title || ''} className="w-full h-full object-cover" loading="lazy" />
        <div
          className={`absolute inset-0 flex flex-col justify-center px-6 md:px-12 ${alignClass}`}
          style={{ background: overlay_color || 'rgba(0,0,0,0.35)' }}
        >
          {title && <p className="text-white text-xl md:text-3xl font-extrabold drop-shadow">{title}</p>}
          {subtitle && <p className="text-white/90 text-sm mt-1">{subtitle}</p>}
          {cta_text && cta_link && (
            <a href={cta_link} className="mt-4 inline-block bg-white text-gray-900 text-xs font-bold px-5 py-2 rounded-lg hover:bg-gray-100 transition">
              {cta_text}
            </a>
          )}
        </div>
      </div>
    </div>
  );
}
