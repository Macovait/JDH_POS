'use client';

import dynamic from 'next/dynamic';
import type { SectionProps } from '@/lib/block-types';

const RenderBlock = dynamic(() => import('./RenderBlock'), { ssr: false });

interface Props extends SectionProps {
  tenantId: number;
}

export default function SectionBlock({
  blocks,
  container = 'boxed',
  bg_image,
  bg_overlay,
  min_height,
  class_name,
  tenantId,
}: Props) {
  const containerClass = {
    full: 'w-full',
    boxed: 'max-w-7xl mx-auto px-4',
    narrow: 'max-w-4xl mx-auto px-4',
  };

  return (
    <div
      className={`${containerClass[container]} ${class_name || ''}`}
      style={{
        minHeight: min_height,
        backgroundImage: bg_image ? `url(${bg_image})` : undefined,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
      }}
    >
      {bg_overlay && (
        <div className="absolute inset-0" style={{ backgroundColor: bg_overlay }} />
      )}
      <div className="relative">
        {blocks.map((block) => (
          <RenderBlock key={block.id} block={block} tenantId={tenantId} />
        ))}
      </div>
    </div>
  );
}
