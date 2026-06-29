<?php
/**
 * Plugin Name:       ROA Folder Manager
 * Plugin URI:        https://github.com/delharris1981/ROA-folder-manager
 * Description:       Manage real on-disk folders in the WordPress Media Library.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Derek Harris
 * Author URI:        https://github.com/delharris1981
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       roa-folder-manager
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MEDIA_FOLDERS_VERSION', '1.0.0' );
define( 'MEDIA_FOLDERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIA_FOLDERS_URL', plugin_dir_url( __FILE__ ) );

require_once MEDIA_FOLDERS_PATH . 'vendor/autoload.php';

( new MediaFolders\Plugin() )->init();
