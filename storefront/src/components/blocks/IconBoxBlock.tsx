import type { IconBoxProps } from '@/lib/block-types';

interface Props extends IconBoxProps {}

const emojiMap: Record<string, string> = {
  truck: '🚚',
  shield: '🛡️',
  refresh: '↩️',
  support: '💬',
  lock: '🔒',
  star: '⭐',
  check: '✅',
  heart: '❤️',
  gift: '🎁',
  clock: '⏰',
  phone: '📞',
  email: '📧',
  location: '📍',
  rocket: '🚀',
  tag: '🏷️',
  award: '🏆',
  smile: '😊',
  box: '📦',
  leaf: '🍃',
  fire: '🔥',
  bell: '🔔',
  cart: '🛒',
  search: '🔍',
  user: '👤',
  home: '🏠',
  settings: '⚙️',
  info: 'ℹ️',
  warning: '⚠️',
  success: '✅',
  error: '❌',
};

export default function IconBoxBlock({
  items,
  layout = 'grid',
  columns = 3,
  icon_style = 'filled',
  align = 'center',
}: Props) {
  if (!items || items.length === 0) return null;

  const gridClass =
    columns === 2
      ? 'grid-cols-1 md:grid-cols-2'
      : columns === 4
      ? 'grid-cols-2 md:grid-cols-4'
      : 'grid-cols-1 md:grid-cols-3';

  const containerClass = layout === 'row' ? 'flex flex-wrap justify-center gap-4' : `grid ${gridClass} gap-4`;
  const alignClass = align === 'left' ? 'text-left' : 'text-center';

  const iconBgClass =
    icon_style === 'outline'
      ? 'bg-transparent border-2 border-orange-100'
      : icon_style === 'minimal'
      ? 'bg-transparent'
      : 'bg-orange-50';

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={containerClass}>
        {items.map((item, i) => (
          <div
            key={i}
            className={`flex ${layout === 'row' ? 'flex-row items-center gap-3' : 'flex-col items-center'} ${alignClass} p-4 rounded-xl bg-white border border-gray-100`}
          >
            <div
              className={`w-12 h-12 rounded-full flex items-center justify-center text-2xl shrink-0 ${iconBgClass}`}
            >
              {emojiMap[item.icon] || item.icon}
            </div>
            <div className={layout === 'row' ? '' : 'mt-3'}>
              <h3 className="font-bold text-gray-900 text-sm">{item.title}</h3>
              {item.description && <p className="text-xs text-gray-500 mt-1 leading-relaxed">{item.description}</p>}
              {item.link && (
                <a href={item.link} className="text-xs font-semibold mt-2 inline-block" style={{ color: 'var(--brand-color)' }}>
                  Learn more
                </a>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
