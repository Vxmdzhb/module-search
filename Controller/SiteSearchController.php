<?php
declare(strict_types=1);

namespace AstrX\Search\Controller;

use AstrX\Auth\Gate;
use AstrX\Http\Request;
use AstrX\I18n\Translator;
use AstrX\Kernel\AbstractController;
use AstrX\Module\ModuleRegistry;
use AstrX\News\NewsPermission;
use AstrX\Result\DiagnosticsCollector;
use AstrX\Result\Result;
use AstrX\Routing\UrlGenerator;
use AstrX\Search\SiteSearchService;
use AstrX\Search\Tab\SearchHit;
use AstrX\Search\Tab\SearchTab;
use AstrX\Template\TemplateVars;

/**
 * The site's one Search page — a no-JavaScript GET form with a tab per source.
 *
 * "This site" searches the CMS's public content (news, static pages, comments,
 * imageboard posts; chat is never indexed) through SiteSearchService. Every
 * other tab comes from an enabled module that answers {@see SearchTab} — the
 * web, onion and torrent engines — so the page grows a tab when such a module
 * is switched on and loses it when it is switched off. Only the visible tab is
 * searched.
 *
 * The page is seeded with file_name 'site_search', so the reflection router
 * resolves it to this class; its URL slug is WORDING_SEARCH ('search'). The
 * query lives in the URL (`q`, `source`, `type`, `page`), so every result page
 * can be bookmarked. Public: gated by NEWS_VIEW (granted to guests).
 */
final class SiteSearchController extends AbstractController
{
    private const SEARCH_LIMIT = 50;

    private const TYPES = ['all', 'news', 'pages', 'comments', 'board'];

    /** The `source` of the built-in tab. */
    private const SITE = 'site';

    public function __construct(
        DiagnosticsCollector            $collector,
        private readonly TemplateVars   $ctx,
        private readonly Request        $request,
        private readonly Gate           $gate,
        private readonly Translator     $t,
        private readonly UrlGenerator   $urlGen,
        private readonly SiteSearchService $search,
        private readonly ModuleRegistry $modules,
    ) {
        parent::__construct($collector);
    }

    public function handle(): Result
    {
        $this->t->load('Search');

        if ($this->gate->cannot(NewsPermission::VIEW)) {
            http_response_code(404);
            exit;
        }

        $slug   = $this->t->t('WORDING_SEARCH');
        $q      = self::queryStr($this->request, 'q');
        $page   = max(1, self::queryInt($this->request, 'page', 1));
        $tabs   = $this->tabs();
        $source = self::queryStr($this->request, 'source');
        if (!isset($tabs[$source])) {
            $source = self::SITE;
        }
        $type = self::queryStr($this->request, 'type');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'all';
        }

        $hits        = [];
        $count       = '';
        $unavailable = false;
        $prevUrl     = '';
        $nextUrl     = '';
        $moreUrl     = '';
        $searched    = $q !== '';

        if ($searched && $source === self::SITE) {
            $hits  = $this->siteHits($q, $type);
            $count = $this->t->t('search.result_count', ['count' => count($hits)]);
        } elseif ($searched) {
            $result      = $tabs[$source]->search($q, $page);
            $unavailable = !$result->available;
            $hits        = array_map(static fn(SearchHit $h): array => $h->view(), $result->hits);
            $count       = $result->total > 0
                ? $this->t->t('search.result_count_about', ['count' => $result->total])
                : $this->t->t('search.result_count', ['count' => count($hits)]);
            $moreUrl     = $result->moreUrl;
            $state       = ['q' => $q, 'source' => $source];
            if ($page > 1) {
                $prevUrl = $this->urlGen->toPage($slug, $state + ['page' => $page - 1]);
            }
            if ($result->hasNext) {
                $nextUrl = $this->urlGen->toPage($slug, $state + ['page' => $page + 1]);
            }
        }

        $tabRows = [['key' => self::SITE, 'label' => $this->t->t('search.tab.site')]];
        foreach ($tabs as $key => $tab) {
            $tabRows[] = ['key' => $key, 'label' => $tab->label()];
        }
        $tabLinks = [];
        foreach ($tabRows as $row) {
            $params = ['source' => $row['key']];
            if ($q !== '') {
                $params['q'] = $q;
            }
            $tabLinks[] = [
                'label'  => $row['label'],
                'url'    => $this->urlGen->toPage($slug, $params),
                'active' => $row['key'] === $source,
            ];
        }

        $this->buildTypeOptions($type);
        $this->ctx->set('form_action',    $this->urlGen->toPage($slug));
        $this->ctx->set('query',          $q);
        $this->ctx->set('active_source',  $source);
        $this->ctx->set('search_tabs',    $tabLinks);
        $this->ctx->set('has_tabs',       count($tabLinks) > 1);
        $this->ctx->set('is_site_tab',    $source === self::SITE);
        $this->ctx->set('searched',       $searched);
        $this->ctx->set('backend_unavailable', $unavailable);
        // Not 'results': DefaultTemplateContext uses that key for the page's
        // notices and sets it after this controller runs.
        $this->ctx->set('search_hits',    $hits);
        $this->ctx->set('has_results',    $hits !== []);
        $this->ctx->set('result_count',   $count);
        $this->ctx->set('has_prev',       $prevUrl !== '');
        $this->ctx->set('prev_url',       $prevUrl);
        $this->ctx->set('has_next',       $nextUrl !== '');
        $this->ctx->set('next_url',       $nextUrl);
        $this->ctx->set('has_more_url',   $moreUrl !== '');
        $this->ctx->set('more_url',       $moreUrl);
        $this->setLabels();

        return $this->ok();
    }

    /**
     * The enabled modules' tabs, by key, in their order.
     *
     * @return array<string, SearchTab>
     */
    private function tabs(): array
    {
        $tabs = $this->modules->extensions(SearchTab::class);
        usort($tabs, static fn(SearchTab $a, SearchTab $b): int => $a->order() <=> $b->order());
        $out = [];
        foreach ($tabs as $tab) {
            $key = $tab->key();
            if ($key !== self::SITE && preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $key) === 1 && !isset($out[$key])) {
                $out[$key] = $tab;
            }
        }
        return $out;
    }

    /**
     * "This site": the CMS's own content.
     *
     * @return list<array<string,mixed>>
     */
    private function siteHits(string $q, string $type): array
    {
        $r    = $this->search->search($q, $type, self::SEARCH_LIMIT)->drainTo($this->collector);
        $rows = $r->isOk() ? $r->unwrap() : [];
        $out  = [];
        foreach ($rows as $row) {
            $out[] = (new SearchHit(
                title:      self::plain($row['title']),
                url:        $row['url'],
                displayUrl: $row['url'],
                snippet:    self::plain($row['excerpt']),
                badge:      $this->t->t('search.type.' . $row['type']),
                external:   false,
            ))->view();
        }
        return $out;
    }

    /**
     * Plain text from the index's title or excerpt, which can hold entities and
     * tags (a board post's `&gt;&gt;1`): decoded and stripped here, so the
     * template's escaping shows `>>1` instead of `&gt;&gt;1`.
     */
    private static function plain(string $s): string
    {
        if ($s === '') {
            return '';
        }
        $s = strip_tags(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $collapsed = preg_replace('/\s+/u', ' ', $s);
        return trim(is_string($collapsed) ? $collapsed : $s);
    }

    private function buildTypeOptions(string $selected): void
    {
        $list = [];
        foreach (self::TYPES as $type) {
            $list[] = [
                'value' => $type,
                'label' => $this->t->t('search.type_option.' . $type),
                'sel'   => $type === $selected,
            ];
        }
        $this->ctx->set('types', $list);
    }

    private function setLabels(): void
    {
        foreach ([
            'lbl_heading'     => 'search.heading',
            'lbl_query'       => 'search.query_label',
            'lbl_type'        => 'search.type_label',
            'lbl_submit'      => 'search.submit',
            'lbl_no_results'  => 'search.no_results',
            'lbl_unavailable' => 'search.unavailable',
            'lbl_prev'        => 'search.prev',
            'lbl_next'        => 'search.next',
            'lbl_more'        => 'search.more_options',
            'lbl_more_files'  => 'search.more_files',
        ] as $ctxKey => $tKey) {
            $this->ctx->set($ctxKey, $this->t->t($tKey));
        }
    }
}
