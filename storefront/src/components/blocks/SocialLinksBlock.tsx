'use client';

import type { SocialLinksProps } from '@/lib/block-types';
import {
  Facebook,
  Twitter,
  Instagram,
  Youtube,
  Linkedin,
  Github,
  Globe,
  MessageCircle,
} from 'lucide-react';

interface Props extends SocialLinksProps {}

const platformIcons: Record<string, React.ElementType> = {
  facebook: Facebook,
  twitter: Twitter,
  x: Twitter,
  instagram: Instagram,
  youtube: Youtube,
  linkedin: Linkedin,
  github: Github,
  website: Globe,
  whatsapp: MessageCircle,
  tiktok: Globe,
  pinterest: Globe,
};

const platformColors: Record<string, string> = {
  facebook: 'hover:text-blue-600 hover:bg-blue-50',
  twitter: 'hover:text-sky-500 hover:bg-sky-50',
  x: 'hover:text-gray-900 hover:bg-gray-100',
  instagram: 'hover:text-pink-600 hover:bg-pink-50',
  youtube: 'hover:text-red-600 hover:bg-red-50',
  linkedin: 'hover:text-blue-700 hover:bg-blue-50',
  github: 'hover:text-gray-900 hover:bg-gray-100',
  whatsapp: 'hover:text-green-600 hover:bg-green-50',
  tiktok: 'hover:text-gray-900 hover:bg-gray-100',
  pinterest: 'hover:text-red-700 hover:bg-red-50',
  website: 'hover:text-gray-700 hover:bg-gray-50',
};

export default function SocialLinksBlock({
  title,
  links,
  style = 'icon-only',
  align = 'center',
}: Props) {
  if (!links || links.length === 0) return null;

  const alignClass =
    align === 'left'
      ? 'justify-start'
      : align === 'right'
      ? 'justify-end'
      : 'justify-center';

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && (
        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 text-center">
          {title}
        </p>
      )}
      <div className={`flex flex-wrap items-center gap-2 ${alignClass}`}>
        {links.map((link, i) => {
          const platform = link.platform.toLowerCase();
          const Icon = platformIcons[platform] || Globe;
          const colorClass = platformColors[platform] || 'hover:text-gray-700 hover:bg-gray-50';

          if (style === 'pill') {
            return (
              <a
                key={i}
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className={`inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-gray-600 border border-gray-200 transition ${colorClass}`}
              >
                <Icon className="w-4 h-4" />
                {link.label || link.platform}
              </a>
            );
          }

          if (style === 'icon-with-label') {
            return (
              <a
                key={i}
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className={`inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition hover:underline underline-offset-2 ${colorClass.replace(
                  /hover:bg-\S+/,
                  ''
                )}`}
              >
                <Icon className="w-5 h-5" />
                {link.label || link.platform}
              </a>
            );
          }

          return (
            <a
              key={i}
              href={link.url}
              target="_blank"
              rel="noopener noreferrer"
              className={`w-10 h-10 flex items-center justify-center rounded-full text-gray-500 transition ${colorClass}`}
              aria-label={link.label || link.platform}
            >
              <Icon className="w-5 h-5" />
            </a>
          );
        })}
      </div>
    </div>
  );
}
