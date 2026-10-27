<?php
declare(strict_types=1);

namespace AstrX\Search\Tab;

/**
 * A source the Search page offers as a tab next to "This site": the web,
 * onion services, torrents. The engine modules answer it, each with its own
 * client; only enabled modules answer, so a tab appears with its module.
 *
 * A tab never throws for an engine that is down: it returns a page with
 * `available: false` and the Search page says so.
 */
interface SearchTab
{
    /** The tab's `source` value in the URL: short, lower-case, unique ('web'). */
    public function key(): string;

    /** The tab's label, translated. */
    public function label(): string;

    /** Where the tab sits: lower comes first ("This site" is always first). */
    public function order(): int;

    /** One page of results for a non-empty query. */
    public function search(string $query, int $page): SearchTabPage;
}
