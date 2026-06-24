'use client';

import { useState, type FormEvent } from 'react';
import { Mail, Phone, MapPin, Clock, Send, ArrowLeft, MessageSquare } from 'lucide-react';
import Link from 'next/link';
import toast from 'react-hot-toast';

interface Props {
  params: { tenant: string };
}

export default function ContactPage({ params }: Props) {
  const tenantId = parseInt(params.tenant, 10);
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
  const [sending, setSending] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!form.name || !form.email || !form.message) {
      toast.error('Please fill in all required fields');
      return;
    }
    setSending(true);
    // Simulate sending
    await new Promise((r) => setTimeout(r, 1200));
    setSending(false);
    toast.success('Message sent! We will get back to you soon.', {
      icon: '📨',
      style: {
        background: '#1e293b',
        color: '#f1f5f9',
        borderRadius: '0.75rem',
        border: '1px solid #334155',
      },
    });
    setForm({ name: '', email: '', subject: '', message: '' });
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8 min-h-screen">
      {/* Header */}
      <div className="flex items-center gap-3 mb-8">
        <Link
          href={`/${tenantId}`}
          className="w-10 h-10 rounded-xl bg-gray-100 dark:bg-slate-800 flex items-center justify-center text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-slate-700 transition"
        >
          <ArrowLeft size={18} />
        </Link>
        <div>
          <h1 className="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2">
            <MessageSquare size={24} className="text-brand" />
            Contact Us
          </h1>
          <p className="text-sm text-gray-500 dark:text-slate-400">
            We would love to hear from you. Send us a message!
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Contact Info */}
        <div className="space-y-4">
          <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <div className="w-10 h-10 rounded-lg bg-brand/10 flex items-center justify-center text-brand mb-3">
              <Phone size={20} />
            </div>
            <h3 className="font-bold text-gray-900 dark:text-white mb-1">Phone</h3>
            <p className="text-sm text-gray-500 dark:text-slate-400">
              Mon–Fri, 8am–6pm EAT
            </p>
            <p className="text-sm font-medium text-gray-900 dark:text-white mt-1">
              +254 700 000 000
            </p>
          </div>

          <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <div className="w-10 h-10 rounded-lg bg-brand/10 flex items-center justify-center text-brand mb-3">
              <Mail size={20} />
            </div>
            <h3 className="font-bold text-gray-900 dark:text-white mb-1">Email</h3>
            <p className="text-sm text-gray-500 dark:text-slate-400">
              We reply within 24 hours
            </p>
            <p className="text-sm font-medium text-gray-900 dark:text-white mt-1">
              support@example.com
            </p>
          </div>

          <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <div className="w-10 h-10 rounded-lg bg-brand/10 flex items-center justify-center text-brand mb-3">
              <MapPin size={20} />
            </div>
            <h3 className="font-bold text-gray-900 dark:text-white mb-1">Address</h3>
            <p className="text-sm text-gray-500 dark:text-slate-400">
              Nairobi, Kenya
            </p>
            <p className="text-sm font-medium text-gray-900 dark:text-white mt-1">
              123 Business Street, CBD
            </p>
          </div>

          <div className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <div className="w-10 h-10 rounded-lg bg-brand/10 flex items-center justify-center text-brand mb-3">
              <Clock size={20} />
            </div>
            <h3 className="font-bold text-gray-900 dark:text-white mb-1">Business Hours</h3>
            <div className="space-y-1 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500 dark:text-slate-400">Mon–Fri</span>
                <span className="font-medium text-gray-900 dark:text-white">8:00 AM – 6:00 PM</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500 dark:text-slate-400">Saturday</span>
                <span className="font-medium text-gray-900 dark:text-white">9:00 AM – 4:00 PM</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500 dark:text-slate-400">Sunday</span>
                <span className="font-medium text-gray-900 dark:text-white">Closed</span>
              </div>
            </div>
          </div>
        </div>

        {/* Contact Form */}
        <div className="lg:col-span-2">
          <form
            onSubmit={handleSubmit}
            className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 space-y-5"
          >
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-2">
              Send a Message
            </h2>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  Full Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  required
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="John Doe"
                  className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40 transition"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                  Email <span className="text-red-500">*</span>
                </label>
                <input
                  type="email"
                  required
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  placeholder="john@example.com"
                  className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40 transition"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                Subject
              </label>
              <input
                type="text"
                value={form.subject}
                onChange={(e) => setForm({ ...form, subject: e.target.value })}
                placeholder="How can we help?"
                className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40 transition"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                Message <span className="text-red-500">*</span>
              </label>
              <textarea
                required
                rows={5}
                value={form.message}
                onChange={(e) => setForm({ ...form, message: e.target.value })}
                placeholder="Tell us more about your inquiry..."
                className="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand/40 transition resize-y"
              />
            </div>

            <button
              type="submit"
              disabled={sending}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-brand text-white font-bold text-sm hover:opacity-90 transition disabled:opacity-50"
            >
              <Send size={16} />
              {sending ? 'Sending...' : 'Send Message'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
