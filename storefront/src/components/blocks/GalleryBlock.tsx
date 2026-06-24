'use client';

import { useState } from 'react';
import { X } from 'lucide-react';
import type { GalleryProps } from '@/lib/block-types';

interface Props extends GalleryProps {}

export default function GalleryBlock({ images, columns = 3, gap = 'md', lightbox = true, aspect = 'square' }: Props) {
  const [active, setActive] = useState<number | null>(null);

  if (!images || images.length === 0) return null;

  const gapClass = gap === 'sm' ? 'gap-2' : gap === 'lg' ? 'gap-4' : 'gap-3';
  const gridClass =
    columns === 2
      ? 'grid-cols-2'
      : columns === 4
      ? 'grid-cols-2 md:grid-cols-4'
      : columns === 5
      ? 'grid-cols-2 md:grid-cols-5'
      : 'grid-cols-2 md:grid-cols-3';

  const aspectClass =
    aspect === 'video'
      ? 'aspect-video'
      : aspect === 'portrait'
      ? 'aspect-[3/4]'
      : aspect === 'auto'
      ? 'aspect-auto'
      : 'aspect-square';

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`grid ${gridClass} ${gapClass}`}>
        {images.map((img, i) => (
          <div
            key={i}
            className={`relative group overflow-hidden rounded-xl bg-gray-100 ${aspectClass} cursor-pointer`}
            onClick={() => lightbox && setActive(i)}
          >
            {img.link ? (
              <a href={img.link} className="block w-full h-full">
                <img src={img.src} alt={img.alt || ''} className="w-full h-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" />
              </a>
            ) : (
              <img src={img.src} alt={img.alt || ''} className="w-full h-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" />
            )}
            {img.caption && (
              <div className="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/60 to-transparent text-white text-xs font-medium">
                {img.caption}
              </div>
            )}
          </div>
        ))}
      </div>

      {lightbox && active !== null && (
        <div
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          onClick={() => setActive(null)}
        >
          <button
            className="absolute top-4 right-4 text-white p-2"
            onClick={() => setActive(null)}
            aria-label="Close lightbox"
          >
            <X size={28} />
          </button>
          <img
            src={images[active].src}
            alt={images[active].alt || ''}
            className="max-w-full max-h-[90vh] object-contain rounded-lg"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}
