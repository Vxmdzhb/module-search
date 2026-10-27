<?php
declare(strict_types=1);

/**
 * Site-wide search — en locale.
 *
 * Loaded by SiteSearchController via load('Search').
 * Keys mirror the it counterpart 1:1 (check_lang_parity.php).
 */
return [
    'search.heading'             => 'Search',
    'search.query_label'         => 'Search terms',
    'search.submit'              => 'Search',
    'search.type_label'          => 'Search in',
    'search.no_results'          => 'No results found.',
    'search.result_count'        => '{count} result(s)',

    // Type-filter dropdown options.
    'search.type_option.all'      => 'Everything',
    'search.type_option.news'     => 'News',
    'search.type_option.pages'    => 'Pages',
    'search.type_option.comments' => 'Comments',
    'search.type_option.board'    => 'Board posts',

    // Per-result type labels (the pill shown next to each hit).
    'search.type.news'     => 'News',
    'search.type.pages'    => 'Page',
    'search.type.comments' => 'Comment',
    'search.type.board'    => 'Board post',

    // Tabs and engine results.
    'search.tab.site'            => 'This site',
    'search.result_count_about'  => 'About {count} result(s)',
    'search.unavailable'         => 'This search engine is not answering right now. Please try again later.',
    'search.prev'                => 'Previous',
    'search.next'                => 'Next',
    'search.more_options'        => 'More options',
    'search.more_files'          => 'more files',
];
