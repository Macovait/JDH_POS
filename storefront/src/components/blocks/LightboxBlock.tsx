'use client';

import { useState } from 'react';
import type { LightboxProps } from '@/lib/block-types';
import { X, Play } from 'lucide-react';

export default function LightboxBlock({
  trigger_text = 'Open',
  trigger_image,
  content_type,
  content,
  caption,
  button_style = 'primary',
  align = 'center',
}: LightboxProps) {
  const [open, setOpen] = useState(false);

  const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  const buttonClass = {
    primary: 'px-5 py-2.5 text-sm font-semibold text-white bg-orange-500 rounded-xl hover:bg-orange-600 transition',
    secondary: 'px-5 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition',
    link: 'text-sm font-semibold hover:underline',
  };

  return (
    <div className={`max-w-7xl mx-auto px-4 ${alignClass[align]}`}>
      <button type="button" onClick={() => setOpen(true)} className={buttonClass[button_style]}>
        {trigger_image ? (
          <span className="inline-flex items-center gap-2">
            <img src={trigger_image} alt={trigger_text} className="w-8 h-8 object-cover rounded" />
            {trigger_text}
          </span>
        ) : content_type === 'video' ? (
          <span className="inline-flex items-center gap-2">
            <span className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
              <Play className="w-4 h-4" />
            </span>
            {trigger_text}
          </span>
        ) : (
          trigger_text
        )}
      </button>

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          onClick={() => setOpen(false)}
        >
          <div className="relative max-w-4xl w-full max-h-[90vh] overflow-auto bg-white rounded-2xl shadow-2xl">
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="absolute top-3 right-3 p-2 bg-black/50 text-white rounded-full hover:bg-black/70 transition z-10"
            >
              <X className="w-4 h-4" />
            </button>
            <div className="p-4">
              {content_type === 'image' && (
                <img src={content} alt={caption || ''} className="w-full rounded-xl" />
              )}
              {content_type === 'video' && (
                <div className="aspect-video rounded-xl overflow-hidden bg-black">
                  <iframe
                    src={content}
                    title={caption || 'Video'}
                    className="w-full h-full"
                    allowFullScreen
                  />
                </div>
              )}
              {content_type === 'html' && (
                <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: content }} />
              )}
              {caption && <p className="text-sm text-gray-500 mt-3 text-center">{caption}</p>}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
