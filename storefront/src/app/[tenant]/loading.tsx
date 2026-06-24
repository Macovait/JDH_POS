export default function TenantLoading() {
  return (
    <main>
      {/* Hero skeleton */}
      <div className="max-w-7xl mx-auto px-4 pt-4 pb-2">
        <div className="flex gap-4">
          {/* Sidebar skeleton */}
          <aside className="hidden lg:block w-52 shrink-0">
            <div className="bg-white rounded-2xl border border-gray-100 h-[320px] animate-pulse">
              <div className="h-10 rounded-t-2xl bg-gray-200" />
              <div className="p-3 space-y-2">
                {Array.from({ length: 8 }).map((_, i) => (
                  <div key={i} className="h-8 bg-gray-100 rounded-lg" />
                ))}
              </div>
            </div>
          </aside>
          {/* Banner skeleton */}
          <div className="flex-1 min-w-0">
            <div className="w-full rounded-2xl bg-gray-200 animate-pulse" style={{ minHeight: 280 }} />
          </div>
        </div>
      </div>

      {/* Trust bar skeleton */}
      <div className="bg-white border-b border-gray-100">
        <div className="max-w-7xl mx-auto px-4 py-2.5">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-lg bg-gray-200 animate-pulse" />
                <div className="flex-1 space-y-1">
                  <div className="h-3 bg-gray-200 rounded animate-pulse w-20" />
                  <div className="h-2 bg-gray-100 rounded animate-pulse w-28 hidden sm:block" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Categories skeleton */}
      <div className="max-w-7xl mx-auto px-4 mt-5">
        <div className="h-5 bg-gray-200 rounded animate-pulse w-40 mb-4" />
        <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex flex-col items-center gap-2 bg-white border border-gray-100 rounded-2xl p-3 animate-pulse">
              <div className="w-12 h-12 rounded-xl bg-gray-200" />
              <div className="h-3 bg-gray-200 rounded w-16" />
            </div>
          ))}
        </div>
      </div>

      {/* Product rows skeleton */}
      <div className="max-w-7xl mx-auto px-4 mt-8 space-y-8 pb-12">
        {[0, 1, 2].map((section) => (
          <div key={section}>
            <div className="flex items-center justify-between mb-3">
              <div className="h-5 bg-gray-200 rounded animate-pulse w-32" />
              <div className="h-4 bg-gray-100 rounded animate-pulse w-16" />
            </div>
            <div className="flex gap-3 overflow-hidden">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="w-44 shrink-0 bg-white rounded-2xl border border-gray-100 overflow-hidden animate-pulse">
                  <div className="aspect-square bg-gray-200" />
                  <div className="p-2.5 space-y-2">
                    <div className="h-3 bg-gray-200 rounded w-full" />
                    <div className="h-3 bg-gray-200 rounded w-3/4" />
                    <div className="h-4 bg-gray-200 rounded w-16" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </main>
  );
}
