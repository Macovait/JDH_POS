import { storeApi } from '@/lib/api';
import HeroBanners from '@/components/HeroBanners';
import MegaMenu from '@/components/MegaMenu';
import TopBlockIcons from '@/components/TopBlockIcons';
import ProductRow from '@/components/ProductRow';
import FlashSaleStrip from '@/components/FlashSaleStrip';
import BrandShowcase from '@/components/BrandShowcase';
import PromoBannerGrid from '@/components/PromoBannerGrid';
import DealSpotlight from '@/components/DealSpotlight';
import NewsletterSignup from '@/components/NewsletterSignup';
import RecentlyViewed from '@/components/RecentlyViewed';
import ContentBlocks from '@/components/ContentBlocks';
import EmptyState from '@/components/EmptyState';

interface Props { params: { tenant: string } }

export default async function StorefrontHome({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);

  const [settings, banners, categories, typedBlocks, topSelling, newArrivals, onSale, featured] = await Promise.all([
    storeApi.getSettings(tenantId).catch(() => null),
    storeApi.getBanners(tenantId).catch(() => []),
    storeApi.getCategories(tenantId).catch(() => []),
    storeApi.getTypedBlocks(tenantId).catch(() => []),
    storeApi.getProducts(tenantId, { sort: 'popular', per_page: 12 }).catch(() => ({ products: [], total: 0, page: 1, pages: 1 })),
    storeApi.getProducts(tenantId, { sort: 'newest', per_page: 12 }).catch(() => ({ products: [], total: 0, page: 1, pages: 1 })),
    storeApi.getProducts(tenantId, { sort: 'price_asc', per_page: 12 }).catch(() => ({ products: [], total: 0, page: 1, pages: 1 })),
    storeApi.getProducts(tenantId, { featured: 1, per_page: 12 }).catch(() => ({ products: [], total: 0, page: 1, pages: 1 })),
  ]);

  const showDealSpotlight = settings?.show_deal_spotlight !== '0';
  const showNewsletter = settings?.show_newsletter !== '0';
  const showRecentlyViewed = settings?.show_recently_viewed !== '0';
  const dealProductId = settings?.deal_of_the_day_product_id ? parseInt(settings.deal_of_the_day_product_id, 10) : 0;

  return (
    <main>
      {/* Jumia-style hero: sidebar + carousel + right promo */}
      <div className="max-w-7xl mx-auto px-4 pt-4 pb-2">
        <div className="flex gap-3">
          {/* Mega Menu Sidebar */}
          <aside className="hidden lg:block w-56 shrink-0">
            <MegaMenu categories={categories} tenantId={tenantId} />
          </aside>

          {/* Center: Carousel + Top Blocks */}
          <div className="flex-1 min-w-0 flex flex-col">
            <HeroBanners banners={banners} tenantId={tenantId} />
            <TopBlockIcons whatsapp={settings?.whatsapp_number ?? null} />
          </div>

          {/* Right: Promo card (uses 2nd banner if available) */}
          <aside className="hidden xl:block w-56 shrink-0">
            {banners[1]?.image_url ? (
              <a href={banners[1].link_url || `/${tenantId}/products`} className="block h-full rounded-xl overflow-hidden border border-gray-100 hover:shadow-md transition relative">
                <img src={banners[1].image_url} alt={banners[1].title ?? ''} className="w-full h-full object-cover" loading="lazy" />
                {banners[1].title && (
                  <div className="absolute bottom-0 left-0 right-0 p-3 bg-gradient-to-t from-black/60 to-transparent">
                    <p className="text-white text-xs font-bold">{banners[1].title}</p>
                  </div>
                )}
              </a>
            ) : (
              <div className="h-full rounded-xl bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-100 flex flex-col items-center justify-center p-5 text-center">
                <span className="text-3xl mb-2">🎁</span>
                <p className="text-sm font-bold text-gray-800">Flash Sale</p>
                <p className="text-[11px] text-gray-500 mt-1">Check out our daily deals</p>
                <a href={`/${tenantId}/products`} className="mt-3 text-[11px] font-bold text-orange-500 bg-white px-3 py-1.5 rounded-lg border border-orange-200 hover:bg-orange-50 transition">Browse Now</a>
              </div>
            )}
          </aside>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 mt-6 space-y-8 pb-12">
        {/* Category icon grid — moved up for discovery */}
        {categories.length > 0 ? (
          <section id="categories">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-extrabold text-gray-900">🗂️ Shop by Category</h2>
              <a href={`/${tenantId}/products`}
                className="text-sm font-semibold hover:underline"
                style={{ color: 'var(--brand-color)' }}>See All</a>
            </div>
            <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
              {categories.slice(0, 12).map((cat) => (
                <a key={cat.id} href={`/${tenantId}/products?category=${cat.id}`}
                  className="flex flex-col items-center gap-2 bg-white border border-gray-100 rounded-2xl p-3 hover:shadow-md hover:border-orange-200 transition group">
                  {cat.image
                    ? <img src={cat.image} alt={cat.name} className="w-12 h-12 object-contain rounded-xl" />
                    : <div className="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-2xl">🛍️</div>}
                  <span className="text-xs font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-500 transition">
                    {cat.name}
                  </span>
                </a>
              ))}
            </div>
          </section>
        ) : (
          <EmptyState type="empty" title="No categories yet" message="Check back soon for new collections." icon="tag" />
        )}

        {/* Popular Collections (brand-style) */}
        {categories.length > 0 && <BrandShowcase categories={categories} tenantId={tenantId} />}

        {/* Extra promo banner grid (uses banners 2+) */}
        {banners.length > 2 && <PromoBannerGrid banners={banners} tenantId={tenantId} />}

        {/* Flash Sale */}
        {onSale.products.filter(p => p.has_discount).length > 0 ? (
          <FlashSaleStrip products={onSale.products} tenantId={tenantId} />
        ) : onSale.products.length === 0 ? (
          <EmptyState type="empty" title="No flash deals right now" message="Watch this space for upcoming sales." icon="tag" />
        ) : null}

        {/* Featured / Premium row */}
        {featured.products.length > 0 ? (
          <ProductRow
            title="Featured For You"
            emoji="⭐"
            products={featured.products}
            tenantId={tenantId}
            seeAllHref={`/${tenantId}/products?featured=1`}
            accentBar
          />
        ) : null}

        {/* Top Selling row */}
        {topSelling.products.length > 0 ? (
          <ProductRow
            title="Top Selling"
            emoji="🔥"
            products={topSelling.products}
            tenantId={tenantId}
            seeAllHref={`/${tenantId}/products?sort=popular`}
            accentBar
          />
        ) : (
          <EmptyState type="empty" title="No top sellers yet" message="Be the first to shop our bestsellers." icon="bag" />
        )}

        {/* Mid promo banner */}
        {banners.length > 1 && banners[1]?.image_url && (
          <div className="rounded-2xl overflow-hidden h-32 md:h-44 relative bg-gray-100">
            <img src={banners[1].image_url} alt={banners[1].title ?? ''} loading="lazy"
              className="w-full h-full object-cover" />
            {banners[1].title && (
              <div className="absolute inset-0 flex items-center px-8 bg-black/30">
                <div className="text-white">
                  <p className="text-xl md:text-3xl font-extrabold drop-shadow">{banners[1].title}</p>
                  {banners[1].subtitle && <p className="text-sm opacity-90 mt-1">{banners[1].subtitle}</p>}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Deal of the Day spotlight */}
        <div data-deal-spotlight>
        {showDealSpotlight && (() => {
          let deal = null;
          if (dealProductId > 0) {
            deal = onSale.products.find(p => p.id === dealProductId);
          }
          if (!deal) {
            deal = onSale.products.find(p => p.has_discount);
          }
          return deal ? (
            <DealSpotlight product={deal} tenantId={tenantId} />
          ) : null;
        })()}
        </div>

        {/* New Arrivals row */}
        {newArrivals.products.length > 0 ? (
          <ProductRow
            title="New Arrivals"
            emoji="✨"
            products={newArrivals.products}
            tenantId={tenantId}
            seeAllHref={`/${tenantId}/products?sort=newest`}
            accentBar
          />
        ) : (
          <EmptyState type="empty" title="No new arrivals yet" message="Stay tuned for fresh products." icon="bag" />
        )}

        {/* Custom Content Blocks (Page Builder) */}
        {typedBlocks.length > 0 && <ContentBlocks blocks={typedBlocks} tenantId={tenantId} />}

        {/* Recently Viewed (client-side) */}
        <div data-recently-viewed>
        {showRecentlyViewed && <RecentlyViewed tenantId={tenantId} />}
        </div>

        {/* Newsletter */}
        <div data-newsletter>
        {showNewsletter && <NewsletterSignup />}
        </div>
      </div>
    </main>
  );
}
