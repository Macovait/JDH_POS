'use client';

import dynamic from 'next/dynamic';
import type { RowProps } from '@/lib/block-types';

const RenderBlock = dynamic(() => import('./RenderBlock'), { ssr: false });

interface Props extends RowProps {
  tenantId: number;
}

export default function RowBlock({
  columns,
  gap = 'md',
  align = 'stretch',
  justify = 'start',
  wrap = true,
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

  const justifyClass = {
    start: 'justify-start',
    center: 'justify-center',
    end: 'justify-end',
    between: 'justify-between',
    around: 'justify-around',
  };

  return (
    <div className={`max-w-7xl mx-auto px-4 ${class_name || ''}`}>
      <div
        className={`flex ${wrap ? 'flex-wrap' : 'flex-nowrap'} ${gapClass[gap]} ${alignClass[align]} ${justifyClass[justify]}`}
      >
        {columns.map((column) => (
          <RenderBlock key={column.id} block={column} tenantId={tenantId} />
        ))}
      </div>
    </div>
  );
}
