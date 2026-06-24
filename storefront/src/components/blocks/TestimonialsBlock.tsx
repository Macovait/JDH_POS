import type { TestimonialsProps } from '@/lib/block-types';

interface Props extends TestimonialsProps {}

export default function TestimonialsBlock({ title = 'What our customers say', testimonials, layout = 'grid' }: Props) {
  if (!testimonials || testimonials.length === 0) return null;

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && <h2 className="text-2xl font-extrabold text-gray-900 text-center mb-8">{title}</h2>}
      <div className={`grid gap-5 ${layout === 'carousel' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'}`}>
        {testimonials.map((t, i) => (
          <div key={i} className="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-sm transition">
            <div className="flex items-center gap-3 mb-3">
              {t.avatar ? (
                <img src={t.avatar} alt={t.name} className="w-10 h-10 rounded-full object-cover" />
              ) : (
                <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-500">
                  {t.name.charAt(0)}
                </div>
              )}
              <div>
                <p className="text-sm font-bold text-gray-800">{t.name}</p>
                <div className="flex gap-0.5">
                  {Array.from({ length: 5 }).map((_, j) => (
                    <svg key={j} className={`w-3.5 h-3.5 ${j < t.rating ? 'text-yellow-400' : 'text-gray-200'}`} fill="currentColor" viewBox="0 0 20 20">
                      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  ))}
                </div>
              </div>
            </div>
            <p className="text-sm text-gray-600 leading-relaxed">{t.text}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
