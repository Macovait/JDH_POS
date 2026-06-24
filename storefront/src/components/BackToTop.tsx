'use client';
import { useEffect, useState } from 'react';
import { ArrowUp } from 'lucide-react';

export default function BackToTop() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 400);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const scroll = () => window.scrollTo({ top: 0, behavior: 'smooth' });

  return (
    <button
      onClick={scroll}
      aria-label="Back to top"
      className={`fixed bottom-24 md:bottom-8 right-4 z-50 w-10 h-10 rounded-full shadow-lg flex items-center justify-center transition-all duration-300 ${
        visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 pointer-events-none'
      }`}
      style={{ background: 'var(--brand-color, #f68b1e)', color: '#fff' }}
    >
      <ArrowUp size={18} />
    </button>
  );
}
