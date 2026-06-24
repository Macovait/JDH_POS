'use client';

import { useState } from 'react';
import { Play, X } from 'lucide-react';
import type { VideoButtonProps } from '@/lib/block-types';

interface Props extends VideoButtonProps {}

function getEmbedUrl(url: string): string {
  if (url.includes('youtube.com/watch?v=')) {
    const id = url.split('v=')[1]?.split('&')[0];
    return id ? `https://www.youtube.com/embed/${id}` : url;
  }
  if (url.includes('youtu.be/')) {
    const id = url.split('youtu.be/')[1]?.split('?')[0];
    return id ? `https://www.youtube.com/embed/${id}` : url;
  }
  if (url.includes('vimeo.com/')) {
    const id = url.split('vimeo.com/')[1]?.split('?')[0];
    return id ? `https://player.vimeo.com/video/${id}` : url;
  }
  return url;
}

export default function VideoButtonBlock({
  video_url,
  title,
  button_text = 'Watch Video',
  button_size = 'md',
  thumbnail,
  align = 'center',
}: Props) {
  const [open, setOpen] = useState(false);

  const sizeMap = {
    sm: 'px-4 py-2 text-xs',
    md: 'px-6 py-3 text-sm',
    lg: 'px-8 py-4 text-base',
  };

  const alignClass = align === 'left' ? 'text-left' : align === 'right' ? 'text-right' : 'text-center';

  return (
    <div className={`max-w-4xl mx-auto px-4 ${alignClass}`}>
      {title && <h2 className="text-xl font-bold text-gray-900 mb-4">{title}</h2>}

      {thumbnail ? (
        <div className="relative rounded-xl overflow-hidden aspect-video cursor-pointer group" onClick={() => setOpen(true)}>
          <img src={thumbnail} alt={button_text} className="w-full h-full object-cover" />
          <div className="absolute inset-0 bg-black/30 flex items-center justify-center group-hover:bg-black/40 transition">
            <div className="w-14 h-14 rounded-full bg-white/90 flex items-center justify-center shadow-lg">
              <Play size={24} className="text-gray-900 ml-1" fill="currentColor" />
            </div>
          </div>
        </div>
      ) : (
        <button
          onClick={() => setOpen(true)}
          className={`inline-flex items-center gap-2 rounded-xl font-bold text-white transition hover:opacity-90 ${sizeMap[button_size]}`}
          style={{ background: 'var(--brand-color)' }}
        >
          <Play size={18} fill="currentColor" />
          {button_text}
        </button>
      )}

      {open && (
        <div
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          onClick={() => setOpen(false)}
        >
          <button
            className="absolute top-4 right-4 text-white p-2"
            onClick={() => setOpen(false)}
            aria-label="Close video"
          >
            <X size={28} />
          </button>
          <div className="w-full max-w-4xl aspect-video" onClick={(e) => e.stopPropagation()}>
            <iframe
              src={getEmbedUrl(video_url)}
              title={title || 'Video'}
              className="w-full h-full rounded-lg"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          </div>
        </div>
      )}
    </div>
  );
}
