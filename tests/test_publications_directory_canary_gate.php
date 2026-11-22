<?php
/**
 * PUB-RELEASE-01: isolated WordPress environment/feature-flag matrix.
 * Run as an independent PHP process for each case because PHP constants
 * are immutable. The directory does not access WordPress until invoked.
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
$env = (string) getenv( 'PUB_TEST_WP_ENV' );
if ( 'unset' !== getenv( 'PUB_TEST_BASE_FLAG' ) ) {
    define( 'SC_LIBRARY_PUBLICATIONS_DIRECTORY', getenv( 'PUB_TEST_BASE_FLAG' ) === '1' );
}
if ( 'unset' !== getenv( 'PUB_TEST_PROD_FLAG' ) ) {
    define(
        'SC_LIBRARY_PUBLICATIONS_DIRECTORY_PRODUCTION_RELEASE',
        getenv( 'PUB_TEST_PROD_FLAG' ) === '1'
    );
}
function wp_get_environment_type(): string {
    return (string) getenv( 'PUB_TEST_WP_ENV' );
}
require __DIR__ . '/../sustainable-catalyst-library/includes/class-sc-library-publications-directory.php';
echo SC_Library_Publications_Directory::enabled() ? "enabled\n" : "disabled\n";
