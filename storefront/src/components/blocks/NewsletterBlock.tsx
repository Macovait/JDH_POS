'use client';

import type { NewsletterProps } from '@/lib/block-types';
import { useState } from 'react';

interface Props extends NewsletterProps {}

export default function NewsletterBlock({
  title = 'Subscribe to our newsletter',
  description = 'Get the latest deals and updates delivered to your inbox.',
  placeholder = 'Enter your email',
  button_text = 'Subscribe',
  success_message = 'Thanks for subscribing!',
}: Props) {
  const [email, setEmail] = useState('');
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [errorMessage, setErrorMessage] = useState('');

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setStatus('submitting');
    setErrorMessage('');

    try {
      // Replace with your actual API endpoint
      const response = await fetch('/api/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });

      if (response.ok) {
        setStatus('success');
        setEmail('');
      } else {
        const data = await response.json();
        setStatus('error');
        setErrorMessage(data.message || 'Something went wrong. Please try again.');
      }
    } catch (error) {
      setStatus('error');
      setErrorMessage('Network error. Please try again.');
    }
  };

  if (status === 'success') {
    return (
      <div className="max-w-7xl mx-auto px-4">
        <div className="bg-white rounded-2xl border border-gray-100 p-8 md:p-12 text-center">
          <div className="max-w-md mx-auto">
            <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
              <svg className="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h3 className="text-xl font-extrabold text-gray-900 mb-2">{success_message}</h3>
            <button
              onClick={() => setStatus('idle')}
              className="text-sm text-gray-500 hover:text-gray-700 underline mt-2"
            >
              Subscribe another email
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4">
      <div className="bg-white rounded-2xl border border-gray-100 p-8 md:p-12 text-center">
        <h3 className="text-xl font-extrabold text-gray-900 mb-2">{title}</h3>
        <p className="text-sm text-gray-500 mb-5">{description}</p>
        <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-2 max-w-md mx-auto">
          <input
            id="newsletter-email"
            name="newsletter-email"
            type="email"
            required
            aria-label={placeholder}
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder={placeholder}
            disabled={status === 'submitting'}
            className="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 disabled:opacity-50"
          />
          <button
            type="submit"
            disabled={status === 'submitting'}
            className="px-6 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed min-w-[100px]"
            style={{ background: 'var(--brand-color, #f97316)' }}
          >
            {status === 'submitting' ? (
              <div className="flex items-center justify-center gap-2">
                <svg className="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                Subscribing...
              </div>
            ) : (
              button_text
            )}
          </button>
        </form>
        {status === 'error' && (
          <p className="text-sm text-red-500 mt-3">{errorMessage}</p>
        )}
        <p className="text-xs text-gray-400 mt-3">
          We'll never share your email. Unsubscribe at any time.
        </p>
      </div>
    </div>
  );
}