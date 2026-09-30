<?php
if (!defined('ABSPATH')) { exit; }

final class SC_Library_Identity_Access {
    public const ROLE = 'optional-identity-bridge';
    public const AUTHORITATIVE = false;

    public static function contract(): array {
        return [
            'schema' => 'sc-library-wordpress-identity-adapter/1.0',
            'library_version' => SC_LIBRARY_VERSION,
            'wordpress_role' => self::ROLE,
            'wordpress_identity_authoritative' => false,
            'wordpress_cookie_is_library_session' => false,
            'library_session_authority' => 'library-service',
            'library_api' => '/api/library/v1',
        ];
    }
}
