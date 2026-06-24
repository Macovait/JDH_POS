export default function ProductSkeleton({ count = 8 }: { count?: number }) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
          <div className="bg-gray-100 dark:bg-slate-700 animate-pulse" style={{ paddingTop: '75%' }} />
          <div className="p-3 space-y-3">
            <div className="h-4 bg-gray-100 dark:bg-slate-700 rounded animate-pulse w-3/4" />
            <div className="h-3 bg-gray-100 dark:bg-slate-700 rounded animate-pulse w-1/2" />
            <div className="h-5 bg-gray-100 dark:bg-slate-700 rounded animate-pulse w-1/3" />
            <div className="h-8 bg-gray-100 dark:bg-slate-700 rounded animate-pulse" />
          </div>
        </div>
      ))}
    </div>
  );
}
