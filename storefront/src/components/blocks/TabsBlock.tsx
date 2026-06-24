'use client';

import { useState } from 'react';
import dynamic from 'next/dynamic';
import type { TabsProps } from '@/lib/block-types';

const RenderBlock = dynamic(() => import('./RenderBlock'), { ssr: false });

interface Props extends TabsProps {
  tenantId: number;
}

export default function TabsBlock({ tabs, orientation = 'horizontal', style = 'default', tenantId }: Props) {
  const [active, setActive] = useState(0);

  const tabListClass = {
    horizontal: 'flex flex-wrap gap-2 border-b border-gray-200',
    vertical: 'flex flex-col gap-1 min-w-[160px]',
  };

  const tabClass = {
    default: {
      active: 'border-b-2 border-orange-500 text-orange-600',
      inactive: 'text-gray-500 hover:text-gray-700',
    },
    pills: {
      active: 'bg-orange-500 text-white rounded-full',
      inactive: 'bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-full',
    },
    underline: {
      active: 'border-b-2 border-orange-500 text-orange-600',
      inactive: 'text-gray-500 hover:text-gray-700',
    },
  };

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className={`${orientation === 'vertical' ? 'flex gap-6' : ''}`}>
        <div className={tabListClass[orientation]} role="tablist">
          {tabs.map((tab, index) => (
            <button
              key={index}
              type="button"
              role="tab"
              aria-selected={active === index}
              onClick={() => setActive(index)}
              className={`px-4 py-2 text-sm font-semibold transition ${
                active === index ? tabClass[style].active : tabClass[style].inactive
              }`}
            >
              {tab.icon && <span className="mr-1.5">{tab.icon}</span>}
              {tab.label}
            </button>
          ))}
        </div>
        <div className="flex-1 py-4">
          {tabs.map((tab, index) => (
            <div
              key={index}
              role="tabpanel"
              hidden={active !== index}
              className={active === index ? 'block' : 'hidden'}
            >
              {tab.blocks.map((block) => (
                <RenderBlock key={block.id} block={block} tenantId={tenantId} />
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
