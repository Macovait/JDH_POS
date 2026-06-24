'use client';

import { useState } from 'react';
import { X, Info, CheckCircle, AlertTriangle, AlertCircle } from 'lucide-react';
import type { MessageBoxProps } from '@/lib/block-types';

interface Props extends MessageBoxProps {}

const typeConfig = {
  info: {
    icon: Info,
    class: 'bg-blue-50 border-blue-200 text-blue-900',
    iconColor: 'text-blue-600',
  },
  success: {
    icon: CheckCircle,
    class: 'bg-emerald-50 border-emerald-200 text-emerald-900',
    iconColor: 'text-emerald-600',
  },
  warning: {
    icon: AlertTriangle,
    class: 'bg-amber-50 border-amber-200 text-amber-900',
    iconColor: 'text-amber-600',
  },
  error: {
    icon: AlertCircle,
    class: 'bg-red-50 border-red-200 text-red-900',
    iconColor: 'text-red-600',
  },
};

const emojiMap: Record<string, string> = {
  info: 'ℹ️',
  success: '✅',
  warning: '⚠️',
  error: '❌',
};

export default function MessageBoxBlock({
  title,
  message,
  type = 'info',
  dismissible = false,
  icon,
  link_text,
  link_url,
}: Props) {
  const [closed, setClosed] = useState(false);
  const config = typeConfig[type] || typeConfig.info;
  const Icon = config.icon;

  if (closed) return null;

  return (
    <div className="max-w-4xl mx-auto px-4">
      <div className={`rounded-xl border p-4 flex items-start gap-3 ${config.class}`}>
        <div className={`shrink-0 mt-0.5 ${config.iconColor}`}>
          {icon ? <span className="text-lg">{emojiMap[icon] || icon}</span> : <Icon size={20} />}
        </div>
        <div className="flex-1">
          {title && <h3 className="font-bold text-sm mb-1">{title}</h3>}
          <p className="text-sm leading-relaxed">{message}</p>
          {link_text && link_url && (
            <a href={link_url} className={`text-sm font-semibold underline mt-2 inline-block ${config.iconColor}`}>
              {link_text}
            </a>
          )}
        </div>
        {dismissible && (
          <button
            onClick={() => setClosed(true)}
            className="shrink-0 text-gray-400 hover:text-gray-600"
            aria-label="Dismiss"
          >
            <X size={18} />
          </button>
        )}
      </div>
    </div>
  );
}
