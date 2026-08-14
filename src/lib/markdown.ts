import { createMarkdownProcessor } from '@astrojs/markdown-remark';

/**
 * Rendert Markdown aus Frontmatter-Feldern (z. B. der Kurzbeschreibung einer
 * Veranstaltung) zu HTML. Nutzt denselben Prozessor wie Astro für den
 * Fließtext der Inhaltsdateien, damit Listen und Links gleich aussehen.
 *
 * Läuft nur zur Buildzeit; der Prozessor wird einmal erzeugt und
 * wiederverwendet.
 */
const prozessor = createMarkdownProcessor({});

export async function renderMarkdown(text?: string): Promise<string> {
  if (!text?.trim()) return '';
  const { code } = await (await prozessor).render(text);
  return code;
}
