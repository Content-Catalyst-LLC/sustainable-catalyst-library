<?php
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-personal-collections-recommendations.php';
$out = array(
    'version' => SC_Library_Personal_Collections_Recommendations::VERSION,
    'schema' => SC_Library_Personal_Collections_Recommendations::SCHEMA,
    'items_meta' => SC_Library_Personal_Collections_Recommendations::USER_META_ITEMS,
    'collections_meta' => SC_Library_Personal_Collections_Recommendations::USER_META_COLLECTIONS,
    'rest_route' => SC_Library_Personal_Collections_Recommendations::REST_ROUTE,
    'max_items' => SC_Library_Personal_Collections_Recommendations::MAX_ITEMS,
    'types' => array_keys(SC_Library_Personal_Collections_Recommendations::types()),
    'relationships' => array_keys(SC_Library_Personal_Collections_Recommendations::relationships()),
    'statuses' => array_keys(SC_Library_Personal_Collections_Recommendations::statuses()),
    'separation' => SC_Library_Personal_Collections_Recommendations::editorial_separation_contract(),
);
echo json_encode($out, JSON_UNESCAPED_SLASHES);
