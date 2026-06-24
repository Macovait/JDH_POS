'use client';

import dynamic from 'next/dynamic';
import type { ColumnProps } from '@/lib/block-types';

const RenderBlock = dynamic(() => import('./RenderBlock'), { ssr: false });

interface Props extends ColumnProps {
  tenantId: number;
}

export default function ColumnBlock({ width = 'auto', blocks, class_name, tenantId }: Props) {
  const widthClass = {
    '1/2': 'w-full md:w-1/2',
    '1/3': 'w-full md:w-1/3',
    '2/3': 'w-full md:w-2/3',
    '1/4': 'w-full md:w-1/4',
    '3/4': 'w-full md:w-3/4',
    '1/5': 'w-full md:w-1/5',
    '2/5': 'w-full md:w-2/5',
    '3/5': 'w-full md:w-3/5',
    '4/5': 'w-full md:w-4/5',
    'auto': 'flex-1',
    'full': 'w-full',
  };

  return (
    <div className={`${widthClass[width]} min-w-0 ${class_name || ''}`}>
      {blocks.map((block) => (
        <RenderBlock key={block.id} block={block} tenantId={tenantId} />
      ))}
    </div>
  );
}
