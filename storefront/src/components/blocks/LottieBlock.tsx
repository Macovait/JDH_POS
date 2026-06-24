'use client';

import { useEffect, useRef } from 'react';
import type { LottieProps } from '@/lib/block-types';

export default function LottieBlock({
  src,
  loop = true,
  autoplay = true,
  width = '100%',
  height = 'auto',
  speed = 1,
  align = 'center',
}: LottieProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let animation: any;
    let cancelled = false;

    const load = async () => {
      try {
        const mod = await import('lottie-web');
        if (cancelled) return;
        const lottie = mod.default || mod;
        animation = lottie.loadAnimation({
          container: ref.current!,
          renderer: 'svg',
          loop,
          autoplay,
          path: src,
        });
        animation.setSpeed(speed);
      } catch {
        // Fallback to static image if lottie-web fails
      }
    };

    load();
    return () => {
      cancelled = true;
      if (animation) animation.destroy();
    };
  }, [src, loop, autoplay, speed]);

  const alignClass = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
  };

  return (
    <div className={`max-w-7xl mx-auto px-4 ${alignClass[align]}`}>
      <div
        ref={ref}
        className="inline-block"
        style={{ width: typeof width === 'number' ? `${width}px` : width, height: typeof height === 'number' ? `${height}px` : height }}
      />
    </div>
  );
}
