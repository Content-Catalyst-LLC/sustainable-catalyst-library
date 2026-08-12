<?php
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-saved-searches-watchlists-queue.php';
echo json_encode([
    'version' => SC_Library_Saved_Searches_Watchlists_Queue::VERSION,
    'schema' => SC_Library_Saved_Searches_Watchlists_Queue::SCHEMA,
    'searches_meta' => SC_Library_Saved_Searches_Watchlists_Queue::USER_META_SEARCHES,
    'watchlists_meta' => SC_Library_Saved_Searches_Watchlists_Queue::USER_META_WATCHLISTS,
    'queue_meta' => SC_Library_Saved_Searches_Watchlists_Queue::USER_META_QUEUE,
    'rest_route' => SC_Library_Saved_Searches_Watchlists_Queue::REST_ROUTE,
    'max_searches' => SC_Library_Saved_Searches_Watchlists_Queue::MAX_SEARCHES,
    'max_watchlists' => SC_Library_Saved_Searches_Watchlists_Queue::MAX_WATCHLISTS,
    'max_queue' => SC_Library_Saved_Searches_Watchlists_Queue::MAX_QUEUE_ITEMS,
    'search_scopes' => array_keys(SC_Library_Saved_Searches_Watchlists_Queue::search_scopes()),
    'watch_kinds' => array_keys(SC_Library_Saved_Searches_Watchlists_Queue::watch_kinds()),
    'queue_kinds' => array_keys(SC_Library_Saved_Searches_Watchlists_Queue::queue_kinds()),
    'contract' => SC_Library_Saved_Searches_Watchlists_Queue::continuity_contract(),
], JSON_UNESCAPED_SLASHES);
