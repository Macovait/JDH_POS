import type { TextSectionProps } from '@/lib/block-types';

interface Props extends TextSectionProps {}

export default function TextSectionBlock({ heading, body, align = 'center', heading_size = 'lg', cta_text, cta_link }: Props) {
  const sizeMap = {
    sm: 'text-xl',
    md: 'text-2xl',
    lg: 'text-3xl',
    xl: 'text-4xl',
  };

  const alignClass = align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left';

  return (
    <div className={`max-w-4xl mx-auto px-4 ${alignClass}`}>
      <h2 className={`font-extrabold text-gray-900 mb-4 ${sizeMap[heading_size]}`}>
        {heading}
      </h2>
      <p className="text-gray-600 leading-relaxed whitespace-pre-line">
        {body}
      </p>
      {cta_text && cta_link && (
        <a
          href={cta_link}
          className="inline-block mt-5 px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90"
          style={{ background: 'var(--brand-color)' }}
        >
          {cta_text}
        </a>
      )}
    </div>
  );
}
