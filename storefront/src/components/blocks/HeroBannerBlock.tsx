'use client';

import { useState, useEffect, useCallback } from 'react';
import type { HeroBannerProps } from '@/lib/block-types';

interface Props extends HeroBannerProps {
  tenantId: number;
}

export default function HeroBannerBlock({ slides, autoplay = true, interval = 5000 }: Props) {
  const [current, setCurrent] = useState(0);

  const next = useCallback(() => {
    setCurrent((c) => (c + 1) % slides.length);
  }, [slides.length]);

  useEffect(() => {
    if (!autoplay || slides.length <= 1) return;
    const timer = setInterval(next, interval);
    return () => clearInterval(timer);
  }, [autoplay, interval, next, slides.length]);

  if (!slides || slides.length === 0) return null;

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="relative rounded-2xl overflow-hidden aspect-[16/7] md:aspect-[16/6]">
        {slides.map((slide, i) => (
          <div
            key={i}
            className={`absolute inset-0 transition-opacity duration-700 ${i === current ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
          >
            <img src={slide.image} alt={slide.title} className="w-full h-full object-cover" loading={i === 0 ? 'eager' : 'lazy'} />
            {slide.overlay !== false && (
              <div className="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent" />
            )}
            <div className="absolute inset-0 flex flex-col justify-center px-6 md:px-14">
              <p className="text-white text-xl md:text-4xl font-extrabold drop-shadow-lg max-w-lg">{slide.title}</p>
              {slide.subtitle && <p className="text-white/90 text-sm md:text-base mt-2 max-w-md">{slide.subtitle}</p>}
              {slide.cta_text && slide.cta_link && (
                <a href={slide.cta_link} className="mt-5 inline-block w-fit bg-white text-gray-900 text-xs md:text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-gray-100 transition">
                  {slide.cta_text}
                </a>
              )}
            </div>
          </div>
        ))}

        {/* Dots */}
        {slides.length > 1 && (
          <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2">
            {slides.map((_, i) => (
              <button
                key={i}
                onClick={() => setCurrent(i)}
                className={`w-2 h-2 rounded-full transition-all ${i === current ? 'bg-white w-5' : 'bg-white/50'}`}
                aria-label={`Go to slide ${i + 1}`}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
