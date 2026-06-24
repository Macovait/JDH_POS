'use client';
import { useState } from 'react';
import { Mail, Send, CheckCircle } from 'lucide-react';

interface Props {
  tenantName?: string;
  accentColor?: string;
}

export default function NewsletterSignup({ tenantName = 'Our Store', accentColor }: Props) {
  const [email, setEmail] = useState('');
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.includes('@')) return;
    setSubmitted(true);
    // TODO: wire to backend when API ready
  };

  return (
    <section className="relative overflow-hidden rounded-2xl"
      style={{ background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)' }}>
      <div className="absolute inset-0 opacity-10"
        style={{ backgroundImage: 'radial-gradient(circle at 20% 50%, #fff 1px, transparent 1px)', backgroundSize: '32px 32px' }} />
      <div className="relative px-6 py-10 md:py-14 text-center">
        <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white/10 mb-4">
          <Mail className="text-white" size={22} />
        </div>
        <h3 className="text-xl md:text-2xl font-extrabold text-white mb-2">
          Get the best deals first
        </h3>
        <p className="text-white/60 text-sm mb-6 max-w-md mx-auto">
          Subscribe to {tenantName} and receive exclusive offers, new arrivals, and flash sale alerts straight to your inbox.
        </p>

        {submitted ? (
          <div className="flex items-center justify-center gap-2 text-emerald-400 font-semibold">
            <CheckCircle size={18} />
            <span>Thanks! Check your inbox for a welcome offer.</span>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-2 max-w-md mx-auto">
            <input
              id="email"
              name="email"
              type="email"
              required
              aria-label="Enter your email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Enter your email"
              className="flex-1 px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-white/30 text-sm"
            />
            <button
              type="submit"
              className="px-6 py-2.5 rounded-xl bg-white text-gray-900 font-bold text-sm hover:bg-gray-100 transition flex items-center justify-center gap-2"
            >
              Subscribe <Send size={14} />
            </button>
          </form>
        )}
      </div>
    </section>
  );
}
