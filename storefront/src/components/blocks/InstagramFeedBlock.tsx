import type { InstagramFeedProps } from '@/lib/block-types';

export default function InstagramFeedBlock({
  username,
  limit = 6,
  columns = 6,
  show_username = true,
  fallback_images = [],
}: InstagramFeedProps) {
  const images = fallback_images.length > 0 ? fallback_images.slice(0, limit) : [];
  const colClass = {
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-2 md:grid-cols-4',
    5: 'grid-cols-2 md:grid-cols-5',
    6: 'grid-cols-3 md:grid-cols-6',
  };

  return (
    <div className="max-w-7xl mx-auto px-4">
      {show_username && username && (
        <div className="text-center mb-4">
          <h3 className="text-lg font-extrabold text-gray-900">@{username}</h3>
          <p className="text-xs text-gray-500">Follow us on Instagram</p>
        </div>
      )}
      {images.length > 0 ? (
        <div className={`grid gap-1 ${colClass[columns]}`}>
          {images.map((src, index) => (
            <a
              key={index}
              href={username ? `https://instagram.com/${username}` : '#'}
              target="_blank"
              rel="noopener noreferrer"
              className="relative aspect-square overflow-hidden group"
            >
              <img
                src={src}
                alt={`Instagram ${index + 1}`}
                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              />
              <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition" />
            </a>
          ))}
        </div>
      ) : (
        <div className="text-center py-8 text-sm text-gray-500 bg-gray-50 rounded-xl">
          {username ? `Connect Instagram feed for @${username}` : 'Connect Instagram feed or add fallback images'}
        </div>
      )}
    </div>
  );
}
