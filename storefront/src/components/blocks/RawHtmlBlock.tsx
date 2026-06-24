import type { RawHtmlProps } from '@/lib/block-types';

interface Props extends RawHtmlProps {}

export default function RawHtmlBlock({ html }: Props) {
  if (!html) return null;
  return <div className="max-w-7xl mx-auto px-4" dangerouslySetInnerHTML={{ __html: html }} />;
}
