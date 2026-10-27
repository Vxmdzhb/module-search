<?php
declare(strict_types=1);

namespace AstrX\Search\Tab;

/**
 * One result, already cleaned by the tab: plain text only (the template
 * escapes it again), and links that are http(s), magnet: or site-relative.
 */
final readonly class SearchHit
{
    /**
     * @param string                                  $url        where the title links to
     * @param string                                  $displayUrl the address shown above the title ('' for none)
     * @param list<string>                            $meta       short facts, shown on one line
     * @param list<array{label:string,url:string}>    $links      extra links under the result (magnet, download)
     * @param list<array{path:string,size:string}>    $files      files to list under the title (torrents)
     * @param int                                     $moreFiles  how many files are not listed
     * @param string                                  $badge      a short label before the title ('' for none)
     * @param bool                                    $external   true when $url leaves the site
     */
    public function __construct(
        public string $title,
        public string $url,
        public string $displayUrl = '',
        public string $snippet = '',
        public array $meta = [],
        public array $links = [],
        public array $files = [],
        public int $moreFiles = 0,
        public string $badge = '',
        public bool $external = true,
    ) {}

    /**
     * The hit as the search templates read it. Every optional part is a list,
     * empty when absent, so a template needs no flag sections inside its
     * result loop (they would lose the loop's row).
     *
     * @return array<string,mixed>
     */
    public function view(): array
    {
        $one = static fn(string $v): array => $v === '' ? [] : [['text' => $v]];
        return [
            'title'      => $this->title,
            'url'        => $this->url,
            'rel'        => $this->external ? 'noopener noreferrer nofollow' : 'nofollow',
            'badge'      => $one($this->badge),
            'display'    => $one($this->displayUrl),
            'snippet'    => $one($this->snippet),
            'meta'       => $one(implode(' · ', $this->meta)),
            'files'      => $this->files,
            'more_files' => $this->moreFiles > 0 ? [['count' => $this->moreFiles]] : [],
            'links'      => $this->links,
        ];
    }
}
