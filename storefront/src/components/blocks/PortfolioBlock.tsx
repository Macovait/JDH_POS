'use client';

import { useState } from 'react';
import type { PortfolioProps } from '@/lib/block-types';

export default function PortfolioBlock({
  projects,
  columns = 3,
  filter = false,
  gap = 'md',
  aspect = 'square',
}: PortfolioProps) {
  const [activeFilter, setActiveFilter] = useState('all');
  const categories = filter ? ['all', ...Array.from(new Set(projects.map((p) => p.category).filter(Boolean) as string[]))] : [];
  const filtered = activeFilter === 'all' || !filter ? projects : projects.filter((p) => p.category === activeFilter);

  const colClass = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  };

  const gapClass = {
    sm: 'gap-3',
    md: 'gap-4',
    lg: 'gap-6',
  };

  const aspectClass = {
    square: 'aspect-square',
    video: 'aspect-video',
    portrait: 'aspect-[3/4]',
  };

  return (
    <div className="max-w-7xl mx-auto px-4">
      {filter && categories.length > 1 && (
        <div className="flex flex-wrap gap-2 justify-center mb-6">
          {categories.map((cat) => (
            <button
              key={cat}
              type="button"
              onClick={() => setActiveFilter(cat)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-full transition ${
                activeFilter === cat
                  ? 'bg-orange-500 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>
      )}
      <div className={`grid ${colClass[columns]} ${gapClass[gap]}`}>
        {filtered.map((project, index) => (
          <a
            key={index}
            href={project.link || '#'}
            className="group relative overflow-hidden rounded-2xl bg-gray-100"
          >
            <img
              src={project.image}
              alt={project.title}
              className={`w-full ${aspectClass[aspect]} object-cover transition-transform duration-500 group-hover:scale-105`}
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-4">
              <div>
                {project.category && <span className="text-[10px] font-semibold uppercase tracking-wide text-orange-300">{project.category}</span>}
                <h3 className="text-base font-bold text-white">{project.title}</h3>
                {project.description && <p className="text-xs text-white/80 mt-0.5 line-clamp-2">{project.description}</p>}
              </div>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}
