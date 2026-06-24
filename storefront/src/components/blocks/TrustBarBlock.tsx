import type { TrustBarProps } from '@/lib/block-types';

interface Props extends TrustBarProps {}

const iconMap: Record<string, string> = {
  'truck': '🚚',
  'shield': '🛡️',
  'refresh': '↩️',
  'support': '💬',
  'lock': '🔒',
  'star': '⭐',
  'check': '✅',
  'heart': '❤️',
};

export default function TrustBarBlock({ items }: Props) {
  if (!items || items.length === 0) return null;

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {items.map((item, i) => (
          <div key={i} className="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4">
            <span className="text-2xl">{iconMap[item.icon] || item.icon}</span>
            <div>
              <p className="text-sm font-bold text-gray-800">{item.title}</p>
              {item.description && <p className="text-[11px] text-gray-500">{item.description}</p>}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
