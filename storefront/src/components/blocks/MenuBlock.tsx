import type { MenuProps } from '@/lib/block-types';

interface Props extends MenuProps {
  tenantId: number;
}

export default function MenuBlock({
  items,
  orientation = 'horizontal',
  style = 'default',
  align = 'center',
}: Props) {
  const alignClass = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
  };

  const containerClass = {
    horizontal: `flex flex-wrap gap-4 ${alignClass[align]}`,
    vertical: 'flex flex-col gap-2',
  };

  const linkClass = {
    default: 'text-sm text-gray-700 hover:text-orange-500 transition',
    pills: 'text-sm px-4 py-2 rounded-full bg-gray-100 text-gray-700 hover:bg-orange-500 hover:text-white transition',
    underline: 'text-sm text-gray-700 hover:text-orange-500 border-b-2 border-transparent hover:border-orange-500 transition pb-1',
  };

  return (
    <nav className="max-w-7xl mx-auto px-4">
      <ul className={containerClass[orientation]}>
        {items.map((item, index) => (
          <li key={index} className={orientation === 'horizontal' ? 'relative group' : ''}>
            <a href={item.url} className={linkClass[style]}>
              {item.icon && <span className="mr-1.5">{item.icon}</span>}
              {item.label}
            </a>
            {item.children && item.children.length > 0 && (
              <ul className="hidden group-hover:block absolute left-0 top-full mt-1 w-48 bg-white border border-gray-100 rounded-xl shadow-lg p-2 z-20">
                {item.children.map((child, i) => (
                  <li key={i}>
                    <a href={child.url} className="block px-3 py-2 text-sm text-gray-600 hover:text-orange-500 hover:bg-orange-50 rounded-lg transition">
                      {child.label}
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </li>
        ))}
      </ul>
    </nav>
  );
}
