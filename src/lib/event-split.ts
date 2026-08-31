/**
 * Splitting events into upcoming and past has to happen in the browser: the
 * site is built statically, so a date computed while rendering stays frozen on
 * the day of the build and events would never move on their own.
 *
 * The pages therefore render every entry a visitor might need – including the
 * ones that are still upcoming today but will have passed tomorrow – and mark
 * each of them with `data-until`. This module decides what is actually shown.
 *
 * Markup contract:
 *
 *   [data-events-upcoming]         container, entries show while until >= today
 *   [data-events-past]             container, entries show once until < today
 *     data-limit="3"               at most this many visible entries (optional)
 *     data-entry-display="contents"  entries are display:contents wrappers
 *   [data-until="YYYY-MM-DD"]      an entry inside one of those containers
 *   [data-events-empty]            shown while no upcoming entry is visible
 *   [data-events-section]          ancestor, hidden while its container is empty
 */

const dateFormat = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Europe/Vienna',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});

/** Today in the Vienna time zone as YYYY-MM-DD, regardless of the visitor's. */
export function todayInVienna(): string {
  return dateFormat.format(new Date());
}

/**
 * Grid children carry Tailwind display utilities and `[hidden]` loses against
 * those in the cascade, so such containers ask for the class pair instead.
 */
function setVisible(entry: HTMLElement, visible: boolean, display?: string): void {
  if (display === 'contents') {
    entry.classList.toggle('contents', visible);
    entry.classList.toggle('hidden', !visible);
    return;
  }

  entry.hidden = !visible;
}

/** Shows the matching entries of one container and returns how many are left. */
function fillContainer(
  container: HTMLElement,
  keep: (until: string) => boolean,
): number {
  const limit = container.dataset.limit
    ? Number(container.dataset.limit)
    : Number.POSITIVE_INFINITY;
  const display = container.dataset.entryDisplay;
  let shown = 0;

  for (const entry of container.querySelectorAll<HTMLElement>('[data-until]')) {
    const visible = shown < limit && keep(entry.dataset.until ?? '');
    setVisible(entry, visible, display);
    if (visible) shown += 1;
  }

  return shown;
}

/** Fills every container of one kind and returns the total of visible entries. */
function fillAll(
  root: ParentNode,
  selector: string,
  keep: (until: string) => boolean,
): number {
  let shown = 0;

  for (const container of root.querySelectorAll<HTMLElement>(selector)) {
    const visible = fillContainer(container, keep);
    shown += visible;

    // Heading, intro text and buttons around an empty list would look odd.
    const section = container.closest<HTMLElement>('[data-events-section]');
    if (section) section.hidden = visible === 0;
  }

  return shown;
}

/**
 * Redoes the upcoming/past split. `today` is a parameter so the result can be
 * checked against any date from the browser console.
 */
export function applyEventSplit(
  today: string = todayInVienna(),
  root: ParentNode = document,
): void {
  const upcoming = fillAll(
    root,
    '[data-events-upcoming]',
    (until) => until >= today,
  );

  fillAll(root, '[data-events-past]', (until) => until < today);

  for (const empty of root.querySelectorAll<HTMLElement>('[data-events-empty]')) {
    empty.hidden = upcoming > 0;
  }
}

/** Applies the split now and again whenever the page comes back into view. */
export function watchEventSplit(root: ParentNode = document): void {
  const split = () => applyEventSplit(todayInVienna(), root);

  split();

  // A tab left open across midnight, or restored from the back/forward cache,
  // would otherwise keep showing yesterday's split.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) split();
  });
  window.addEventListener('pageshow', split);
}
