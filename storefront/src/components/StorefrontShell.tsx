'use client';
import { useEffect, useState } from 'react';
import type { TenantInfo, StoreSettings } from '@/lib/api';
import { useCartStore } from '@/lib/store';
import Navbar from './Navbar';
import CartDrawer from './CartDrawer';
import TrustBar from './TrustBar';
import TopPromoStrip from './TopPromoStrip';
import BackToTop from './BackToTop';
import MobileBottomNav from './MobileBottomNav';
import { usePathname } from 'next/navigation';
import Link from 'next/link';

interface Props {
  tenant: TenantInfo;
  settings: StoreSettings | null;
  children: React.ReactNode;
}

export default function StorefrontShell({ tenant, settings, children }: Props) {
  const { setTenant } = useCartStore();
  const pathname = usePathname();
  const [isHomepage, setIsHomepage] = useState(false);

  useEffect(() => {
    setTenant(tenant);
    // Inject CSS custom property for brand color
    document.documentElement.style.setProperty('--brand-color', tenant.primary_color ?? '#f68b1e');
    document.documentElement.style.setProperty('--brand-dark', '#d97706');
    
    // Check if homepage
    setIsHomepage(pathname === `/${tenant.tenant_id}` || pathname === `/${tenant.tenant_id}/`);
  }, [tenant, setTenant, pathname]);

  // Live preview: listen for settings changes from PHP customizer iframe parent
  useEffect(() => {
    // Signal to parent that we are ready to receive preview updates
    if (window.parent !== window) {
      try { window.parent.postMessage({ type: 'STOREFRONT_READY' }, '*'); } catch (e) {}
    }
    
    const handleMessage = (e: MessageEvent) => {
      if (e.data?.type !== 'STOREFRONT_SETTINGS') return;
      console.log('[Storefront] received STOREFRONT_SETTINGS', e.data.settings);
      const s = e.data.settings;
      if (!s) return;
      
      // Update CSS custom properties for instant visual feedback
      if (s.primary_color) document.documentElement.style.setProperty('--brand-color', s.primary_color);
      if (s.secondary_color) document.documentElement.style.setProperty('--secondary-color', s.secondary_color);
      if (s.header_bg_color) document.documentElement.style.setProperty('--header-bg', s.header_bg_color);
      if (s.header_text_color) document.documentElement.style.setProperty('--header-text', s.header_text_color);
      if (s.announcement_bar_color) document.documentElement.style.setProperty('--announcement-bg', s.announcement_bar_color);
      
      if (s.font_family && s.font_family !== 'system') {
        document.documentElement.style.setProperty('--font-family', s.font_family);
      }
      
      // Update page title for store name
      if (s.store_name) document.title = s.store_name;
      
      // Toggle announcement bar visibility
      const promo = document.querySelector('[data-announcement-bar]');
      if (promo) {
        (promo as HTMLElement).style.display = s.announcement_bar_enabled === '1' ? '' : 'none';
      }
      
      // Toggle header elements by data attributes
      const toggleAttr = (sel: string, enabled: string | undefined) => {
        const el = document.querySelector(sel);
        if (!el || enabled === undefined) return;
        (el as HTMLElement).style.display = enabled === '1' ? '' : 'none';
      };
      
      toggleAttr('[data-header-cart]', s.header_show_cart);
      toggleAttr('[data-header-account]', s.header_show_account);
      toggleAttr('[data-header-search]', s.header_show_search);
      toggleAttr('[data-header-search-mobile]', s.header_show_search);
      toggleAttr('[data-header-wishlist]', s.header_show_wishlist);
      
      // Toggle homepage sections by data attributes
      toggleAttr('[data-deal-spotlight]', s.show_deal_spotlight);
      toggleAttr('[data-newsletter]', s.show_newsletter);
      toggleAttr('[data-recently-viewed]', s.show_recently_viewed);
      
      // Layout / style classes on <html>
      const root = document.documentElement;
      root.classList.remove(
        'layout-mode-full-width', 'layout-mode-boxed', 'layout-mode-framed',
        'button-style-rounded', 'button-style-sharp', 'button-style-pill',
        'button-padding-compact', 'button-padding-normal', 'button-padding-spacious',
        'card-style-flat', 'card-style-elevated', 'card-style-bordered',
        'header-style-standard', 'header-style-minimal', 'header-style-centered', 'header-style-modern',
        'header-sticky', 'header-transparent-home',
        'mobile-menu-overlay', 'mobile-menu-slideout', 'mobile-menu-dropdown',
        'dropdown-style-simple', 'dropdown-style-boxed', 'dropdown-style-mega'
      );
      
      if (s.layout_mode) root.classList.add('layout-mode-' + s.layout_mode);
      if (s.button_style) root.classList.add('button-style-' + s.button_style);
      if (s.button_padding) root.classList.add('button-padding-' + s.button_padding);
      if (s.card_style) root.classList.add('card-style-' + s.card_style);
      if (s.header_style) root.classList.add('header-style-' + s.header_style);
      if (s.header_sticky === '1') root.classList.add('header-sticky');
      if (s.header_transparent_home === '1') root.classList.add('header-transparent-home');
      if (s.header_mobile_style) root.classList.add('mobile-menu-' + s.header_mobile_style);
      if (s.header_dropdown_style) root.classList.add('dropdown-style-' + s.header_dropdown_style);
    };
    
    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, []);

  return (
    <>
      {settings?.announcement_bar_enabled !== '0' && (
        <TopPromoStrip
          text={settings?.announcement_bar_text || 'Free delivery on orders over KES 5,000 · Same-day in Nairobi'}
          href={`/${tenant.tenant_id}/products`}
          bgColor={settings?.announcement_bar_color || '#1a1a2e'}
          textColor="#fff"
        />
      )}
      
      <Navbar tenant={tenant} settings={settings} />
      <TrustBar />
      <CartDrawer tenantId={tenant.tenant_id} currency={tenant.currency || 'KES'} />
      
      <main className={`min-h-screen bg-gray-50 dark:bg-slate-900 pb-20 md:pb-0 ${isHomepage ? 'header-transparent-home' : ''}`}>
        {children}
      </main>
      
      <MobileBottomNav tenant={tenant} />
      <BackToTop />
      
      <footer className="bg-slate-800 dark:bg-slate-900 text-white mt-16 border-t border-slate-700/50">
        {/* Newsletter bar */}
        <div className="bg-brand">
          <div className="max-w-7xl mx-auto px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div className="flex items-center gap-2 text-sm font-bold text-white">
              <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
              </svg>
              Subscribe to our newsletter for the latest deals
            </div>
            <Link 
              href={`/${tenant.tenant_id}/products`} 
              className="inline-flex items-center gap-1.5 text-xs font-bold bg-white text-brand px-4 py-1.5 rounded-lg hover:bg-gray-100 transition-colors shadow-lg"
            >
              Shop Now →
            </Link>
          </div>
        </div>

        {/* Links */}
        <div className="max-w-7xl mx-auto px-4 py-10 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
          {/* About */}
          <div>
            <div className="font-bold text-base mb-3 text-white">{tenant.name}</div>
            <p className="text-slate-400 text-xs leading-relaxed">
              {settings?.site_description || 'Your trusted online store. Quality products, fast delivery, and secure payments.'}
            </p>
            {settings?.store_email && (
              <p className="text-slate-500 text-xs mt-2">📧 {settings.store_email}</p>
            )}
            {settings?.store_phone && (
              <p className="text-slate-500 text-xs mt-1">📞 {settings.store_phone}</p>
            )}
            {tenant.whatsapp && (
              <a 
                href={`https://wa.me/${tenant.whatsapp.replace(/\D/g, '')}`} 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 mt-3 bg-green-600 hover:bg-green-500 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
              >
                💬 WhatsApp Us
              </a>
            )}
          </div>

          {/* Quick Links */}
          <div>
            <div className="font-semibold mb-3 text-slate-300">Quick Links</div>
            <ul className="space-y-2 text-slate-400 text-xs">
              <li>
                <Link href={`/${tenant.tenant_id}`} className="hover:text-brand transition-colors">
                  Home
                </Link>
              </li>
              <li>
                <Link href={`/${tenant.tenant_id}/products`} className="hover:text-brand transition-colors">
                  All Products
                </Link>
              </li>
              <li>
                <Link href={`/${tenant.tenant_id}/track`} className="hover:text-brand transition-colors">
                  Track Order
                </Link>
              </li>
              <li>
                <Link href={`/${tenant.tenant_id}/contact`} className="hover:text-brand transition-colors">
                  Contact Us
                </Link>
              </li>
            </ul>
          </div>

          {/* Customer Service */}
          <div>
            <div className="font-semibold mb-3 text-slate-300">Customer Service</div>
            <ul className="space-y-2 text-slate-400 text-xs">
              <li>
                <Link href={`/${tenant.tenant_id}/track`} className="hover:text-brand transition-colors">
                  Order Status
                </Link>
              </li>
              <li>
                <span className="hover:text-brand transition-colors cursor-pointer">
                  Shipping & Delivery
                </span>
              </li>
              <li>
                <span className="hover:text-brand transition-colors cursor-pointer">
                  Returns Policy
                </span>
              </li>
              <li>
                <span className="hover:text-brand transition-colors cursor-pointer">
                  Help Center
                </span>
              </li>
            </ul>
          </div>

          {/* Payments & Social */}
          <div>
            <div className="font-semibold mb-3 text-slate-300">Payments</div>
            <div className="flex flex-wrap gap-2 text-slate-400 text-xs">
              <span className="bg-slate-700/50 px-2.5 py-1 rounded-lg">M-Pesa</span>
              <span className="bg-slate-700/50 px-2.5 py-1 rounded-lg">Cash</span>
              <span className="bg-slate-700/50 px-2.5 py-1 rounded-lg">Card</span>
              <span className="bg-slate-700/50 px-2.5 py-1 rounded-lg">PayPal</span>
            </div>
            
            <div className="mt-4">
              <div className="font-semibold mb-2 text-slate-300 text-xs">Follow Us</div>
              <div className="flex gap-3 text-slate-400">
                {settings?.facebook_url ? (
                  <a href={settings.facebook_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="Facebook">📘</a>
                ) : null}
                {settings?.instagram_url ? (
                  <a href={settings.instagram_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="Instagram">📸</a>
                ) : null}
                {settings?.twitter_url ? (
                  <a href={settings.twitter_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="Twitter">🐦</a>
                ) : null}
                {settings?.tiktok_url ? (
                  <a href={settings.tiktok_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="TikTok">🎵</a>
                ) : null}
                {settings?.youtube_url ? (
                  <a href={settings.youtube_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="YouTube">▶️</a>
                ) : null}
                {settings?.linkedin_url ? (
                  <a href={settings.linkedin_url} target="_blank" rel="noopener noreferrer" className="hover:text-brand transition-colors text-lg" aria-label="LinkedIn">💼</a>
                ) : null}
              </div>
            </div>
          </div>
        </div>

        {/* Bottom bar */}
        <div className="border-t border-slate-700/50">
          <div className="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-slate-500 text-xs">
            <span>© {new Date().getFullYear()} {tenant.name}. All rights reserved.</span>
            <span className="flex items-center gap-3">
              <span className="hover:text-slate-300 transition-colors cursor-pointer">Privacy Policy</span>
              <span className="text-slate-700">|</span>
              <span className="hover:text-slate-300 transition-colors cursor-pointer">Terms of Service</span>
            </span>
          </div>
        </div>
      </footer>
    </>
  );
}