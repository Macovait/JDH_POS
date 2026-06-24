'use client';

import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import type { AccordionProps } from '@/lib/block-types';

interface Props extends AccordionProps {}

export default function AccordionBlock({ title, items, allow_multiple = false, style = 'default' }: Props) {
  const [open, setOpen] = useState<Set<number>>(new Set());

  if (!items || items.length === 0) return null;

  const toggle = (i: number) => {
    setOpen((prev) => {
      const next = new Set(prev);
      if (next.has(i)) {
        next.delete(i);
      } else {
        if (!allow_multiple) next.clear();
        next.add(i);
      }
      return next;
    });
  };

  const containerClass =
    style === 'bordered'
      ? 'border border-gray-200 rounded-xl overflow-hidden'
      : style === 'flush'
      ? 'border-b border-gray-200'
      : 'space-y-3';

  const itemClass =
    style === 'bordered'
      ? 'border-b border-gray-200 last:border-b-0'
      : style === 'flush'
      ? ''
      : 'bg-white rounded-xl border border-gray-100';

  return (
    <div className="max-w-3xl mx-auto px-4">
      {title && <h2 className="text-xl font-bold text-gray-900 mb-4 text-center">{title}</h2>}
      <div className={containerClass}>
        {items.map((item, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} className={itemClass}>
              <button
                onClick={() => toggle(i)}
                className="w-full flex items-center justify-between p-4 text-left"
                aria-expanded={isOpen}
              >
                <span className="font-semibold text-gray-900 text-sm md:text-base">{item.question}</span>
                <ChevronDown
                  size={18}
                  className={`text-gray-500 transition-transform duration-300 ${isOpen ? 'rotate-180' : ''}`}
                />
              </button>
              <div
                className={`overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-96' : 'max-h-0'}`}
              >
                <div className={`px-4 pb-4 text-gray-600 text-sm leading-relaxed ${style === 'flush' ? 'pt-0' : ''}`}>
                  {item.answer}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
