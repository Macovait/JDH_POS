'use client';
import { useEffect } from 'react';
import { recordView } from './RecentlyViewed';
import type { Product } from '@/lib/api';

interface Props {
  product: Product;
}

export default function ProductViewTracker({ product }: Props) {
  useEffect(() => {
    recordView(product);
  }, [product]);

  return null;
}
