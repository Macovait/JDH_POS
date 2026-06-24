import type { TypedBlock } from '@/lib/block-types';
import RenderBlock from './RenderBlock';

interface Props {
  blocks: TypedBlock[];
  tenantId: number;
}

export default function BlockRenderer({ blocks, tenantId }: Props) {
  if (!blocks || blocks.length === 0) return null;

  return (
    <>
      {blocks.map((block) => {
        if (!block.is_active) return null;

        const wrapperClass = [
          block.padding || 'py-8',
          block.section_class || '',
        ].filter(Boolean).join(' ');

        const style: React.CSSProperties = {};
        if (block.bg_color && block.bg_color !== 'transparent') style.backgroundColor = block.bg_color;
        if (block.text_color) style.color = block.text_color;

        const content = <RenderBlock block={block} tenantId={tenantId} />;

        return (
          <section
            key={block.id}
            className={wrapperClass}
            style={style}
            data-block-type={block.type}
            data-block-id={block.id}
          >
            {content}
          </section>
        );
      })}
    </>
  );
}