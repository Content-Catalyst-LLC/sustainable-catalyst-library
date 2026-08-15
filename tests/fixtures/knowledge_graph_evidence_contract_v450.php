<?php
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__, 2) . '/sustainable-catalyst-library/includes/class-sc-library-knowledge-graph-evidence-intelligence.php';
echo json_encode(SC_Library_Knowledge_Graph_Evidence_Intelligence::contract(), JSON_UNESCAPED_SLASHES);
