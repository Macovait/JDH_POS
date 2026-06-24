import type { ImageProps } from '@/lib/block-types';

interface Props extends ImageProps {
  tenantId: number;
}

export default function ImageBlock({
  src,
  alt = '',
  caption,
  link,
  width,
  height,
  rounded = false,
  shadow = false,
  align = 'center',
  lazy = true,
}: Props) {
  const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  const img = (
    <img
      src={src}
      alt={alt}
      loading={lazy ? 'lazy' : 'eager'}
      className={`inline-block max-w-full ${rounded ? 'rounded-xl' : ''} ${shadow ? 'shadow-lg' : ''}`}
      style={{ width: width ?? 'auto', height: height ?? 'auto' }}
    />
  );

  return (
    <div className={`max-w-7xl mx-auto px-4 ${alignClass[align]}`}>
      {link ? (
        <a href={link} className="inline-block hover:opacity-90 transition">
          {img}
        </a>
      ) : (
        img
      )}
      {caption && <p className="text-sm text-gray-500 mt-2">{caption}</p>}
    </div>
  );
}
