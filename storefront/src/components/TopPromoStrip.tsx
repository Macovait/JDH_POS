'use client';
import { X } from 'lucide-react';
import { useState } from 'react';

interface Props {
  text: string;
  href?: string;
  bgColor?: string;
  textColor?: string;
}

export default function TopPromoStrip({ text, href, bgColor = '#1a1a2e', textColor = '#fff' }: Props) {
  const [closed, setClosed] = useState(false);
  if (closed || !text) return null;

  const content = (
    <span className="text-xs font-semibold tracking-wide">
      {text}
    </span>
  );

  return (
    <div
      data-announcement-bar
      className="relative text-center py-1.5 px-8"
      style={{ background: bgColor, color: textColor }}
    >
      {href ? (
        <a href={href} className="hover:underline block">
          {content}
        </a>
      ) : (
        content
      )}
      <button
        onClick={() => setClosed(true)}
        className="absolute right-3 top-1/2 -translate-y-1/2 opacity-60 hover:opacity-100 transition"
        aria-label="Close promo"
      >
        <X size={14} />
      </button>
    </div>
  );
}
