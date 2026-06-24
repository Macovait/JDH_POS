'use client';

import { Category } from '@/lib/api';
import CategorySidebar from './CategorySidebar';

interface Props {
  categories: Category[];
  tenantId: number;
  activeCategory?: number;
}

export default function MobileFilterToggle({ categories, tenantId, activeCategory }: Props) {
  return (
    <div className="lg:hidden mt-4">
      <button
        className="w-full py-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors flex items-center justify-center gap-2"
        onClick={() => document.getElementById('mobileFilters')?.classList.toggle('hidden')}
      >
        <i className="fas fa-sliders-h"></i>
        Show Filters
      </button>
      <div id="mobileFilters" className="hidden mt-2">
        <CategorySidebar
          categories={categories}
          tenantId={tenantId}
          activeCategory={activeCategory}
          mobile
        />
      </div>
    </div>
  );
}
