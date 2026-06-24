import type { MapProps } from '@/lib/block-types';

interface Props extends MapProps {}

export default function MapBlock({
  address,
  lat,
  lng,
  height = 360,
  zoom = 15,
  marker_title,
  iframe_url,
}: Props) {
  let src = iframe_url;
  if (!src && lat !== undefined && lng !== undefined) {
    src = `https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0%3A0!2z${lat}%2C${lng}!5e0!3m2!1sen!2sus!4v1`;
  } else if (!src && address) {
    const q = encodeURIComponent(address);
    src = `https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1!2d0!3d0!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s${q}!5e0!3m2!1sen!2sus!4v1`;
  }

  return (
    <div className="max-w-7xl mx-auto px-4">
      {marker_title && <h3 className="text-lg font-bold text-gray-900 mb-3">{marker_title}</h3>}
      {address && !marker_title && <p className="text-sm text-gray-600 mb-3">{address}</p>}
      {src ? (
        <iframe
          src={src}
          width="100%"
          height={height}
          style={{ border: 0, borderRadius: '12px' }}
          allowFullScreen
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          title={marker_title || 'Store location'}
        />
      ) : (
        <div className="rounded-xl bg-gray-100 h-64 flex items-center justify-center text-gray-500 text-sm">
          No map address or coordinates provided
        </div>
      )}
    </div>
  );
}
