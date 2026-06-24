import { Star } from 'lucide-react';

interface Props { rating: number; size?: 'sm' | 'md' }

export default function StarRating({ rating, size = 'md' }: Props) {
  const px = size === 'sm' ? 12 : 16;
  return (
    <div className="flex items-center gap-0.5">
      {[1, 2, 3, 4, 5].map((i) => (
        <Star
          key={i}
          size={px}
          className={i <= Math.round(rating) ? 'text-yellow-400' : 'text-gray-200'}
          fill={i <= Math.round(rating) ? 'currentColor' : 'currentColor'}
        />
      ))}
    </div>
  );
}
