'use client';
import { type ChangeEvent } from 'react';
interface Props {
  currentSort?: string;
  tenantId: number;
  searchParams: Record<string, string | undefined>;
}

const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest' },
  { value: 'popular', label: 'Popular' },
  { value: 'price_asc', label: 'Price: Low → High' },
  { value: 'price_desc', label: 'Price: High → Low' },
];

export default function ProductFilters({ currentSort, tenantId, searchParams }: Props) {
  const handleSort = (e: ChangeEvent<HTMLSelectElement>) => {
    const sp = new URLSearchParams();
    Object.entries(searchParams).forEach(([k, v]) => { if (v && k !== 'sort' && k !== 'page') sp.set(k, v); });
    if (e.target.value) sp.set('sort', e.target.value);
    window.location.href = `/${tenantId}/products?${sp}`;
  };

  return (
    <select
      aria-label="Sort products"
      value={currentSort ?? ''}
      onChange={handleSort}
      className="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-brand/40 bg-white"
    >
      <option value="">Sort by</option>
      {SORT_OPTIONS.map((o) => (
        <option key={o.value} value={o.value}>{o.label}</option>
      ))}
    </select>
  );
}
