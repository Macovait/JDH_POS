import type { TitleProps } from '@/lib/block-types';

export default function TitleBlock({
  text,
  tag: Tag = 'h2',
  size = 'lg',
  align = 'left',
  color,
  class_name,
  sub_title,
  divider = false,
}: TitleProps) {
  const sizeClass = {
    xs: 'text-sm',
    sm: 'text-base',
    md: 'text-lg',
    lg: 'text-xl md:text-2xl',
    xl: 'text-2xl md:text-3xl',
    '2xl': 'text-3xl md:text-4xl',
  };

  const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  return (
    <div className={`max-w-7xl mx-auto px-4 ${alignClass[align]} ${class_name || ''}`}>
      <Tag className={`font-extrabold text-gray-900 ${sizeClass[size]}`} style={color ? { color } : undefined}>
        {text}
      </Tag>
      {sub_title && <p className="text-sm text-gray-500 mt-1">{sub_title}</p>}
      {divider && (
        <div className={`mt-3 flex ${align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : 'justify-start'}`}>
          <div className="w-16 h-0.5 rounded-full" style={{ backgroundColor: 'var(--brand-color)' }} />
        </div>
      )}
    </div>
  );
}
