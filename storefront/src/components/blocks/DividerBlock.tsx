import type { DividerProps } from '@/lib/block-types';

interface Props extends DividerProps {}

export default function DividerBlock({
  style = 'solid',
  width = 'full',
  align = 'center',
  label,
  spacing = 'md',
}: Props) {
  const spacingClass = spacing === 'sm' ? 'py-4' : spacing === 'lg' ? 'py-10' : 'py-6';
  const widthClass =
    width === 'short'
      ? 'w-16'
      : width === 'medium'
      ? 'w-32'
      : 'w-full';

  const alignClass =
    align === 'left'
      ? 'mr-auto'
      : align === 'right'
      ? 'ml-auto'
      : 'mx-auto';

  const borderClass =
    style === 'dashed'
      ? 'border-t-2 border-dashed border-gray-300'
      : style === 'dotted'
      ? 'border-t-2 border-dotted border-gray-300'
      : style === 'gradient'
      ? 'h-px bg-gradient-to-r from-transparent via-gray-400 to-transparent'
      : 'border-t border-gray-200';

  return (
    <div className={`max-w-4xl mx-auto px-4 ${spacingClass}`}>
      {label ? (
        <div className="flex items-center gap-3">
          <div className={`flex-1 ${borderClass}`} />
          <span className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{label}</span>
          <div className={`flex-1 ${borderClass}`} />
        </div>
      ) : (
        <div className={`${widthClass} ${alignClass} ${borderClass}`} />
      )}
    </div>
  );
}
