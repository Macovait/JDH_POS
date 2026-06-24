import BlockRenderer from './blocks/BlockRenderer';
import type { TypedBlock } from '@/lib/api';

interface Props {
  blocks: TypedBlock[];
  tenantId: number;
}

export default function ContentBlocks({ blocks, tenantId }: Props) {
  if (!blocks || blocks.length === 0) return null;
  return <BlockRenderer blocks={blocks as any} tenantId={tenantId} />;
}
