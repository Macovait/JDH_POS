import { storeApi, type Category } from '@/lib/api';
import ProductGrid from '@/components/ProductGrid';
import CategorySidebar from '@/components/CategorySidebar';
import ProductFilters from '@/components/ProductFilters';
import MobileFilterToggle from '@/components/MobileFilterToggle';
import { Suspense } from 'react';
import ProductSkeleton from '@/components/ProductSkeleton';
import Breadcrumbs from '@/components/Breadcrumbs';
import { Metadata } from 'next';
import Link from 'next/link';

interface Props {
  params: { tenant: string };
  searchParams: Record<string, string | undefined>;
}

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const tenantId = parseInt(params.tenant, 10);
  const categoryId = searchParams.category ? parseInt(searchParams.category) : undefined;
  
  let title = 'All Products';
  let description = 'Browse our collection of quality products at great prices.';
  
  if (searchParams.q) {
    title = `Search Results for "${searchParams.q}"`;
    description = `Showing products matching "${searchParams.q}". Find what you're looking for.`;
  }
  
  if (categoryId) {
    try {
      const categories = await storeApi.getCategories(tenantId);
      const category = categories.find(c => c.id === categoryId);
      if (category) {
        title = `${category.name} - Products`;
        description = `Browse our ${category.name} collection. Quality products at great prices.`;
      }
    } catch (e) {
      // Ignore
    }
  }
  
  return {
    title,
    description,
    openGraph: {
      title,
      description,
      url: `/${tenantId}/products`,
    },
  };
}

export default async function ProductsPage({ params, searchParams }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  const page = parseInt(searchParams.page ?? '1', 10);
  const categoryId = searchParams.category ? parseInt(searchParams.category) : undefined;

  // Fetch data in parallel
  const [productsData, categories] = await Promise.all([
    storeApi.getProducts(tenantId, {
      q: searchParams.q,
      category: categoryId,
      sort: searchParams.sort as 'price_asc' | 'price_desc' | 'newest' | 'popular' | undefined,
      min_price: searchParams.min ? parseFloat(searchParams.min) : undefined,
      max_price: searchParams.max ? parseFloat(searchParams.max) : undefined,
      page,
      per_page: 24,
    }).catch(() => ({ products: [], total: 0, page: 1, pages: 1 })),
    storeApi.getCategories(tenantId).catch(() => [] as Category[]),
  ]);

  const hasFilters = !!(searchParams.q || searchParams.category || searchParams.min || searchParams.max);
  const isFiltered = hasFilters || !!searchParams.sort;
  
  // Breadcrumb items
  const breadcrumbs: Array<{ label: string; url?: string; active?: boolean }> = [
    { label: 'Home', url: `/${tenantId}` },
    { label: 'Products', url: `/${tenantId}/products`, active: !isFiltered },
  ];
  
  // Add category to breadcrumbs if selected
  if (categoryId) {
    const category = categories.find(c => c.id === categoryId);
    if (category) {
      breadcrumbs.push({ label: category.name, active: true });
    }
  }
  
  // Add search to breadcrumbs if searching
  if (searchParams.q) {
    breadcrumbs.push({ label: `Search: "${searchParams.q}"`, active: true });
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-slate-900">
      {/* Header */}
      <div className="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700">
        <div className="max-w-7xl mx-auto px-4 py-4 md:py-6">
          <Breadcrumbs items={breadcrumbs} className="mb-3" />
          
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 dark:text-white">
                {searchParams.q ? (
                  <>Results for <span className="text-brand">"{searchParams.q}"</span></>
                ) : categoryId ? (
                  <> {categories.find(c => c.id === categoryId)?.name || 'Products'}</>
                ) : (
                  'All Products'
                )}
              </h1>
              <p className="text-sm text-gray-500 dark:text-slate-400 mt-0.5 flex items-center gap-2">
                <span>{productsData.total} products found</span>
                {isFiltered && (
                  <span className="text-xs bg-brand/10 text-brand px-2 py-0.5 rounded-full">
                    Filtered
                  </span>
                )}
              </p>
            </div>
            
            <div className="flex items-center gap-3">
              {hasFilters && (
                <Link
                  href={`/${tenantId}/products`}
                  className="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-gray-600 dark:text-slate-400 hover:text-brand dark:hover:text-brand transition-colors border border-gray-200 dark:border-slate-700 rounded-lg hover:border-brand/30"
                >
                  <i className="fas fa-times text-xs"></i>
                  Clear Filters
                </Link>
              )}
              <ProductFilters
                currentSort={searchParams.sort}
                tenantId={tenantId}
                searchParams={searchParams}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 py-6">
        <div className="flex flex-col lg:flex-row gap-6">
          {/* Sidebar */}
          <aside className="lg:w-64 lg:shrink-0 order-2 lg:order-1">
            <div className="sticky top-6">
              <CategorySidebar
                categories={categories}
                tenantId={tenantId}
                activeCategory={categoryId}
                className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden"
              />
              
              {/* Mobile filter toggle */}
              <MobileFilterToggle
                categories={categories}
                tenantId={tenantId}
                activeCategory={categoryId}
              />
            </div>
          </aside>

          {/* Products Grid */}
          <div className="flex-1 min-w-0 order-1 lg:order-2">
            <Suspense fallback={<ProductSkeleton count={24} />}>
              {productsData.products.length === 0 ? (
                <div className="text-center py-16 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                  <div className="text-6xl mb-4">🔍</div>
                  <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">No products found</h3>
                  <p className="text-gray-500 dark:text-slate-400 max-w-md mx-auto">
                    {hasFilters 
                      ? 'Try adjusting your filters or search terms.'
                      : 'Check back later for new arrivals.'}
                  </p>
                  {hasFilters && (
                    <Link
                      href={`/${tenantId}/products`}
                      className="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-brand text-white rounded-lg hover:bg-brand-dark transition-colors"
                    >
                      <i className="fas fa-undo-alt text-xs"></i>
                      Reset Filters
                    </Link>
                  )}
                </div>
              ) : (
                <>
                  <ProductGrid 
                    products={productsData.products} 
                    tenantId={tenantId}
                    className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4"
                  />

                  {/* Pagination */}
                  {productsData.pages > 1 && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 mt-8 pt-4 border-t border-gray-200 dark:border-slate-700">
                      <p className="text-sm text-gray-500 dark:text-slate-400">
                        Showing page {page} of {productsData.pages}
                      </p>
                      <div className="flex items-center gap-1.5 flex-wrap justify-center">
                        {/* Previous */}
                        {page > 1 && (
                          <Link
                            href={`/${tenantId}/products?${new URLSearchParams({ ...searchParams, page: String(page - 1) })}`}
                            className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-400 hover:border-brand hover:text-brand transition-colors"
                          >
                            <i className="fas fa-chevron-left text-xs"></i>
                          </Link>
                        )}

                        {/* Page numbers */}
                        {Array.from({ length: Math.min(productsData.pages, 7) }, (_, i) => {
                          let p;
                          if (productsData.pages <= 7) {
                            p = i + 1;
                          } else if (page <= 4) {
                            p = i + 1;
                          } else if (page >= productsData.pages - 3) {
                            p = productsData.pages - 6 + i;
                          } else {
                            p = page - 3 + i;
                          }
                          return p;
                        }).filter((p, i, arr) => {
                          if (i === 0 || i === arr.length - 1) return true;
                          if (arr[i] - arr[i-1] === 1) return true;
                          return false;
                        }).map((p, i, arr) => {
                          if (i > 0 && p - arr[i-1] > 1) {
                            return (
                              <span key={`ellipsis-${i}`} className="w-9 h-9 flex items-center justify-center text-sm text-gray-400">
                                …
                              </span>
                            );
                          }
                          const isActive = p === page;
                          const sp = new URLSearchParams({ ...searchParams, page: String(p) });
                          return (
                            <Link
                              key={p}
                              href={`/${tenantId}/products?${sp}`}
                              className={`w-9 h-9 flex items-center justify-center rounded-lg text-sm font-medium transition-colors ${
                                isActive
                                  ? 'bg-brand text-white shadow-lg shadow-brand/20'
                                  : 'border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-400 hover:border-brand hover:text-brand'
                              }`}
                            >
                              {p}
                            </Link>
                          );
                        })}

                        {/* Next */}
                        {page < productsData.pages && (
                          <Link
                            href={`/${tenantId}/products?${new URLSearchParams({ ...searchParams, page: String(page + 1) })}`}
                            className="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-400 hover:border-brand hover:text-brand transition-colors"
                          >
                            <i className="fas fa-chevron-right text-xs"></i>
                          </Link>
                        )}
                      </div>
                    </div>
                  )}
                </>
              )}
            </Suspense>
          </div>
        </div>
      </div>
    </div>
  );
}