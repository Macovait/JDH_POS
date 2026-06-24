'use client';

import { useState, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { SliderProps } from '@/lib/block-types';

interface Props extends SliderProps {
  tenantId?: number;
}

export default function SliderBlock({
  items,
  autoplay = true,
  interval = 5000,
  show_arrows = true,
  show_dots = true,
  slides_per_view = 1,
}: Props) {
  const [current, setCurrent] = useState(0);
  const total = items?.length ?? 0;
  const visible = Math.min(slides_per_view, total || 1);
  const maxIndex = Math.max(0, total - visible);

  const next = useCallback(() => {
    setCurrent((c) => (c >= maxIndex ? 0 : c + 1));
  }, [maxIndex]);

  const prev = useCallback(() => {
    setCurrent((c) => (c <= 0 ? maxIndex : c - 1));
  }, [maxIndex]);

  useEffect(() => {
    if (!autoplay || total <= visible) return;
    const timer = setInterval(next, interval);
    return () => clearInterval(timer);
  }, [autoplay, interval, next, total, visible]);

  if (!items || total === 0) return null;

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="relative overflow-hidden">
        <div
          className="flex transition-transform duration-500 ease-out"
          style={{ transform: `translateX(-${current * (100 / visible)}%)` }}
        >
          {items.map((item, i) => (
            <div key={i} className="flex-shrink-0 px-2" style={{ width: `${100 / visible}%` }}>
              <div className="relative rounded-xl overflow-hidden bg-gray-100 aspect-[16/9]">
                {item.image ? (
                  <img src={item.image} alt={item.title || ''} className="w-full h-full object-cover" loading="lazy" />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-400 text-sm">No image</div>
                )}
                {(item.title || item.subtitle) && (
                  <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/70 to-transparent">
                    {item.title && <p className="text-white font-bold text-sm md:text-base">{item.title}</p>}
                    {item.subtitle && <p className="text-white/80 text-xs mt-1">{item.subtitle}</p>}
                  </div>
                )}
                {item.link && (
                  <a href={item.link} className="absolute inset-0" aria-label={item.title || 'Slide link'} />
                )}
              </div>
            </div>
          ))}
        </div>

        {show_arrows && total > visible && (
          <>
            <button
              onClick={prev}
              className="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-700 hover:bg-white transition"
              aria-label="Previous slide"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              onClick={next}
              className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 shadow flex items-center justify-center text-gray-700 hover:bg-white transition"
              aria-label="Next slide"
            >
              <ChevronRight size={18} />
            </button>
          </>
        )}
      </div>

      {show_dots && total > visible && (
        <div className="flex justify-center gap-2 mt-3">
          {Array.from({ length: maxIndex + 1 }).map((_, i) => (
            <button
              key={i}
              onClick={() => setCurrent(i)}
              className={`w-2 h-2 rounded-full transition-all ${i === current ? 'bg-orange-500 w-5' : 'bg-gray-300'}`}
              aria-label={`Go to slide ${i + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}
