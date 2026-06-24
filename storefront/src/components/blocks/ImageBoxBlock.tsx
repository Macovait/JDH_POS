import type { ImageBoxProps } from '@/lib/block-types';

interface Props extends ImageBoxProps {
  tenantId: number;
}

export default function ImageBoxBlock({
  image,
  title,
  description,
  link,
  hover_text,
  overlay = true,
  ratio = 'video',
  height = 'md',
  align = 'center',
}: Props) {
  const ratioClass = {
    square: 'aspect-square',
    video: 'aspect-video',
    portrait: 'aspect-[3/4]',
    auto: 'h-auto',
  };

  const heightClass = {
    sm: 'min-h-[180px]',
    md: 'min-h-[260px]',
    lg: 'min-h-[360px]',
  };

  const alignClass = {
    left: 'items-start text-left',
    center: 'items-center text-center',
    right: 'items-end text-right',
  };

  const content = (
    <div
      className={`relative overflow-hidden rounded-2xl bg-gray-100 ${ratioClass[ratio]} ${heightClass[height]} flex items-end p-6 group`}
    >
      <img
        src={image}
        alt={title || ''}
        className="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
      />
      {overlay && <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />}
      <div className={`relative z-10 w-full ${alignClass[align]}`}>
        {hover_text && (
          <span className="inline-block px-2 py-1 mb-2 text-[10px] font-semibold uppercase tracking-wide text-white bg-orange-500 rounded-full">
            {hover_text}
          </span>
        )}
        {title && <h3 className="text-xl font-bold text-white">{title}</h3>}
        {description && <p className="text-sm text-white/80 mt-1">{description}</p>}
      </div>
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto px-4">
      {link ? (
        <a href={link} className="block hover:opacity-95 transition">
          {content}
        </a>
      ) : (
        content
      )}
    </div>
  );
}
