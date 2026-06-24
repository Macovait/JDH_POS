'use client';

import type { FooterLinksProps } from '@/lib/block-types';
import SocialLinksBlock from './SocialLinksBlock';
import PaymentIconsBlock from './PaymentIconsBlock';

interface Props extends FooterLinksProps {}

const defaultSocialLinks = [
  { platform: 'facebook', url: '#', label: 'Facebook' },
  { platform: 'instagram', url: '#', label: 'Instagram' },
  { platform: 'twitter', url: '#', label: 'Twitter' },
  { platform: 'whatsapp', url: '#', label: 'WhatsApp' },
];

export default function FooterLinksBlock({
  columns,
  bottom_text = '© 2026 All rights reserved.',
  show_payment_icons = true,
  show_social_links = true,
}: Props) {
  const safeColumns = columns && columns.length > 0 ? columns : [
    {
      title: 'Shop',
      links: [
        { label: 'All Products', url: '/shop' },
        { label: 'New Arrivals', url: '/shop?sort=newest' },
        { label: 'Best Sellers', url: '/shop?sort=popular' },
        { label: 'Sale', url: '/shop?sale=true' },
      ],
    },
    {
      title: 'Support',
      links: [
        { label: 'Contact Us', url: '/contact' },
        { label: 'FAQs', url: '/faqs' },
        { label: 'Shipping & Returns', url: '/shipping' },
        { label: 'Track Order', url: '/track' },
      ],
    },
    {
      title: 'Company',
      links: [
        { label: 'About Us', url: '/about' },
        { label: 'Blog', url: '/blog' },
        { label: 'Careers', url: '/careers' },
        { label: 'Privacy Policy', url: '/privacy' },
      ],
    },
  ];

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10">
        {safeColumns.map((col, i) => (
          <div key={i}>
            <h4 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">
              {col.title}
            </h4>
            <ul className="space-y-2.5">
              {col.links.map((link, j) => (
                <li key={j}>
                  <a
                    href={link.url}
                    className="text-sm text-gray-600 hover:text-gray-900 transition"
                  >
                    {link.label}
                  </a>
                </li>
              ))}
            </ul>
          </div>
        ))}

        {/* Extra column for social/payment if enabled */}
        {(show_social_links || show_payment_icons) && (
          <div className="col-span-2 md:col-span-1">
            {show_social_links && (
              <div className="mb-6">
                <h4 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">
                  Follow Us
                </h4>
                <SocialLinksBlock
                  links={defaultSocialLinks}
                  style="icon-only"
                  align="left"
                />
              </div>
            )}
            {show_payment_icons && (
              <div>
                <h4 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">
                  Payment
                </h4>
                <PaymentIconsBlock icons={[]} />
              </div>
            )}
          </div>
        )}
      </div>

      <div className="border-t border-gray-100 pt-6 flex flex-col md:flex-row items-center justify-between gap-4">
        <p className="text-xs text-gray-500">{bottom_text}</p>
        <p className="text-xs text-gray-400">
          Powered by JDH POS
        </p>
      </div>
    </div>
  );
}
