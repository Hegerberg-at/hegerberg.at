import { createMarkdownProcessor } from '@astrojs/markdown-remark';

/**
 * Renders markdown from frontmatter fields (e.g. the short description of an
 * event) to HTML. Uses the same processor Astro uses for the body text of the
 * content files, so lists and links look identical.
 *
 * Runs at build time only; the processor is created once and reused.
 */
const processor = createMarkdownProcessor({});

export async function renderMarkdown(text?: string): Promise<string> {
  if (!text?.trim()) return '';
  const { code } = await (await processor).render(text);
  return code;
}
