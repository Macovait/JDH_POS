/**
 * Block Types — Typed definitions for the modular CMS block system.
 * Each block has a `type` and `props` (structured JSON).
 */

export type BlockType =
  | 'hero-banner'
  | 'product-grid'
  | 'categories-grid'
  | 'promo-banner'
  | 'flash-sale'
  | 'text-section'
  | 'image-text'
  | 'testimonials'
  | 'brand-showcase'
  | 'newsletter'
  | 'trust-bar'
  | 'raw-html'
  | 'store-notice'
  | 'featured-product'
  | 'blog-posts'
  | 'payment-icons'
  | 'social-links'
  | 'footer-links'
  | 'slider'
  | 'accordion'
  | 'countdown'
  | 'gallery'
  | 'icon-box'
  | 'map'
  | 'video-button'
  | 'button-block'
  | 'message-box'
  | 'divider'
  | 'row'
  | 'column'
  | 'section'
  | 'tabs'
  | 'title'
  | 'image'
  | 'image-box'
  | 'team-member'
  | 'price-table'
  | 'search'
  | 'lightbox'
  | 'menu'
  | 'instagram-feed'
  | 'stack'
  | 'lottie'
  | 'featured-items'
  | 'portfolio'
  | 'product-list'
  | 'product-category';

export interface BaseBlock {
  id: number;
  name: string;
  type: BlockType;
  bg_color?: string;
  text_color?: string;
  padding?: string;
  section_class?: string;
  display_order?: number;
  is_active?: boolean;
  _fallback_html?: string;
}

// ── Hero Banner ──────────────────────────────────────────
export interface HeroBannerProps {
  slides: {
    image: string;
    title: string;
    subtitle?: string;
    cta_text?: string;
    cta_link?: string;
    overlay?: boolean;
  }[];
  autoplay?: boolean;
  interval?: number;
}

// ── Product Grid ──────────────────────────────────────────
export interface ProductGridProps {
  title: string;
  subtitle?: string;
  product_ids?: number[];
  category_id?: number;
  sort?: 'popular' | 'newest' | 'price_asc' | 'price_desc' | 'featured';
  limit?: number;
  columns?: 2 | 3 | 4 | 5;
  show_add_to_cart?: boolean;
  see_all_link?: string;
}

// ── Categories Grid ───────────────────────────────────────
export interface CategoriesGridProps {
  title: string;
  category_ids?: number[];
  limit?: number;
  layout?: 'grid' | 'masonry' | 'carousel';
  show_product_count?: boolean;
}

// ── Promo Banner ─────────────────────────────────────────
export interface PromoBannerProps {
  image: string;
  title?: string;
  subtitle?: string;
  cta_text?: string;
  cta_link?: string;
  align?: 'left' | 'center' | 'right';
  height?: 'sm' | 'md' | 'lg';
  overlay_color?: string;
}

// ── Flash Sale ──────────────────────────────────────────
export interface FlashSaleProps {
  title?: string;
  end_time?: string; // ISO datetime
  product_ids?: number[];
  limit?: number;
  show_timer?: boolean;
}

// ── Text Section ────────────────────────────────────────
export interface TextSectionProps {
  heading: string;
  body: string;
  align?: 'left' | 'center' | 'right';
  heading_size?: 'sm' | 'md' | 'lg' | 'xl';
  cta_text?: string;
  cta_link?: string;
}

// ── Image + Text ───────────────────────────────────────
export interface ImageTextProps {
  image: string;
  image_position?: 'left' | 'right';
  heading: string;
  body: string;
  cta_text?: string;
  cta_link?: string;
  image_ratio?: 'square' | 'video' | 'portrait';
}

// ── Testimonials ────────────────────────────────────────
export interface TestimonialsProps {
  title?: string;
  testimonials: {
    name: string;
    avatar?: string;
    rating: number;
    text: string;
    date?: string;
  }[];
  layout?: 'grid' | 'carousel';
}

// ── Brand Showcase ──────────────────────────────────────
export interface BrandShowcaseProps {
  title?: string;
  brands: {
    name: string;
    logo?: string;
    link?: string;
  }[];
  layout?: 'grid' | 'marquee';
}

// ── Newsletter ──────────────────────────────────────────
export interface NewsletterProps {
  title?: string;
  description?: string;
  placeholder?: string;
  button_text?: string;
  success_message?: string;
}

// ── Trust Bar ────────────────────────────────────────────
export interface TrustBarProps {
  items: {
    icon: string;
    title: string;
    description?: string;
  }[];
}

// ── Raw HTML (backward compat) ──────────────────────────
export interface RawHtmlProps {
  html: string;
}

// ── Store Notice ───────────────────────────────────────
export interface StoreNoticeProps {
  message: string;
  type?: 'info' | 'success' | 'warning' | 'error';
  dismissible?: boolean;
  link_text?: string;
  link_url?: string;
}

// ── Featured Product ────────────────────────────────────
export interface FeaturedProductProps {
  product_id: number;
  badge?: string;
  show_description?: boolean;
  show_reviews?: boolean;
  image_position?: 'left' | 'right';
}

// ── Blog Posts ──────────────────────────────────────────
export interface BlogPostsProps {
  title?: string;
  post_ids?: number[];
  category_id?: number;
  limit?: number;
  columns?: 2 | 3 | 4;
  show_date?: boolean;
  show_excerpt?: boolean;
  show_read_more?: boolean;
}

// ── Payment Icons ──────────────────────────────────────
export interface PaymentIconsProps {
  title?: string;
  icons: {
    name: string;
    src?: string;
    alt?: string;
  }[];
  layout?: 'row' | 'grid';
}

// ── Social Links ────────────────────────────────────────
export interface SocialLinksProps {
  title?: string;
  links: {
    platform: string;
    url: string;
    label?: string;
  }[];
  style?: 'icon-only' | 'icon-with-label' | 'pill';
  align?: 'left' | 'center' | 'right';
}

// ── Footer Links ────────────────────────────────────────
export interface FooterLinksProps {
  columns: {
    title: string;
    links: {
      label: string;
      url: string;
    }[];
  }[];
  bottom_text?: string;
  show_payment_icons?: boolean;
  show_social_links?: boolean;
}

// ── Slider / Carousel ──────────────────────────────────
export interface SliderProps {
  items: {
    image?: string;
    title?: string;
    subtitle?: string;
    link?: string;
  }[];
  type?: 'image' | 'product' | 'testimonial';
  autoplay?: boolean;
  interval?: number;
  show_arrows?: boolean;
  show_dots?: boolean;
  slides_per_view?: 1 | 2 | 3 | 4;
}

// ── Accordion / FAQ ────────────────────────────────────
export interface AccordionProps {
  title?: string;
  items: {
    question: string;
    answer: string;
  }[];
  allow_multiple?: boolean;
  style?: 'default' | 'bordered' | 'flush';
}

// ── Countdown Timer ────────────────────────────────────
export interface CountdownProps {
  target: string; // ISO datetime
  title?: string;
  subtitle?: string;
  show_labels?: boolean;
  labels?: {
    days?: string;
    hours?: string;
    minutes?: string;
    seconds?: string;
  };
  expired_text?: string;
  link?: string;
}

// ── Image Gallery ──────────────────────────────────────
export interface GalleryProps {
  images: {
    src: string;
    alt?: string;
    caption?: string;
    link?: string;
  }[];
  columns?: 2 | 3 | 4 | 5;
  gap?: 'sm' | 'md' | 'lg';
  lightbox?: boolean;
  aspect?: 'square' | 'video' | 'portrait' | 'auto';
}

// ── Icon Box / Feature Highlight ───────────────────────
export interface IconBoxProps {
  items: {
    icon: string;
    title: string;
    description?: string;
    link?: string;
  }[];
  layout?: 'grid' | 'row' | 'columns';
  columns?: 2 | 3 | 4;
  icon_style?: 'outline' | 'filled' | 'minimal';
  align?: 'left' | 'center';
}

// ── Map / Store Locator ────────────────────────────────
export interface MapProps {
  address?: string;
  lat?: number;
  lng?: number;
  height?: number;
  zoom?: number;
  marker_title?: string;
  iframe_url?: string;
  api_key?: string;
}

// ── Video Button / Lightbox ────────────────────────────
export interface VideoButtonProps {
  video_url: string;
  title?: string;
  button_text?: string;
  button_size?: 'sm' | 'md' | 'lg';
  autoplay?: boolean;
  thumbnail?: string;
  align?: 'left' | 'center' | 'right';
}

// ── Button / CTA ───────────────────────────────────────
export interface ButtonBlockProps {
  text: string;
  link: string;
  style?: 'primary' | 'secondary' | 'outline' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  width?: 'auto' | 'full';
  align?: 'left' | 'center' | 'right';
  icon?: string;
  target?: '_blank' | '_self';
  rel?: string;
}

// ── Message Box / Alert ────────────────────────────────
export interface MessageBoxProps {
  title?: string;
  message: string;
  type?: 'info' | 'success' | 'warning' | 'error';
  dismissible?: boolean;
  icon?: string;
  link_text?: string;
  link_url?: string;
}

// ── Divider / Separator ────────────────────────────────
export interface DividerProps {
  style?: 'solid' | 'dashed' | 'dotted' | 'gradient';
  width?: 'full' | 'short' | 'medium';
  align?: 'left' | 'center' | 'right';
  label?: string;
  spacing?: 'sm' | 'md' | 'lg';
}

// ── Layout: Row ──────────────────────────────────────────
export interface RowProps {
  columns: TypedBlock[];
  gap?: 'sm' | 'md' | 'lg';
  align?: 'start' | 'center' | 'end' | 'stretch';
  justify?: 'start' | 'center' | 'end' | 'between' | 'around';
  wrap?: boolean;
  class_name?: string;
}

// ── Layout: Column ─────────────────────────────────────
export interface ColumnProps {
  width?: '1/2' | '1/3' | '2/3' | '1/4' | '3/4' | '1/5' | '2/5' | '3/5' | '4/5' | 'auto' | 'full';
  blocks: TypedBlock[];
  class_name?: string;
}

// ── Layout: Section ──────────────────────────────────────
export interface SectionProps {
  blocks: TypedBlock[];
  container?: 'full' | 'boxed' | 'narrow';
  bg_image?: string;
  bg_overlay?: string;
  min_height?: string;
  class_name?: string;
}

// ── Tabs ─────────────────────────────────────────────────
export interface TabsProps {
  tabs: {
    label: string;
    icon?: string;
    blocks: TypedBlock[];
  }[];
  orientation?: 'horizontal' | 'vertical';
  style?: 'default' | 'pills' | 'underline';
}

// ── Title / Heading ──────────────────────────────────────
export interface TitleProps {
  text: string;
  tag?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'span';
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  align?: 'left' | 'center' | 'right';
  color?: string;
  class_name?: string;
  sub_title?: string;
  divider?: boolean;
}

// ── Image ────────────────────────────────────────────────
export interface ImageProps {
  src: string;
  alt?: string;
  caption?: string;
  link?: string;
  width?: number | string;
  height?: number | string;
  rounded?: boolean;
  shadow?: boolean;
  align?: 'left' | 'center' | 'right';
  lazy?: boolean;
}

// ── Image Box ────────────────────────────────────────────
export interface ImageBoxProps {
  image: string;
  title?: string;
  description?: string;
  link?: string;
  hover_text?: string;
  overlay?: boolean;
  ratio?: 'square' | 'video' | 'portrait' | 'auto';
  height?: 'sm' | 'md' | 'lg';
  align?: 'left' | 'center' | 'right';
}

// ── Team Member ──────────────────────────────────────────
export interface TeamMemberProps {
  members: {
    name: string;
    role?: string;
    bio?: string;
    image?: string;
    social?: { platform: string; url: string }[];
  }[];
  columns?: 2 | 3 | 4;
  layout?: 'grid' | 'list';
}

// ── Price Table ──────────────────────────────────────────
export interface PriceTableProps {
  tables: {
    title: string;
    price: string;
    period?: string;
    features: string[];
    button_text?: string;
    button_link?: string;
    highlighted?: boolean;
    badge?: string;
  }[];
  columns?: 2 | 3 | 4;
  align?: 'top' | 'bottom';
}

// ── Search ───────────────────────────────────────────────
export interface SearchProps {
  placeholder?: string;
  button_text?: string;
  scope?: 'products' | 'posts' | 'all';
  layout?: 'inline' | 'compact';
  show_categories?: boolean;
  class_name?: string;
}

// ── Lightbox ─────────────────────────────────────────────
export interface LightboxProps {
  trigger_text?: string;
  trigger_image?: string;
  content_type: 'image' | 'video' | 'html';
  content: string;
  caption?: string;
  button_style?: 'primary' | 'secondary' | 'link';
  align?: 'left' | 'center' | 'right';
}

// ── Menu ─────────────────────────────────────────────────
export interface MenuProps {
  items: {
    label: string;
    url: string;
    icon?: string;
    children?: { label: string; url: string }[];
  }[];
  orientation?: 'horizontal' | 'vertical';
  style?: 'default' | 'pills' | 'underline';
  align?: 'left' | 'center' | 'right';
}

// ── Instagram Feed ───────────────────────────────────────
export interface InstagramFeedProps {
  username?: string;
  access_token?: string;
  limit?: number;
  columns?: 2 | 3 | 4 | 5 | 6;
  show_username?: boolean;
  fallback_images?: string[];
}

// ── Stack / Layered Layout ───────────────────────────────
export interface StackProps {
  items: TypedBlock[];
  direction?: 'vertical' | 'horizontal';
  gap?: 'sm' | 'md' | 'lg';
  align?: 'start' | 'center' | 'end' | 'stretch';
  class_name?: string;
}

// ── Lottie Animation ───────────────────────────────────
export interface LottieProps {
  src: string;
  loop?: boolean;
  autoplay?: boolean;
  width?: number | string;
  height?: number | string;
  speed?: number;
  align?: 'left' | 'center' | 'right';
}

// ── Featured Items ─────────────────────────────────────
export interface FeaturedItemsProps {
  title?: string;
  items: {
    image?: string;
    title: string;
    subtitle?: string;
    link?: string;
    badge?: string;
  }[];
  layout?: 'grid' | 'slider' | 'list';
  columns?: 2 | 3 | 4 | 5;
  gap?: 'sm' | 'md' | 'lg';
  show_arrows?: boolean;
  show_dots?: boolean;
}

// ── Portfolio ────────────────────────────────────────────
export interface PortfolioProps {
  projects: {
    image: string;
    title: string;
    category?: string;
    link?: string;
    description?: string;
  }[];
  columns?: 2 | 3 | 4;
  filter?: boolean;
  gap?: 'sm' | 'md' | 'lg';
  aspect?: 'square' | 'video' | 'portrait';
}

// ── Product List ─────────────────────────────────────────
export interface ProductListProps {
  title?: string;
  product_ids?: number[];
  category_ids?: number[];
  sort?: 'popular' | 'newest' | 'price_asc' | 'price_desc' | 'featured';
  limit?: number;
  show_add_to_cart?: boolean;
  show_rating?: boolean;
  style?: 'compact' | 'detailed' | 'mini';
}

// ── Product Category ─────────────────────────────────────
export interface ProductCategoryProps {
  category_ids?: number[];
  layout?: 'grid' | 'carousel' | 'list';
  columns?: 2 | 3 | 4;
  show_count?: boolean;
  show_image?: boolean;
  limit?: number;
}

// Union type for all block props
export type BlockProps =
  | HeroBannerProps
  | ProductGridProps
  | CategoriesGridProps
  | PromoBannerProps
  | FlashSaleProps
  | TextSectionProps
  | ImageTextProps
  | TestimonialsProps
  | BrandShowcaseProps
  | NewsletterProps
  | TrustBarProps
  | RawHtmlProps
  | StoreNoticeProps
  | FeaturedProductProps
  | BlogPostsProps
  | PaymentIconsProps
  | SocialLinksProps
  | FooterLinksProps
  | SliderProps
  | AccordionProps
  | CountdownProps
  | GalleryProps
  | IconBoxProps
  | MapProps
  | VideoButtonProps
  | ButtonBlockProps
  | MessageBoxProps
  | DividerProps
  | RowProps
  | ColumnProps
  | SectionProps
  | TabsProps
  | TitleProps
  | ImageProps
  | ImageBoxProps
  | TeamMemberProps
  | PriceTableProps
  | SearchProps
  | LightboxProps
  | MenuProps
  | InstagramFeedProps
  | StackProps
  | LottieProps
  | FeaturedItemsProps
  | PortfolioProps
  | ProductListProps
  | ProductCategoryProps;

// Full block with props
export interface TypedBlock extends BaseBlock {
  props: BlockProps;
}
