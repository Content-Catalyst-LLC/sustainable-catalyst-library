<?php
error_reporting(E_ALL);

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/sustainable-catalyst-library/includes/class-sc-library-pdf-to-document.php';

if (!defined('SC_Library_PDF_To_Document::META_PDF_ID')) {
    fwrite(STDERR, "FAIL: META_PDF_ID is not defined.\n");
    exit(1);
}

if (!defined('SC_Library_PDF_To_Document::META_SOURCE_ATTACHMENT')) {
    fwrite(STDERR, "FAIL: compatibility alias is not defined.\n");
    exit(1);
}

if (
    SC_Library_PDF_To_Document::META_SOURCE_ATTACHMENT
    !== SC_Library_PDF_To_Document::META_PDF_ID
) {
    fwrite(STDERR, "FAIL: compatibility alias does not match META_PDF_ID.\n");
    exit(1);
}

echo "PASS: PDF source constants resolve to the same metadata key.\n";
