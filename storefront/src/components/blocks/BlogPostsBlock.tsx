'use client';

import type { BlogPostsProps } from '@/lib/block-types';
import { Calendar, ArrowRight, Clock } from 'lucide-react';

interface Props extends BlogPostsProps {}

// Placeholder posts until API integration
const placeholderPosts = [
  {
    id: 1,
    title: '10 Tips for Choosing the Perfect Baby Gear',
    excerpt:
      'Navigating the world of baby products can be overwhelming. Here are our top tips to help you make the best choices for your little one.',
    image: 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&h=400&fit=crop',
    date: '2026-06-15',
    read_time: '5 min read',
    slug: 'tips-choosing-baby-gear',
  },
  {
    id: 2,
    title: 'How to Create a Safe Nursery Environment',
    excerpt:
      'Safety first! Learn how to design a nursery that is both beautiful and safe for your newborn.',
    image: 'https://images.unsplash.com/photo-1522771753035-a0a1f66cd459?w=600&h=400&fit=crop',
    date: '2026-06-10',
    read_time: '4 min read',
    slug: 'safe-nursery-environment',
  },
  {
    id: 3,
    title: 'The Ultimate Guide to Baby Nutrition',
    excerpt:
      'From breastfeeding to first solids, understand the nutritional needs of your baby at every stage.',
    image: 'https://images.unsplash.com/photo-1515488042361-29d5d4e8608d?w=600&h=400&fit=crop',
    date: '2026-06-05',
    read_time: '7 min read',
    slug: 'baby-nutrition-guide',
  },
];

export default function BlogPostsBlock({
  title = 'Latest from the Blog',
  limit = 3,
  columns = 3,
  show_date = true,
  show_excerpt = true,
  show_read_more = true,
}: Props) {
  const posts = placeholderPosts.slice(0, limit);
  const colClass =
    columns === 2
      ? 'md:grid-cols-2'
      : columns === 4
      ? 'md:grid-cols-4'
      : 'md:grid-cols-3';

  return (
    <div className="max-w-7xl mx-auto px-4">
      {title && (
        <div className="text-center mb-8">
          <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900">
            {title}
          </h2>
        </div>
      )}
      <div className={`grid grid-cols-1 ${colClass} gap-6`}>
        {posts.map((post) => (
          <article
            key={post.id}
            className="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-md transition-shadow"
          >
            <a href={`/blog/${post.slug}`}>
              <img
                src={post.image}
                alt={post.title}
                className="w-full h-48 object-cover"
              />
            </a>
            <div className="p-5">
              {show_date && (
                <div className="flex items-center gap-3 text-xs text-gray-500 mb-3">
                  <span className="flex items-center gap-1">
                    <Calendar className="w-3.5 h-3.5" />
                    {new Date(post.date).toLocaleDateString('en-US', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                    })}
                  </span>
                  <span className="flex items-center gap-1">
                    <Clock className="w-3.5 h-3.5" />
                    {post.read_time}
                  </span>
                </div>
              )}
              <h3 className="text-lg font-bold text-gray-900 mb-2 leading-snug">
                <a href={`/blog/${post.slug}`} className="hover:opacity-80">
                  {post.title}
                </a>
              </h3>
              {show_excerpt && (
                <p className="text-sm text-gray-600 leading-relaxed line-clamp-3">
                  {post.excerpt}
                </p>
              )}
              {show_read_more && (
                <a
                  href={`/blog/${post.slug}`}
                  className="inline-flex items-center gap-1 mt-4 text-sm font-semibold hover:underline underline-offset-2"
                  style={{ color: 'var(--brand-color, #f97316)' }}
                >
                  Read More
                  <ArrowRight className="w-3.5 h-3.5" />
                </a>
              )}
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}
