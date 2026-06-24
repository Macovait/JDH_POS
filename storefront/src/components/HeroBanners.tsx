'use client';
import { useState, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Banner } from '@/lib/api';

interface Props { banners: Banner[]; tenantId: number }

export default function HeroBanners({ banners, tenantId }: Props) {
  const [idx, setIdx] = useState(0);
  if (!banners || banners.length === 0) {
    return (
      <div className="w-full rounded-xl flex items-center justify-center"
        style={{ minHeight: 280, background: 'linear-gradient(135deg, var(--brand-color,#f68b1e) 0%, #d97706 100%)' }}>
        <p className="text-white/60 text-sm">No banners configured</p>
      </div>
    );
  }
  const banner = banners[idx];

  const prev = useCallback(() => setIdx((i) => (i - 1 + banners.length) % banners.length), [banners.length]);
  const next = useCallback(() => setIdx((i) => (i + 1) % banners.length), [banners.length]);

  useEffect(() => {
    if (banners.length <= 1) return;
    const timer = setInterval(next, 5000);
    return () => clearInterval(timer);
  }, [banners.length, next]);

  return (
    <div className="relative w-full overflow-hidden rounded-2xl bg-gray-900" style={{ minHeight: 280 }}>
      {banner.image_url ? (
        <img src={banner.image_url} alt={banner.title} loading="lazy"
          className="w-full object-cover" style={{ maxHeight: 420, minHeight: 220, width: '100%', objectFit: 'cover' }} />
      ) : (
        <div className="w-full flex items-center justify-center py-24"
          style={{ background: 'linear-gradient(135deg, var(--brand-color,#f68b1e) 0%, #d97706 100%)' }} />
      )}

      {/* Overlay text */}
      <div className="absolute inset-0 flex flex-col items-start justify-center px-8 md:px-16"
        style={{ background: 'linear-gradient(to right, rgba(0,0,0,0.55) 0%, transparent 70%)' }}>
        <h1 className="text-white text-2xl md:text-4xl font-extrabold mb-2 max-w-xl leading-tight drop-shadow-lg">
          {banner.title}
        </h1>
        {banner.subtitle && (
          <p className="text-white/80 text-sm md:text-base mb-5 max-w-md">{banner.subtitle}</p>
        )}
        {banner.link_url && (
          <a href={banner.link_url}
            className="px-5 py-2.5 rounded-lg text-sm font-bold text-white shadow-lg transition hover:opacity-90"
            style={{ background: 'var(--brand-color, #f68b1e)' }}>
            {banner.button_text ?? 'Shop Now'}
          </a>
        )}
      </div>

      {/* Nav arrows */}
      {banners.length > 1 && (
        <>
          <button onClick={prev}
            className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 bg-white/30 hover:bg-white/60 rounded-full flex items-center justify-center text-white transition">
            <ChevronLeft size={18} />
          </button>
          <button onClick={next}
            className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 bg-white/30 hover:bg-white/60 rounded-full flex items-center justify-center text-white transition">
            <ChevronRight size={18} />
          </button>
          {/* Dots */}
          <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
            {banners.map((_, i) => (
              <button key={i} onClick={() => setIdx(i)}
                className={`w-2 h-2 rounded-full transition ${i === idx ? 'bg-white scale-125' : 'bg-white/40'}`} />
            ))}
          </div>
        </>
      )}
    </div>
  );
}
