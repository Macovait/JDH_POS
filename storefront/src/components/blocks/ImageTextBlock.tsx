import type { ImageTextProps } from '@/lib/block-types';

interface Props extends ImageTextProps {
  tenantId: number;
}

export default function ImageTextBlock({ image, image_position = 'left', heading, body, cta_text, cta_link, image_ratio = 'square' }: Props) {
  const ratioClass = {
    square: 'aspect-square',
    video: 'aspect-video',
    portrait: 'aspect-[3/4]',
  };

  const imgBlock = (
    <div className={`relative rounded-2xl overflow-hidden ${ratioClass[image_ratio]}`}>
      <img src={image} alt={heading} className="w-full h-full object-cover" loading="lazy" />
    </div>
  );

  const textBlock = (
    <div className="flex flex-col justify-center">
      <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900 mb-3">{heading}</h2>
      <p className="text-gray-600 leading-relaxed whitespace-pre-line">{body}</p>
      {cta_text && cta_link && (
        <a
          href={cta_link}
          className="mt-5 inline-block w-fit px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90"
          style={{ background: 'var(--brand-color)' }}
        >
          {cta_text}
        </a>
      )}
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`grid md:grid-cols-2 gap-8 items-center ${image_position === 'right' ? 'md:[direction:rtl]' : ''}`}>
        <div className={image_position === 'right' ? 'md:[direction:ltr]' : ''}>{imgBlock}</div>
        <div className={image_position === 'right' ? 'md:[direction:ltr]' : ''}>{textBlock}</div>
      </div>
    </div>
  );
}
