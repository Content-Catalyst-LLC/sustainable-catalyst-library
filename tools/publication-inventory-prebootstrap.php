<?php
/**
 * Require from the dedicated inventory wp-config.php after defining DB_*
 * and before wp-settings.php. It deliberately accepts only a plain,
 * database-wide SELECT grant and global USAGE (no roles or GRANT OPTION).
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'SC_SEO_INVENTORY_APPROVED' )
    || isset( $GLOBALS['wpdb'] ) || defined( 'WPINC' ) ) {
    throw new RuntimeException( 'Expected an approved pre-bootstrap inventory run.' );
}
if ( ! defined( 'DB_HOST' ) || ! defined( 'DB_USER' ) || ! defined( 'DB_PASSWORD' )
    || ! defined( 'DB_NAME' ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', DB_NAME )
    || ! preg_match( '/^([A-Za-z0-9.-]+)(?::([0-9]+))?$/D', DB_HOST, $endpoint ) ) {
    throw new RuntimeException( 'Use dedicated database constants and a plain TCP host for this inventory.' );
}
$database = mysqli_init();
try {
    $database->real_connect( $endpoint[1], DB_USER, DB_PASSWORD, DB_NAME, (int) ( $endpoint[2] ?? 3306 ) );
    $grants = $database->query( 'SHOW GRANTS FOR CURRENT_USER' );
    $schema_grant = 'GRANT SELECT ON `' . DB_NAME . '`.* TO ';
    $select = false;
    foreach ( $grants->fetch_all( MYSQLI_NUM ) as $record ) {
        $grant = $record[0];
        if ( stripos( $grant, 'WITH GRANT OPTION' ) !== false
            || ( ! str_starts_with( $grant, 'GRANT USAGE ON *.* TO ' ) && ! str_starts_with( $grant, $schema_grant ) ) ) {
            throw new RuntimeException( 'Inventory database account has grants beyond the required SELECT scope.' );
        }
        $select = $select || str_starts_with( $grant, $schema_grant );
    }
    if ( ! $select ) {
        throw new RuntimeException( 'Inventory database account is missing the dedicated SELECT grant.' );
    }
    $account = $database->query( 'SELECT CURRENT_USER()' )->fetch_row()[0];
} finally {
    $database->close();
}
define( 'SC_SEO_INVENTORY_VERIFIED_ACCOUNT', $account );
unset( $database, $grants, $schema_grant, $select, $account, $endpoint, $record, $grant );
