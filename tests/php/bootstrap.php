<?php
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Minimal WP_Error stub for unit tests (no WP bootstrap needed)
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public function __construct(
            public readonly string $code = '',
            public readonly string $message = ''
        ) {}
    }
}

function is_wp_error( mixed $thing ): bool {
    return $thing instanceof WP_Error;
}
