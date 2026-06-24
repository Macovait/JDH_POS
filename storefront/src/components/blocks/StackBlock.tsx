'use client';

import dynamic from 'next/dynamic';
import type { StackProps } from '@/lib/block-types';

const RenderBlock = dynamic(() => import('./RenderBlock'), { ssr: false });

interface Props extends StackProps {
  tenantId: number;
}

export default function StackBlock({
  items,
  direction = 'vertical',
  gap = 'md',
  align = 'stretch',
  class_name,
  tenantId,
}: Props) {
  const gapClass = {
    sm: 'gap-3',
    md: 'gap-4',
    lg: 'gap-6',
  };

  const alignClass = {
    start: 'items-start',
    center: 'items-center',
    end: 'items-end',
    stretch: 'items-stretch',
  };

  return (
    <div className={`max-w-7xl mx-auto px-4 ${class_name || ''}`}>
      <div
        className={`flex ${direction === 'horizontal' ? 'flex-row' : 'flex-col'} ${gapClass[gap]} ${alignClass[align]}`}
      >
        {items.map((item) => (
          <RenderBlock key={item.id} block={item} tenantId={tenantId} />
        ))}
      </div>
    </div>
  );
}
