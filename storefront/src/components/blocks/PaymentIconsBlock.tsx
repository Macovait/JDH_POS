'use client';

import type { PaymentIconsProps } from '@/lib/block-types';
import { CreditCard } from 'lucide-react';

interface Props extends PaymentIconsProps {}

// Built-in SVG icons for common payment methods
const paymentIconMap: Record<string, React.ReactNode> = {
  visa: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#1A1F71" />
      <path d="M19.5 21.5L21 10.5h3.5l-1.5 11h-3.5zM32.5 10.5c-.8-.3-2-.6-3.5-.6-3.8 0-6.5 2-6.5 4.8 0 2.1 2 3.3 3.5 4 1.5.7 2 1.2 2 1.8 0 1-.6 1.5-1.8 1.5-1.3 0-2.5-.5-3.2-.8l-.5-.2-.6 2.8c.7.3 2.1.7 3.5.7 4 0 6.6-2 6.6-4.9 0-1.6-1-2.8-3.2-3.9-1.3-.7-2-1.1-2-1.8 0-.6.5-1.2 1.7-1.2 1.1 0 1.9.4 2.5.7l.4.2.5-2.7zM42.5 10.5h-2.7c-.9 0-1.6.3-2 1l-5.7 13.5h3.6l.8-2.2h4.4l.5 2.2h3.2l-2.2-14.5zm-3.6 9.3l1.8-4.9 1 4.9h-2.8zM15.5 10.5l-3.2 11.5h-3.6l3.2-11.5h3.6zM10.5 10.5L6 21.9l-.3 1.5c-.2 1.2.2 2.2 2 2.2.8 0 1.5-.2 1.8-.3l.4-.2.7 2.8-.5.2c-.8.3-2 .5-3.2.5-3.6 0-5.4-1.8-5.4-5 0-1.7.7-4.3 1.5-6.8L4.5 10.5h3.5l.5 1.8.5-1.8h1.5z" fill="#fff" />
    </svg>
  ),
  mastercard: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#f5f5f5" />
      <circle cx="19" cy="16" r="10" fill="#EB001B" />
      <circle cx="29" cy="16" r="10" fill="#F79E1B" />
      <path d="M24 9a10 10 0 000 14 10 10 0 000-14z" fill="#FF5F00" />
    </svg>
  ),
  amex: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#016FD0" />
      <path d="M6 10h5l3 7V10h5l2.5 5 2.5-5h5v12h-5l-2.5-5-2.5 5h-3v-7l-3 7H6V10zM36 10h6v2h-4v2h4v2h-4v2h4v2h-6V10z" fill="#fff" />
    </svg>
  ),
  paypal: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#f5f5f5" />
      <path d="M17 10h6c3 0 5 1.5 5 4.5 0 3.5-2.5 5-6 5h-2l-1 5h-3l1.5-8H14l.5-2.5h2.5c1.5 0 3.5-.5 3.5-2.5 0-1.5-1-2.5-2.5-2.5h-1.5z" fill="#003087" />
      <path d="M28 10h6c3 0 5 1.5 5 4.5 0 3.5-2.5 5-6 5h-2l-1 5h-3l1.5-8H25l.5-2.5h2.5c1.5 0 3.5-.5 3.5-2.5 0-1.5-1-2.5-2.5-2.5h-1.5z" fill="#0070E0" />
    </svg>
  ),
  mpesa: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#00A650" />
      <text x="24" y="20" textAnchor="middle" fill="#fff" fontSize="10" fontWeight="bold" fontFamily="sans-serif">M-PESA</text>
    </svg>
  ),
  discover: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#f5f5f5" />
      <text x="8" y="20" fill="#FF6000" fontSize="9" fontWeight="bold" fontFamily="sans-serif">DISCOVER</text>
    </svg>
  ),
  applepay: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#000" />
      <text x="24" y="20" textAnchor="middle" fill="#fff" fontSize="8" fontWeight="bold" fontFamily="sans-serif">Pay</text>
    </svg>
  ),
  googlepay: (
    <svg viewBox="0 0 48 32" className="w-10 h-6">
      <rect width="48" height="32" rx="4" fill="#f5f5f5" />
      <text x="24" y="20" textAnchor="middle" fontSize="8" fontWeight="bold" fontFamily="sans-serif">
        <tspan fill="#4285F4">G</tspan>
        <tspan fill="#EA4335">o</tspan>
        <tspan fill="#FBBC05">o</tspan>
        <tspan fill="#4285F4">g</tspan>
        <tspan fill="#34A853">l</tspan>
        <tspan fill="#EA4335">e</tspan>
        <tspan fill="#000"> Pay</tspan>
      </text>
    </svg>
  ),
};

export default function PaymentIconsBlock({
  title = 'We Accept',
  icons,
  layout = 'row',
}: Props) {
  const displayIcons =
    icons && icons.length > 0
      ? icons
      : [
          { name: 'visa' },
          { name: 'mastercard' },
          { name: 'amex' },
          { name: 'paypal' },
          { name: 'mpesa' },
        ];

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && (
        <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 text-center">
          {title}
        </p>
      )}
      <div
        className={`flex flex-wrap items-center justify-center gap-3 ${
          layout === 'grid' ? 'grid grid-cols-4 md:grid-cols-6 gap-3' : ''
        }`}
      >
        {displayIcons.map((icon, i) => (
          <div
            key={i}
            className="flex items-center justify-center bg-white border border-gray-100 rounded-lg px-3 py-2"
            title={icon.alt || icon.name}
          >
            {icon.src ? (
              <img src={icon.src} alt={icon.alt || icon.name} className="h-6 w-auto" />
            ) : (
              paymentIconMap[icon.name.toLowerCase()] || (
                <CreditCard className="w-6 h-4 text-gray-400" />
              )
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
