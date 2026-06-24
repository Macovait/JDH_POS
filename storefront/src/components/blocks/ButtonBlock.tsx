import type { ButtonBlockProps } from '@/lib/block-types';

interface Props extends ButtonBlockProps {}

export default function ButtonBlock({
  text,
  link,
  style = 'primary',
  size = 'md',
  width = 'auto',
  align = 'center',
  icon,
  target = '_self',
  rel,
}: Props) {
  const sizeMap = {
    sm: 'px-4 py-2 text-xs',
    md: 'px-6 py-2.5 text-sm',
    lg: 'px-8 py-3.5 text-base',
  };

  const styleClass =
    style === 'secondary'
      ? 'bg-gray-900 text-white hover:bg-gray-800'
      : style === 'outline'
      ? 'bg-transparent border-2 border-current hover:bg-gray-50'
      : style === 'ghost'
      ? 'bg-transparent hover:bg-gray-100 underline-offset-2'
      : 'bg-brand text-white hover:opacity-90';

  const colorClass = style === 'outline' ? 'text-orange-500' : style === 'ghost' ? 'text-gray-900' : '';
  const alignClass = align === 'left' ? 'text-left' : align === 'right' ? 'text-right' : 'text-center';
  const widthClass = width === 'full' ? 'w-full justify-center' : 'inline-flex';

  return (
    <div className={`max-w-4xl mx-auto px-4 ${alignClass}`}>
      <a
        href={link}
        target={target}
        rel={rel || (target === '_blank' ? 'noopener noreferrer' : undefined)}
        className={`items-center gap-2 rounded-xl font-bold transition ${sizeMap[size]} ${styleClass} ${colorClass} ${widthClass}`}
        style={style === 'primary' ? { background: 'var(--brand-color)' } : undefined}
      >
        {icon && <span>{icon}</span>}
        {text}
      </a>
    </div>
  );
}
