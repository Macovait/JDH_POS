import { AlertTriangle, ShoppingBag, Tag, ImageOff } from 'lucide-react';

interface Props {
  type: 'error' | 'empty';
  title: string;
  message?: string;
  icon?: 'alert' | 'bag' | 'tag' | 'image';
}

const icons = {
  alert: AlertTriangle,
  bag: ShoppingBag,
  tag: Tag,
  image: ImageOff,
};

export default function EmptyState({ type, title, message, icon = 'bag' }: Props) {
  const Icon = icons[icon];
  return (
    <div className="flex flex-col items-center justify-center py-8 px-4 text-center">
      <div className={`w-12 h-12 rounded-full flex items-center justify-center mb-3 ${
        type === 'error' ? 'bg-red-50 text-red-400' : 'bg-gray-100 text-gray-400'
      }`}>
        <Icon size={20} />
      </div>
      <p className="text-sm font-semibold text-gray-700">{title}</p>
      {message && <p className="text-xs text-gray-400 mt-1 max-w-xs">{message}</p>}
    </div>
  );
}
