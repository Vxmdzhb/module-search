<?php
declare(strict_types=1);

namespace AstrX\Search\Tab;

/**
 * One page of a tab's results.
 */
final readonly class SearchTabPage
{
    /**
     * @param list<SearchHit> $hits
     * @param string          $moreUrl the engine's own page for this query, with
     *                                 the options the tab doesn't show ('' for none)
     */
    public function __construct(
        public bool $available,
        public array $hits = [],
        public int $total = 0,
        public bool $hasNext = false,
        public string $moreUrl = '',
    ) {}

    public static function unavailable(string $moreUrl = ''): self
    {
        return new self(false, [], 0, false, $moreUrl);
    }
}
