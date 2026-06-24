'use client';

import { useState, useEffect } from 'react';
import type { CountdownProps } from '@/lib/block-types';

interface Props extends CountdownProps {}

function pad(n: number) {
  return n.toString().padStart(2, '0');
}

export default function CountdownBlock({
  target,
  title,
  subtitle,
  show_labels = true,
  labels = {},
  expired_text = 'Offer has ended',
  link,
}: Props) {
  const [timeLeft, setTimeLeft] = useState({ days: 0, hours: 0, minutes: 0, seconds: 0 });
  const [expired, setExpired] = useState(false);

  useEffect(() => {
    const targetDate = new Date(target).getTime();
    if (isNaN(targetDate)) return;

    const tick = () => {
      const now = Date.now();
      const diff = targetDate - now;
      if (diff <= 0) {
        setExpired(true);
        setTimeLeft({ days: 0, hours: 0, minutes: 0, seconds: 0 });
        return;
      }
      setExpired(false);
      setTimeLeft({
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff / (1000 * 60 * 60)) % 24),
        minutes: Math.floor((diff / (1000 * 60)) % 60),
        seconds: Math.floor((diff / 1000) % 60),
      });
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [target]);

  const labelMap = {
    days: labels.days || 'Days',
    hours: labels.hours || 'Hours',
    minutes: labels.minutes || 'Minutes',
    seconds: labels.seconds || 'Seconds',
  };

  return (
    <div className="max-w-4xl mx-auto px-4 text-center">
      {title && <h2 className="text-2xl font-bold text-gray-900 mb-2">{title}</h2>}
      {subtitle && !expired && <p className="text-gray-600 text-sm mb-5">{subtitle}</p>}
      {expired ? (
        <p className="text-lg font-bold text-gray-500">{expired_text}</p>
      ) : (
        <>
          <div className="flex flex-wrap justify-center gap-3 md:gap-5">
            {(
              [
                ['days', timeLeft.days],
                ['hours', timeLeft.hours],
                ['minutes', timeLeft.minutes],
                ['seconds', timeLeft.seconds],
              ] as const
            ).map(([key, value]) => (
              <div key={key} className="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 min-w-[72px]">
                <div className="text-2xl md:text-3xl font-extrabold text-gray-900">{pad(value)}</div>
                {show_labels && (
                  <div className="text-[10px] uppercase tracking-wider text-gray-500 mt-1">
                    {labelMap[key as keyof typeof labelMap]}
                  </div>
                )}
              </div>
            ))}
          </div>
          {link && (
            <a
              href={link}
              className="inline-block mt-6 px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90"
              style={{ background: 'var(--brand-color)' }}
            >
              Shop Now
            </a>
          )}
        </>
      )}
    </div>
  );
}
