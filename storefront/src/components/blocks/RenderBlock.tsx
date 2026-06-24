'use client';

import dynamic from 'next/dynamic';
import type { TypedBlock } from '@/lib/block-types';

// Static imports for lightweight blocks
import TextSectionBlock from './TextSectionBlock';
import PromoBannerBlock from './PromoBannerBlock';
import ImageTextBlock from './ImageTextBlock';
import TestimonialsBlock from './TestimonialsBlock';
import BrandShowcaseBlock from './BrandShowcaseBlock';
import NewsletterBlock from './NewsletterBlock';
import TrustBarBlock from './TrustBarBlock';
import RawHtmlBlock from './RawHtmlBlock';
import StoreNoticeBlock from './StoreNoticeBlock';
import SocialLinksBlock from './SocialLinksBlock';
import ButtonBlock from './ButtonBlock';
import DividerBlock from './DividerBlock';
import TitleBlock from './TitleBlock';
import ImageBlock from './ImageBlock';
import ImageBoxBlock from './ImageBoxBlock';
import TeamMemberBlock from './TeamMemberBlock';
import PriceTableBlock from './PriceTableBlock';
import SearchBlock from './SearchBlock';
import LightboxBlock from './LightboxBlock';
import MenuBlock from './MenuBlock';
import InstagramFeedBlock from './InstagramFeedBlock';
import LottieBlock from './LottieBlock';
import FeaturedItemsBlock from './FeaturedItemsBlock';
import PortfolioBlock from './PortfolioBlock';
import ProductListBlock from './ProductListBlock';
import ProductCategoryBlock from './ProductCategoryBlock';

// Dynamic imports for data-heavy / interactive blocks
const HeroBannerBlock = dynamic(() => import('./HeroBannerBlock'), { ssr: true });
const ProductGridBlock = dynamic(() => import('./ProductGridBlock'), { ssr: true });
const CategoriesGridBlock = dynamic(() => import('./CategoriesGridBlock'), { ssr: true });
const FlashSaleBlock = dynamic(() => import('./FlashSaleBlock'), { ssr: true });
const FeaturedProductBlock = dynamic(() => import('./FeaturedProductBlock'), { ssr: true });
const BlogPostsBlock = dynamic(() => import('./BlogPostsBlock'), { ssr: true });
const PaymentIconsBlock = dynamic(() => import('./PaymentIconsBlock'), { ssr: true });
const FooterLinksBlock = dynamic(() => import('./FooterLinksBlock'), { ssr: true });
const SliderBlock = dynamic(() => import('./SliderBlock'), { ssr: false });
const AccordionBlock = dynamic(() => import('./AccordionBlock'), { ssr: false });
const CountdownBlock = dynamic(() => import('./CountdownBlock'), { ssr: false });
const GalleryBlock = dynamic(() => import('./GalleryBlock'), { ssr: false });
const IconBoxBlock = dynamic(() => import('./IconBoxBlock'), { ssr: true });
const MapBlock = dynamic(() => import('./MapBlock'), { ssr: true });
const VideoButtonBlock = dynamic(() => import('./VideoButtonBlock'), { ssr: false });
const MessageBoxBlock = dynamic(() => import('./MessageBoxBlock'), { ssr: false });

// Nested layout blocks imported dynamically to avoid circular imports with RenderBlock
const RowBlock = dynamic(() => import('./RowBlock'), { ssr: false });
const ColumnBlock = dynamic(() => import('./ColumnBlock'), { ssr: false });
const SectionBlock = dynamic(() => import('./SectionBlock'), { ssr: false });
const TabsBlock = dynamic(() => import('./TabsBlock'), { ssr: false });
const StackBlock = dynamic(() => import('./StackBlock'), { ssr: false });

interface Props {
  block: TypedBlock;
  tenantId: number;
}

export default function RenderBlock({ block, tenantId }: Props): React.ReactNode {
  const { type, props, _fallback_html } = block;

  // If props is empty but fallback HTML exists, render raw HTML
  if ((!props || Object.keys(props).length === 0) && _fallback_html) {
    return <RawHtmlBlock html={_fallback_html} />;
  }

  switch (type) {
    case 'hero-banner':
      return <HeroBannerBlock {...(props as any)} tenantId={tenantId} />;

    case 'product-grid':
      return <ProductGridBlock {...(props as any)} tenantId={tenantId} />;

    case 'categories-grid':
      return <CategoriesGridBlock {...(props as any)} tenantId={tenantId} />;

    case 'promo-banner':
      return <PromoBannerBlock {...(props as any)} tenantId={tenantId} />;

    case 'flash-sale':
      return <FlashSaleBlock {...(props as any)} tenantId={tenantId} />;

    case 'text-section':
      return <TextSectionBlock {...(props as any)} />;

    case 'image-text':
      return <ImageTextBlock {...(props as any)} tenantId={tenantId} />;

    case 'testimonials':
      return <TestimonialsBlock {...(props as any)} />;

    case 'brand-showcase':
      return <BrandShowcaseBlock {...(props as any)} />;

    case 'newsletter':
      return <NewsletterBlock {...(props as any)} />;

    case 'trust-bar':
      return <TrustBarBlock {...(props as any)} />;

    case 'raw-html':
      return <RawHtmlBlock {...(props as any)} />;

    case 'store-notice':
      return <StoreNoticeBlock {...(props as any)} />;

    case 'featured-product':
      return <FeaturedProductBlock {...(props as any)} tenantId={tenantId} />;

    case 'blog-posts':
      return <BlogPostsBlock {...(props as any)} />;

    case 'payment-icons':
      return <PaymentIconsBlock {...(props as any)} />;

    case 'social-links':
      return <SocialLinksBlock {...(props as any)} />;

    case 'footer-links':
      return <FooterLinksBlock {...(props as any)} />;

    case 'slider':
      return <SliderBlock {...(props as any)} tenantId={tenantId} />;

    case 'accordion':
      return <AccordionBlock {...(props as any)} />;

    case 'countdown':
      return <CountdownBlock {...(props as any)} />;

    case 'gallery':
      return <GalleryBlock {...(props as any)} />;

    case 'icon-box':
      return <IconBoxBlock {...(props as any)} />;

    case 'map':
      return <MapBlock {...(props as any)} />;

    case 'video-button':
      return <VideoButtonBlock {...(props as any)} />;

    case 'button-block':
      return <ButtonBlock {...(props as any)} />;

    case 'message-box':
      return <MessageBoxBlock {...(props as any)} />;

    case 'divider':
      return <DividerBlock {...(props as any)} />;

    case 'title':
      return <TitleBlock {...(props as any)} />;

    case 'image':
      return <ImageBlock {...(props as any)} tenantId={tenantId} />;

    case 'image-box':
      return <ImageBoxBlock {...(props as any)} tenantId={tenantId} />;

    case 'team-member':
      return <TeamMemberBlock {...(props as any)} />;

    case 'price-table':
      return <PriceTableBlock {...(props as any)} />;

    case 'search':
      return <SearchBlock {...(props as any)} tenantId={tenantId} />;

    case 'lightbox':
      return <LightboxBlock {...(props as any)} />;

    case 'menu':
      return <MenuBlock {...(props as any)} tenantId={tenantId} />;

    case 'instagram-feed':
      return <InstagramFeedBlock {...(props as any)} />;

    case 'lottie':
      return <LottieBlock {...(props as any)} />;

    case 'featured-items':
      return <FeaturedItemsBlock {...(props as any)} />;

    case 'portfolio':
      return <PortfolioBlock {...(props as any)} />;

    case 'product-list':
      return <ProductListBlock {...(props as any)} tenantId={tenantId} />;

    case 'product-category':
      return <ProductCategoryBlock {...(props as any)} tenantId={tenantId} />;

    case 'row':
      return <RowBlock {...(props as any)} tenantId={tenantId} />;

    case 'column':
      return <ColumnBlock {...(props as any)} tenantId={tenantId} />;

    case 'section':
      return <SectionBlock {...(props as any)} tenantId={tenantId} />;

    case 'tabs':
      return <TabsBlock {...(props as any)} tenantId={tenantId} />;

    case 'stack':
      return <StackBlock {...(props as any)} tenantId={tenantId} />;

    default:
      // Unknown type: try fallback HTML
      if (_fallback_html) {
        return <RawHtmlBlock html={_fallback_html} />;
      }
      return null;
  }
}
