import Link from 'next/link';

interface BreadcrumbItem {
  label: string;
  url?: string;
  active?: boolean;
}

interface Props {
  items: BreadcrumbItem[];
  className?: string;
}

export default function Breadcrumbs({ items, className = '' }: Props) {
  return (
    <nav className={`flex items-center gap-2 text-sm ${className}`} aria-label="Breadcrumb">
      {items.map((item, i) => (
        <span key={`${item.label}-${i}`} className="flex items-center gap-2">
          {i > 0 && <i className="fas fa-chevron-right text-[10px] text-gray-300" />}
          {item.active ? (
            <span className="text-gray-500 dark:text-slate-400">{item.label}</span>
          ) : (
            <Link href={item.url || '#'} className="text-gray-600 dark:text-slate-400 hover:text-brand transition-colors">
              {item.label}
            </Link>
          )}
        </span>
      ))}
    </nav>
  );
}
