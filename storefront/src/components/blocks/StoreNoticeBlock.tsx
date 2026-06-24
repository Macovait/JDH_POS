'use client';

import type { StoreNoticeProps } from '@/lib/block-types';
import { useState } from 'react';
import { X, Info, CheckCircle, AlertTriangle, AlertCircle } from 'lucide-react';

interface Props extends StoreNoticeProps {}

const typeStyles = {
  info: 'bg-blue-50 text-blue-800 border-blue-200',
  success: 'bg-green-50 text-green-800 border-green-200',
  warning: 'bg-amber-50 text-amber-800 border-amber-200',
  error: 'bg-red-50 text-red-800 border-red-200',
};

const typeIcons = {
  info: Info,
  success: CheckCircle,
  warning: AlertTriangle,
  error: AlertCircle,
};

export default function StoreNoticeBlock({
  message,
  type = 'info',
  dismissible = true,
  link_text,
  link_url,
}: Props) {
  const [dismissed, setDismissed] = useState(false);

  if (dismissed) return null;

  const Icon = typeIcons[type];

  return (
    <div className={`border ${typeStyles[type]}`}>
      <div className="max-w-7xl mx-auto px-4 py-2.5 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2 text-sm">
          <Icon className="w-4 h-4 shrink-0" />
          <span>{message}</span>
          {link_text && link_url && (
            <a
              href={link_url}
              className="font-semibold underline underline-offset-2 hover:opacity-80"
            >
              {link_text}
            </a>
          )}
        </div>
        {dismissible && (
          <button
            onClick={() => setDismissed(true)}
            className="shrink-0 p-1 rounded hover:bg-black/5 transition"
            aria-label="Dismiss notice"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  );
}
