# ROA Folder Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that adds a real on-disk folder tree to the Media Library, enabling nested folder creation, upload routing, file moving (with thumbnail + DB sync), and safe folder deletion.

**Architecture:** OOP PHP with `MediaFolders\` PSR-4 namespace handles all filesystem and DB operations via five REST endpoints. A React frontend (built with `@wordpress/scripts`, compiled assets committed to repo) injects a folder panel into `upload.php` and the media modal. GitHub Actions runs parallel test+build jobs on every tag push, bumps the version string, and creates a GitHub Release with a plugin zip.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, Composer (PSR-4 autoload + PHPUnit 10), `@wordpress/scripts` (webpack + Jest), `@wordpress/components`, `@wordpress/element`, GitHub Actions.

## Global Constraints

- Minimum WordPress: 6.0
- Minimum PHP: 8.0
- Plugin slug / text domain: `roa-folder-manager`
- Plugin class namespace: `MediaFolders\`
- REST namespace: `media-folders/v1`
- All paths validated to stay within `wp_upload_dir()['basedir']` — path traversal returns `403`
- No year/month subfolders inside plugin-managed folders (files land directly in selected folder)
- `build/` directory committed to repo; `src/`, `node_modules/`, `vendor/` excluded from release zip
- Semantic versioning; version stored in plugin header `Version:` line, `readme.txt` `Stable tag:`, and `MEDIA_FOLDERS_VERSION` constant — all three bumped atomically by release workflow
- GitHub repo: `https://github.com/delharris1981/ROA-folder-manager`

---

## File Map

```
media-folders/
├── media-folders.php                    # Bootstrap: constants + new Plugin()->init()
├── readme.txt                           # WordPress.org readme (Stable tag bumped on release)
├── composer.json                        # PSR-4 autoload + phpunit dev dep
├── package.json                         # @wordpress/scripts build + test
├── phpunit.xml                          # PHPUnit config, bootstrap = tests/php/bootstrap.php
├── .gitignore
├── includes/
│   ├── Plugin.php                       # Orchestrator: wires all classes + hooks
│   ├── Admin.php                        # Enqueue scripts, media library query filter
│   ├── FolderManager.php                # Filesystem CRUD, path validation
│   ├── AttachmentMover.php              # Move file + thumbs, update meta + post URLs
│   └── RestAPI.php                      # Register 5 REST endpoints
├── src/
│   ├── index.js                         # React entry: mounts FolderPanel on upload.php
│   ├── api.js                           # fetch helpers for all 5 REST endpoints
│   └── components/
│       ├── FolderPanel.js               # Root state: tree, error, deleteTarget
│       ├── FolderTree.js                # Recursive folder tree renderer
│       ├── FolderNode.js                # Single node: click, drag-drop, context menu
│       ├── AddFolderButton.js           # Inline input → POST /folders
│       ├── DeleteFolderDialog.js        # Move-or-delete choice + confirmation
│       └── __tests__/
│           └── FolderTree.test.js       # Render + click + drag-drop assertions
├── tests/
│   └── php/
│       ├── bootstrap.php                # Loads composer autoloader
│       └── FolderManagerTest.php        # Pure filesystem tests (no WP bootstrap)
└── .github/
    └── workflows/
        ├── ci.yml                       # PR: parallel test-php + test-js
        └── release.yml                  # Tag: parallel test+build → version bump → zip → GH Release
```

---

### Task 1: Project Scaffold

**Files:**
- Create: `media-folders.php`
- Create: `readme.txt`
- Create: `composer.json`
- Create: `package.json`
- Create: `phpunit.xml`
- Create: `.gitignore`

**Interfaces:**
- Produces: `MEDIA_FOLDERS_VERSION`, `MEDIA_FOLDERS_PATH`, `MEDIA_FOLDERS_URL` constants; `MediaFolders\Plugin` instantiation; composer autoloader; npm build/test scripts

- [ ] **Step 1: Create the plugin bootstrap file**

```php
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
```

Save to: `media-folders.php`

- [ ] **Step 2: Create readme.txt**

```
=== ROA Folder Manager ===
Contributors: delharris1981
Tags: media, folders, uploads, library
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage real on-disk folders in the WordPress Media Library.

== Description ==

ROA Folder Manager adds a folder tree to the Media Library. Create nested folders, upload directly into them, move files between folders. All folders are real directories on disk.

== Changelog ==

= 1.0.0 =
* Initial release
```

Save to: `readme.txt`

- [ ] **Step 3: Create composer.json**

```json
{
    "name": "delharris1981/roa-folder-manager",
    "description": "WordPress plugin for real on-disk media folder management",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "autoload": {
        "psr-4": {
            "MediaFolders\\": "includes/"
        }
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "scripts": {
        "test": "vendor/bin/phpunit"
    }
}
```

Save to: `composer.json`

- [ ] **Step 4: Create package.json**

```json
{
    "name": "roa-folder-manager",
    "version": "1.0.0",
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start",
        "test": "wp-scripts test-unit-js"
    },
    "devDependencies": {
        "@wordpress/scripts": "^30.0"
    }
}
```

Save to: `package.json`

- [ ] **Step 5: Create phpunit.xml**

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/php/bootstrap.php"
    colors="true"
    cacheResultFile=".phpunit.cache"
>
    <testsuites>
        <testsuite name="ROA Folder Manager">
            <directory suffix="Test.php">tests/php</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Save to: `phpunit.xml`

- [ ] **Step 6: Create .gitignore**

```
/vendor/
/node_modules/
.phpunit.cache
*.zip
```

Save to: `.gitignore`

- [ ] **Step 7: Install dependencies**

```bash
composer install
npm install
```

Expected: `vendor/` created with phpunit; `node_modules/` created with @wordpress/scripts.

- [ ] **Step 8: Commit**

```bash
git add media-folders.php readme.txt composer.json composer.lock package.json package-lock.json phpunit.xml .gitignore
git commit -m "feat: project scaffold — plugin header, composer, npm"
```

---

### Task 2: Plugin Orchestrator + Admin

**Files:**
- Create: `includes/Plugin.php`
- Create: `includes/Admin.php`

**Interfaces:**
- Consumes: `MEDIA_FOLDERS_PATH`, `MEDIA_FOLDERS_URL`, `MEDIA_FOLDERS_VERSION` constants (from Task 1)
- Produces: `MediaFolders\Plugin::init()` — registers all hooks; `MediaFolders\Admin::register_hooks()` — enqueues scripts + registers query filter

- [ ] **Step 1: Create Plugin.php**

```php
<?php

declare( strict_types=1 );

namespace MediaFolders;

class Plugin {

    public function init(): void {
        $upload_dir      = wp_upload_dir();
        $folder_manager  = new FolderManager( $upload_dir['basedir'] );
        $attachment_mover = new AttachmentMover();
        $rest_api        = new RestAPI( $folder_manager, $attachment_mover );
        $admin           = new Admin();

        add_action( 'rest_api_init', [ $rest_api, 'register_routes' ] );
        $admin->register_hooks();
    }
}
```

Save to: `includes/Plugin.php`

- [ ] **Step 2: Create Admin.php**

```php
<?php

declare( strict_types=1 );

namespace MediaFolders;

class Admin {

    public function register_hooks(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'ajax_query_attachments_args', [ $this, 'filter_attachments_by_folder' ] );
    }

    public function enqueue_scripts( string $hook ): void {
        if ( 'upload.php' !== $hook && ! did_action( 'wp_enqueue_media' ) ) {
            return;
        }

        $asset_file = MEDIA_FOLDERS_PATH . 'build/index.asset.php';
        $asset      = file_exists( $asset_file ) ? require $asset_file : [ 'dependencies' => [], 'version' => MEDIA_FOLDERS_VERSION ];

        wp_enqueue_script(
            'roa-folder-manager',
            MEDIA_FOLDERS_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'roa-folder-manager',
            MEDIA_FOLDERS_URL . 'build/index.css',
            [],
            $asset['version']
        );

        wp_localize_script( 'roa-folder-manager', 'mediaFolders', [
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'restBase' => rest_url( 'media-folders/v1' ),
        ] );
    }

    public function filter_attachments_by_folder( array $query ): array {
        $folder = sanitize_text_field( wp_unslash( $_REQUEST['query']['media_folder'] ?? '' ) );

        if ( '' === $folder ) {
            return $query;
        }

        // Validate: no traversal, relative path only
        if ( str_contains( $folder, '..' ) || str_starts_with( $folder, '/' ) ) {
            return $query;
        }

        $query['meta_query'] = [
            [
                'key'     => '_wp_attached_file',
                'value'   => $folder . '/',
                'compare' => 'LIKE',
            ],
        ];

        return $query;
    }
}
```

Save to: `includes/Admin.php`

- [ ] **Step 3: Commit**

```bash
git add includes/Plugin.php includes/Admin.php
git commit -m "feat: Plugin orchestrator and Admin script enqueue + folder query filter"
```

---

### Task 3: Folder Manager + PHPUnit Tests

**Files:**
- Create: `includes/FolderManager.php`
- Create: `tests/php/bootstrap.php`
- Create: `tests/php/FolderManagerTest.php`

**Interfaces:**
- Consumes: `$base_dir` (absolute path to `uploads/` basedir) via constructor
- Produces:
  - `FolderManager::get_tree(): array` — recursive `[['name'=>string, 'path'=>string, 'children'=>array]]`
  - `FolderManager::create(string $relative_path): bool|\WP_Error`
  - `FolderManager::rename(string $relative_path, string $new_name): bool|\WP_Error`
  - `FolderManager::delete_empty(string $relative_path): bool|\WP_Error`
  - `FolderManager::validate_path(string $relative_path): string|\WP_Error` — returns absolute path or WP_Error

- [ ] **Step 1: Create test bootstrap**

```php
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
```

Save to: `tests/php/bootstrap.php`

- [ ] **Step 2: Write the failing tests**

```php
<?php

declare( strict_types=1 );

use MediaFolders\FolderManager;
use PHPUnit\Framework\TestCase;

class FolderManagerTest extends TestCase {

    private string $base_dir;
    private FolderManager $manager;

    protected function setUp(): void {
        $this->base_dir = sys_get_temp_dir() . '/roa-test-' . uniqid();
        mkdir( $this->base_dir, 0755, true );
        $this->manager = new FolderManager( $this->base_dir );
    }

    protected function tearDown(): void {
        $this->remove_dir( $this->base_dir );
    }

    public function test_create_makes_directory(): void {
        $result = $this->manager->create( 'portraits' );
        $this->assertTrue( $result );
        $this->assertDirectoryExists( $this->base_dir . '/portraits' );
    }

    public function test_create_returns_error_if_exists(): void {
        mkdir( $this->base_dir . '/portraits' );
        $result = $this->manager->create( 'portraits' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'folder_exists', $result->code );
    }

    public function test_create_rejects_traversal(): void {
        $result = $this->manager->create( '../escape' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'invalid_path', $result->code );
    }

    public function test_rename_renames_directory(): void {
        mkdir( $this->base_dir . '/old-name' );
        $result = $this->manager->rename( 'old-name', 'new-name' );
        $this->assertTrue( $result );
        $this->assertDirectoryExists( $this->base_dir . '/new-name' );
        $this->assertDirectoryDoesNotExist( $this->base_dir . '/old-name' );
    }

    public function test_rename_rejects_traversal_in_new_name(): void {
        mkdir( $this->base_dir . '/folder' );
        $result = $this->manager->rename( 'folder', '../escape' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'invalid_path', $result->code );
    }

    public function test_delete_empty_removes_directory(): void {
        mkdir( $this->base_dir . '/empty-folder' );
        $result = $this->manager->delete_empty( 'empty-folder' );
        $this->assertTrue( $result );
        $this->assertDirectoryDoesNotExist( $this->base_dir . '/empty-folder' );
    }

    public function test_delete_empty_fails_if_not_empty(): void {
        mkdir( $this->base_dir . '/has-files' );
        touch( $this->base_dir . '/has-files/file.jpg' );
        $result = $this->manager->delete_empty( 'has-files' );
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'folder_not_empty', $result->code );
    }

    public function test_get_tree_returns_nested_structure(): void {
        mkdir( $this->base_dir . '/portraits', 0755, true );
        mkdir( $this->base_dir . '/portraits/2024', 0755, true );
        mkdir( $this->base_dir . '/products', 0755, true );

        $tree = $this->manager->get_tree();

        $this->assertCount( 2, $tree );
        $names = array_column( $tree, 'name' );
        $this->assertContains( 'portraits', $names );
        $this->assertContains( 'products', $names );

        $portraits = array_values( array_filter( $tree, fn( $n ) => $n['name'] === 'portraits' ) )[0];
        $this->assertCount( 1, $portraits['children'] );
        $this->assertSame( '2024', $portraits['children'][0]['name'] );
        $this->assertSame( 'portraits/2024', $portraits['children'][0]['path'] );
    }

    public function test_validate_path_rejects_traversal(): void {
        $result = $this->manager->validate_path( '../outside' );
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    public function test_validate_path_returns_absolute_path(): void {
        $result = $this->manager->validate_path( 'portraits' );
        $this->assertSame( $this->base_dir . '/portraits', $result );
    }

    private function remove_dir( string $dir ): void {
        if ( ! is_dir( $dir ) ) return;
        $items = array_diff( scandir( $dir ), [ '.', '..' ] );
        foreach ( $items as $item ) {
            $path = $dir . '/' . $item;
            is_dir( $path ) ? $this->remove_dir( $path ) : unlink( $path );
        }
        rmdir( $dir );
    }
}
```

Save to: `tests/php/FolderManagerTest.php`

- [ ] **Step 3: Run tests — verify they fail**

```bash
vendor/bin/phpunit
```

Expected: `Error: Class "MediaFolders\FolderManager" not found`

- [ ] **Step 4: Implement FolderManager.php**

```php
<?php

declare( strict_types=1 );

namespace MediaFolders;

class FolderManager {

    private string $base_dir;

    public function __construct( string $base_dir ) {
        $this->base_dir = rtrim( $base_dir, '/' );
    }

    public function get_tree( string $relative_root = '' ): array {
        $abs_root = '' === $relative_root
            ? $this->base_dir
            : $this->base_dir . '/' . $relative_root;

        if ( ! is_dir( $abs_root ) ) {
            return [];
        }

        $entries = array_diff( scandir( $abs_root ), [ '.', '..' ] );
        $nodes   = [];

        foreach ( $entries as $entry ) {
            $abs_path = $abs_root . '/' . $entry;
            if ( ! is_dir( $abs_path ) ) {
                continue;
            }
            $rel_path = '' === $relative_root ? $entry : $relative_root . '/' . $entry;
            $nodes[]  = [
                'name'     => $entry,
                'path'     => $rel_path,
                'children' => $this->get_tree( $rel_path ),
            ];
        }

        return $nodes;
    }

    public function create( string $relative_path ): bool|\WP_Error {
        $abs_path = $this->validate_path( $relative_path );
        if ( is_wp_error( $abs_path ) ) {
            return $abs_path;
        }

        if ( is_dir( $abs_path ) ) {
            return new \WP_Error( 'folder_exists', 'A folder with that name already exists.' );
        }

        if ( ! mkdir( $abs_path, 0755, true ) ) {
            return new \WP_Error( 'permission_denied', 'Could not create folder.' );
        }

        return true;
    }

    public function rename( string $relative_path, string $new_name ): bool|\WP_Error {
        if ( str_contains( $new_name, '/' ) || str_contains( $new_name, '..' ) ) {
            return new \WP_Error( 'invalid_path', 'Invalid folder name.' );
        }

        $abs_source = $this->validate_path( $relative_path );
        if ( is_wp_error( $abs_source ) ) {
            return $abs_source;
        }

        $abs_dest = dirname( $abs_source ) . '/' . $new_name;

        // Verify dest stays within base_dir
        if ( ! str_starts_with( realpath( dirname( $abs_dest ) ) . '/', $this->base_dir . '/' ) ) {
            return new \WP_Error( 'invalid_path', 'Destination is outside uploads directory.' );
        }

        if ( ! rename( $abs_source, $abs_dest ) ) {
            return new \WP_Error( 'move_failed', 'Could not rename folder.' );
        }

        return true;
    }

    /**
     * Removes the directory only if it is already empty.
     * The REST API handler is responsible for moving/deleting contents first.
     */
    public function delete_empty( string $relative_path ): bool|\WP_Error {
        $abs_path = $this->validate_path( $relative_path );
        if ( is_wp_error( $abs_path ) ) {
            return $abs_path;
        }

        $contents = array_diff( scandir( $abs_path ), [ '.', '..' ] );
        if ( ! empty( $contents ) ) {
            return new \WP_Error( 'folder_not_empty', 'Folder must be emptied before deletion.' );
        }

        if ( ! rmdir( $abs_path ) ) {
            return new \WP_Error( 'delete_failed', 'Could not delete folder.' );
        }

        return true;
    }

    public function validate_path( string $relative_path ): string|\WP_Error {
        if ( str_contains( $relative_path, '..' ) || str_starts_with( $relative_path, '/' ) ) {
            return new \WP_Error( 'invalid_path', 'Invalid path.' );
        }

        $abs_path = $this->base_dir . '/' . ltrim( $relative_path, '/' );

        // Resolve any symlinks in the base_dir for accurate prefix check
        $real_base = realpath( $this->base_dir );
        $real_path = realpath( dirname( $abs_path ) );

        // dirname may not exist yet (for new folders); check parent exists within base
        if ( false !== $real_path && ! str_starts_with( $real_path . '/', $real_base . '/' ) ) {
            return new \WP_Error( 'invalid_path', 'Path is outside uploads directory.' );
        }

        return $abs_path;
    }
}
```

Save to: `includes/FolderManager.php`

- [ ] **Step 5: Run tests — verify they pass**

```bash
vendor/bin/phpunit
```

Expected: `OK (9 tests, 12 assertions)`

- [ ] **Step 6: Commit**

```bash
git add includes/FolderManager.php tests/php/bootstrap.php tests/php/FolderManagerTest.php
git commit -m "feat: FolderManager filesystem CRUD with PHPUnit tests"
```

---

### Task 4: Attachment Mover

**Files:**
- Create: `includes/AttachmentMover.php`

**Interfaces:**
- Produces:
  - `AttachmentMover::move(int $attachment_id, string $destination_relative_path): bool|\WP_Error`
    - Moves file + all generated sizes on disk
    - Updates `_wp_attached_file`, `_wp_attachment_metadata` post meta
    - Updates attachment `guid`
    - Replaces old URL in `post_content` for up to 500 published posts (best-effort)

> Note: `AttachmentMover` calls WP functions (`get_post_meta`, `wp_update_attachment_metadata`, etc.) and cannot be unit-tested without a WP installation. Integration tests require `wp-env` or similar. Manual testing checklist is in `docs/testing.md`.

- [ ] **Step 1: Create AttachmentMover.php**

```php
<?php

declare( strict_types=1 );

namespace MediaFolders;

class AttachmentMover {

    public function move( int $attachment_id, string $destination_relative_path ): bool|\WP_Error {
        $upload_dir  = wp_upload_dir();
        $base_dir    = $upload_dir['basedir'];
        $base_url    = $upload_dir['baseurl'];

        // Validate destination stays within uploads
        if ( str_contains( $destination_relative_path, '..' ) ) {
            return new \WP_Error( 'invalid_path', 'Invalid destination path.' );
        }

        $dest_abs = $base_dir . '/' . ltrim( $destination_relative_path, '/' );

        if ( ! is_dir( $dest_abs ) ) {
            return new \WP_Error( 'destination_missing', 'Destination folder does not exist.' );
        }

        $current_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
        if ( ! $current_relative ) {
            return new \WP_Error( 'attachment_missing', 'Attachment file meta not found.' );
        }

        $current_abs = $base_dir . '/' . $current_relative;
        $filename    = basename( $current_abs );
        $new_abs     = $dest_abs . '/' . $filename;
        $new_relative = ltrim( $destination_relative_path, '/' ) . '/' . $filename;

        if ( ! file_exists( $current_abs ) ) {
            return new \WP_Error( 'file_missing', 'Source file does not exist on disk.' );
        }

        // Move original
        if ( ! rename( $current_abs, $new_abs ) ) {
            return new \WP_Error( 'move_failed', 'Could not move file.' );
        }

        // Move all generated thumbnail sizes (best-effort)
        $metadata    = wp_get_attachment_metadata( $attachment_id );
        $old_dir_abs = dirname( $current_abs );

        if ( ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size => $size_data ) {
                $old_thumb = $old_dir_abs . '/' . $size_data['file'];
                $new_thumb = $dest_abs . '/' . $size_data['file'];
                if ( file_exists( $old_thumb ) ) {
                    rename( $old_thumb, $new_thumb );
                }
            }
        }

        // Update attachment meta
        if ( is_array( $metadata ) ) {
            $metadata['file'] = $new_relative;
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }
        update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );

        // Update guid
        $old_url = $base_url . '/' . $current_relative;
        $new_url = $base_url . '/' . $new_relative;
        wp_update_post( [
            'ID'   => $attachment_id,
            'guid' => $new_url,
        ] );

        // Update post_content URLs (best-effort, capped at 500 posts)
        $this->replace_urls_in_posts( $old_url, $new_url );

        return true;
    }

    private function replace_urls_in_posts( string $old_url, string $new_url ): void {
        global $wpdb;

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_content LIKE %s
               AND post_status = 'publish'
             LIMIT 500",
            '%' . $wpdb->esc_like( $old_url ) . '%'
        ) );

        foreach ( $post_ids as $post_id ) {
            $post            = get_post( (int) $post_id );
            $updated_content = str_replace( $old_url, $new_url, $post->post_content );
            wp_update_post( [
                'ID'           => (int) $post_id,
                'post_content' => $updated_content,
            ] );
        }
    }
}
```

Save to: `includes/AttachmentMover.php`

- [ ] **Step 2: Commit**

```bash
git add includes/AttachmentMover.php
git commit -m "feat: AttachmentMover — move file + thumbnails, sync meta + post content URLs"
```

---

### Task 5: REST API

**Files:**
- Create: `includes/RestAPI.php`

**Interfaces:**
- Consumes: `FolderManager` (Task 3), `AttachmentMover` (Task 4)
- Produces: Five endpoints under `/wp-json/media-folders/v1/`:
  - `GET /folders`
  - `POST /folders`
  - `PATCH /folders`
  - `DELETE /folders`
  - `POST /attachments/(?P<id>\d+)/move`

- [ ] **Step 1: Create RestAPI.php**

```php
<?php

declare( strict_types=1 );

namespace MediaFolders;

class RestAPI {

    public function __construct(
        private FolderManager $folder_manager,
        private AttachmentMover $attachment_mover
    ) {}

    public function register_routes(): void {
        register_rest_route( 'media-folders/v1', '/folders', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_folders' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_folder' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'path'        => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'parent_path' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'rename_folder' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'path'     => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'new_name' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_folder' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'path'             => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                    'action'           => [ 'required' => true, 'type' => 'string', 'enum' => [ 'move', 'delete' ] ],
                    'destination_path' => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );

        register_rest_route( 'media-folders/v1', '/attachments/(?P<id>\d+)/move', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'move_attachment' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'id'               => [ 'required' => true, 'type' => 'integer' ],
                'destination_path' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    public function check_permission(): bool|\WP_Error {
        if ( ! current_user_can( 'upload_files' ) ) {
            return new \WP_Error( 'rest_forbidden', 'You do not have permission to manage media folders.', [ 'status' => 403 ] );
        }
        return true;
    }

    public function get_folders(): \WP_REST_Response {
        return new \WP_REST_Response( $this->folder_manager->get_tree(), 200 );
    }

    public function create_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $parent = $request->get_param( 'parent_path' );
        $name   = $request->get_param( 'path' );
        $rel    = '' !== $parent ? $parent . '/' . $name : $name;

        $result = $this->folder_manager->create( $rel );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [ 'path' => $rel ], 201 );
    }

    public function rename_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $result = $this->folder_manager->rename(
            $request->get_param( 'path' ),
            $request->get_param( 'new_name' )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [ 'renamed' => true ], 200 );
    }

    public function delete_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $path   = $request->get_param( 'path' );
        $action = $request->get_param( 'action' );
        $dest   = $request->get_param( 'destination_path' );

        // Collect all attachments in this folder (and subfolders) via meta query
        $attachments = get_posts( [
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_wp_attached_file',
                    'value'   => $path . '/',
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        if ( 'move' === $action ) {
            foreach ( $attachments as $id ) {
                $result = $this->attachment_mover->move( $id, $dest );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
            }
        } else {
            foreach ( $attachments as $id ) {
                wp_delete_attachment( $id, true );
            }
        }

        $result = $this->folder_manager->delete_empty( $path );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [ 'deleted' => true ], 200 );
    }

    public function move_attachment( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $result = $this->attachment_mover->move(
            $request->get_param( 'id' ),
            $request->get_param( 'destination_path' )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [ 'moved' => true ], 200 );
    }
}
```

Save to: `includes/RestAPI.php`

- [ ] **Step 2: Commit**

```bash
git add includes/RestAPI.php
git commit -m "feat: REST API — 5 endpoints for folder CRUD and attachment move"
```

---

### Task 6: React Foundation — api.js + index.js + FolderPanel

**Files:**
- Create: `src/api.js`
- Create: `src/index.js`
- Create: `src/components/FolderPanel.js`
- Create: `src/index.css`

**Interfaces:**
- Consumes: `window.mediaFolders.nonce`, `window.mediaFolders.restBase` (set by Admin.php Task 2)
- Produces:
  - `api.js` exports: `getFolders()`, `createFolder(path, parentPath)`, `renameFolder(path, newName)`, `deleteFolder(path, action, destinationPath)`, `moveAttachment(id, destinationPath)` — all return Promises
  - `FolderPanel` props: none (manages own state; reads `window.mediaFolders`)
  - `FolderPanel` passes down to children: `tree`, `onSelect(path)`, `onMove(attachmentId, destPath)`, `onAdd(name, parentPath)`, `onRename(path, newName)`, `onDeleteRequest(path)`

- [ ] **Step 1: Create src/api.js**

```js
const { nonce, restBase } = window.mediaFolders;

async function apiFetch( path, options = {} ) {
    const res = await fetch( restBase + path, {
        headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
        },
        ...options,
    } );
    const data = await res.json();
    if ( ! res.ok ) throw data;
    return data;
}

export const getFolders = () => apiFetch( '/folders' );

export const createFolder = ( path, parentPath = '' ) =>
    apiFetch( '/folders', {
        method: 'POST',
        body: JSON.stringify( { path, parent_path: parentPath } ),
    } );

export const renameFolder = ( path, newName ) =>
    apiFetch( '/folders', {
        method: 'PATCH',
        body: JSON.stringify( { path, new_name: newName } ),
    } );

export const deleteFolder = ( path, action, destinationPath = '' ) =>
    apiFetch( '/folders', {
        method: 'DELETE',
        body: JSON.stringify( { path, action, destination_path: destinationPath } ),
    } );

export const moveAttachment = ( id, destinationPath ) =>
    apiFetch( `/attachments/${ id }/move`, {
        method: 'POST',
        body: JSON.stringify( { destination_path: destinationPath } ),
    } );
```

Save to: `src/api.js`

- [ ] **Step 2: Create src/index.css**

```css
#media-folders-panel {
    width: 220px;
    flex-shrink: 0;
    border-right: 1px solid #dcdcde;
    padding: 12px 8px;
    overflow-y: auto;
    background: #f6f7f7;
}

.media-folders-layout {
    display: flex;
    align-items: flex-start;
}

.media-folders-layout #wp-media-grid {
    flex: 1;
    min-width: 0;
}
```

Save to: `src/index.css`

- [ ] **Step 3: Create src/components/FolderPanel.js**

```jsx
import { useState, useEffect } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import FolderTree from './FolderTree';
import AddFolderButton from './AddFolderButton';
import DeleteFolderDialog from './DeleteFolderDialog';
import { getFolders, createFolder, renameFolder, deleteFolder, moveAttachment } from '../api';

export default function FolderPanel() {
    const [ tree, setTree ]               = useState( [] );
    const [ loading, setLoading ]         = useState( true );
    const [ error, setError ]             = useState( null );
    const [ deleteTarget, setDeleteTarget ] = useState( null );

    function loadTree() {
        setLoading( true );
        getFolders()
            .then( setTree )
            .catch( ( e ) => setError( e?.message || 'Failed to load folders.' ) )
            .finally( () => setLoading( false ) );
    }

    useEffect( () => { loadTree(); }, [] );

    function handleSelect( folderPath ) {
        if ( ! window.wp?.media?.frame ) return;
        const library = window.wp.media.frame.state().get( 'library' );
        if ( library ) {
            library.props.set( { media_folder: folderPath } );
            library.reset();
            library.more();
        }
    }

    function handleMove( attachmentId, destPath ) {
        moveAttachment( attachmentId, destPath )
            .then( loadTree )
            .catch( ( e ) => setError( e?.message || 'Failed to move file.' ) );
    }

    function handleAdd( name, parentPath ) {
        createFolder( name, parentPath )
            .then( loadTree )
            .catch( ( e ) => setError( e?.message || 'Failed to create folder.' ) );
    }

    function handleRename( path, newName ) {
        renameFolder( path, newName )
            .then( loadTree )
            .catch( ( e ) => setError( e?.message || 'Failed to rename folder.' ) );
    }

    function handleDeleteRequest( path ) {
        setDeleteTarget( path );
    }

    function handleDeleteConfirm( path, action, destinationPath ) {
        setDeleteTarget( null );
        deleteFolder( path, action, destinationPath )
            .then( loadTree )
            .catch( ( e ) => setError( e?.message || 'Failed to delete folder.' ) );
    }

    return (
        <div className="media-folders-panel-inner">
            <strong style={ { display: 'block', marginBottom: 8 } }>Folders</strong>

            { error && (
                <Notice status="error" isDismissible onRemove={ () => setError( null ) }>
                    { error }
                </Notice>
            ) }

            { loading ? (
                <Spinner />
            ) : (
                <FolderTree
                    tree={ tree }
                    onSelect={ handleSelect }
                    onMove={ handleMove }
                    onRename={ handleRename }
                    onDelete={ handleDeleteRequest }
                />
            ) }

            <AddFolderButton onAdd={ handleAdd } />

            { deleteTarget && (
                <DeleteFolderDialog
                    path={ deleteTarget }
                    tree={ tree }
                    onConfirm={ handleDeleteConfirm }
                    onCancel={ () => setDeleteTarget( null ) }
                />
            ) }
        </div>
    );
}
```

Save to: `src/components/FolderPanel.js`

- [ ] **Step 4: Create src/index.js**

```jsx
import { render } from '@wordpress/element';
import FolderPanel from './components/FolderPanel';
import './index.css';

// Mount folder panel on upload.php — insert before the media grid
const grid = document.getElementById( 'wp-media-grid' );
if ( grid ) {
    const panel = document.createElement( 'div' );
    panel.id = 'media-folders-panel';

    // Wrap grid + panel in a flex layout container
    const wrapper = document.createElement( 'div' );
    wrapper.className = 'media-folders-layout';
    grid.parentNode.insertBefore( wrapper, grid );
    wrapper.appendChild( panel );
    wrapper.appendChild( grid );

    render( <FolderPanel />, panel );
}
```

Save to: `src/index.js`

- [ ] **Step 5: Commit**

```bash
git add src/api.js src/index.js src/index.css src/components/FolderPanel.js
git commit -m "feat: React foundation — api layer, FolderPanel state, DOM injection"
```

---

### Task 7: FolderTree + FolderNode + Jest Tests

**Files:**
- Create: `src/components/FolderTree.js`
- Create: `src/components/FolderNode.js`
- Create: `src/components/__tests__/FolderTree.test.js`

**Interfaces:**
- Consumes (FolderTree props): `tree: array`, `onSelect(path)`, `onMove(attachmentId, destPath)`, `onRename(path, newName)`, `onDelete(path)`, `depth?: number`
- Consumes (FolderNode props): `node: {name, path, children}`, `onSelect`, `onMove`, `onRename`, `onDelete`, `depth`
- Produces: Rendered nested `<ul>/<li>` tree; drag-over + drop handlers that call `onMove(attachmentId, destPath)`

- [ ] **Step 1: Write failing Jest tests**

```jsx
// src/components/__tests__/FolderTree.test.js
import { render, screen, fireEvent } from '@testing-library/react';
import FolderTree from '../FolderTree';

const noop = () => {};

const tree = [
    {
        name: 'portraits',
        path: 'portraits',
        children: [
            { name: '2024', path: 'portraits/2024', children: [] },
        ],
    },
    { name: 'products', path: 'products', children: [] },
];

test( 'renders top-level folder names', () => {
    render( <FolderTree tree={ tree } onSelect={ noop } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
    expect( screen.getByText( 'portraits' ) ).toBeInTheDocument();
    expect( screen.getByText( 'products' ) ).toBeInTheDocument();
} );

test( 'renders nested child folder', () => {
    render( <FolderTree tree={ tree } onSelect={ noop } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
    expect( screen.getByText( '2024' ) ).toBeInTheDocument();
} );

test( 'calls onSelect with folder path when folder name is clicked', () => {
    const onSelect = jest.fn();
    render( <FolderTree tree={ tree } onSelect={ onSelect } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
    fireEvent.click( screen.getByText( 'products' ) );
    expect( onSelect ).toHaveBeenCalledWith( 'products' );
} );

test( 'calls onMove with attachment id and folder path on drop', () => {
    const onMove = jest.fn();
    render( <FolderTree tree={ tree } onSelect={ noop } onMove={ onMove } onRename={ noop } onDelete={ noop } /> );
    const folder = screen.getByText( 'products' );
    fireEvent.drop( folder, {
        dataTransfer: { getData: () => '42' },
    } );
    expect( onMove ).toHaveBeenCalledWith( 42, 'products' );
} );
```

Save to: `src/components/__tests__/FolderTree.test.js`

- [ ] **Step 2: Run tests — verify they fail**

```bash
npm test -- --watchAll=false
```

Expected: `Cannot find module '../FolderTree'`

- [ ] **Step 3: Create FolderNode.js**

```jsx
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import FolderTree from './FolderTree';

export default function FolderNode( { node, onSelect, onMove, onRename, onDelete, depth } ) {
    const [ dragOver, setDragOver ] = useState( false );
    const [ editing, setEditing ]   = useState( false );
    const [ editName, setEditName ] = useState( node.name );

    function handleDrop( e ) {
        e.preventDefault();
        setDragOver( false );
        const attachmentId = parseInt( e.dataTransfer.getData( 'text/plain' ), 10 );
        if ( attachmentId ) {
            onMove( attachmentId, node.path );
        }
    }

    function handleRenameSubmit( e ) {
        e.preventDefault();
        setEditing( false );
        if ( editName && editName !== node.name ) {
            onRename( node.path, editName );
        }
    }

    return (
        <li style={ { listStyle: 'none', paddingLeft: depth * 12 } }>
            <span
                style={ {
                    display: 'flex',
                    alignItems: 'center',
                    gap: 4,
                    padding: '2px 4px',
                    borderRadius: 3,
                    background: dragOver ? '#e0f0ff' : 'transparent',
                    cursor: 'pointer',
                } }
                onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
                onDragLeave={ () => setDragOver( false ) }
                onDrop={ handleDrop }
            >
                { /* Folder icon */ }
                <span aria-hidden="true">📁</span>

                { editing ? (
                    <form onSubmit={ handleRenameSubmit } style={ { margin: 0 } }>
                        <input
                            autoFocus
                            value={ editName }
                            onChange={ ( e ) => setEditName( e.target.value ) }
                            onBlur={ handleRenameSubmit }
                            style={ { width: 120 } }
                        />
                    </form>
                ) : (
                    <button
                        type="button"
                        style={ { background: 'none', border: 'none', cursor: 'pointer', padding: 0, fontWeight: 'inherit' } }
                        onClick={ () => onSelect( node.path ) }
                    >
                        { node.name }
                    </button>
                ) }

                <span style={ { marginLeft: 'auto', display: 'flex', gap: 2 } }>
                    <Button
                        icon="edit"
                        label="Rename"
                        isSmall
                        onClick={ () => { setEditing( true ); setEditName( node.name ); } }
                    />
                    <Button
                        icon="trash"
                        label="Delete"
                        isSmall
                        isDestructive
                        onClick={ () => onDelete( node.path ) }
                    />
                </span>
            </span>

            { node.children.length > 0 && (
                <FolderTree
                    tree={ node.children }
                    onSelect={ onSelect }
                    onMove={ onMove }
                    onRename={ onRename }
                    onDelete={ onDelete }
                    depth={ depth + 1 }
                />
            ) }
        </li>
    );
}
```

Save to: `src/components/FolderNode.js`

- [ ] **Step 4: Create FolderTree.js**

```jsx
import FolderNode from './FolderNode';

export default function FolderTree( { tree, onSelect, onMove, onRename, onDelete, depth = 0 } ) {
    if ( ! tree.length ) return null;

    return (
        <ul style={ { margin: 0, padding: 0 } }>
            { tree.map( ( node ) => (
                <FolderNode
                    key={ node.path }
                    node={ node }
                    onSelect={ onSelect }
                    onMove={ onMove }
                    onRename={ onRename }
                    onDelete={ onDelete }
                    depth={ depth }
                />
            ) ) }
        </ul>
    );
}
```

Save to: `src/components/FolderTree.js`

- [ ] **Step 5: Run tests — verify they pass**

```bash
npm test -- --watchAll=false
```

Expected: `Tests: 4 passed, 4 total`

- [ ] **Step 6: Commit**

```bash
git add src/components/FolderTree.js src/components/FolderNode.js src/components/__tests__/FolderTree.test.js
git commit -m "feat: FolderTree + FolderNode with drag-drop; Jest tests pass"
```

---

### Task 8: AddFolderButton

**Files:**
- Create: `src/components/AddFolderButton.js`

**Interfaces:**
- Consumes props: `onAdd(name: string, parentPath: string)`
- Produces: Button that reveals an inline text input; on Enter or blur submits to `onAdd`

- [ ] **Step 1: Create AddFolderButton.js**

```jsx
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

export default function AddFolderButton( { onAdd } ) {
    const [ open, setOpen ]   = useState( false );
    const [ name, setName ]   = useState( '' );

    function handleSubmit( e ) {
        e.preventDefault();
        if ( name.trim() ) {
            onAdd( name.trim(), '' );
        }
        setName( '' );
        setOpen( false );
    }

    if ( ! open ) {
        return (
            <Button
                icon="plus"
                variant="secondary"
                style={ { marginTop: 8, width: '100%' } }
                onClick={ () => setOpen( true ) }
            >
                Add Folder
            </Button>
        );
    }

    return (
        <form onSubmit={ handleSubmit } style={ { marginTop: 8 } }>
            <input
                autoFocus
                type="text"
                placeholder="Folder name"
                value={ name }
                onChange={ ( e ) => setName( e.target.value ) }
                onBlur={ handleSubmit }
                style={ { width: '100%', boxSizing: 'border-box' } }
            />
        </form>
    );
}
```

Save to: `src/components/AddFolderButton.js`

- [ ] **Step 2: Commit**

```bash
git add src/components/AddFolderButton.js
git commit -m "feat: AddFolderButton inline input component"
```

---

### Task 9: DeleteFolderDialog

**Files:**
- Create: `src/components/DeleteFolderDialog.js`

**Interfaces:**
- Consumes props: `path: string`, `tree: array`, `onConfirm(path, action, destinationPath)`, `onCancel()`
- Produces: Two-step modal. Step 1: choose Move or Delete. Step 2: confirm. If Move, show folder selector. Calls `onConfirm` with final decision.

- [ ] **Step 1: Create DeleteFolderDialog.js**

```jsx
import { useState } from '@wordpress/element';
import { Modal, Button, RadioControl, SelectControl } from '@wordpress/components';

function flattenTree( tree, result = [] ) {
    for ( const node of tree ) {
        result.push( { label: node.path, value: node.path } );
        if ( node.children.length ) flattenTree( node.children, result );
    }
    return result;
}

export default function DeleteFolderDialog( { path, tree, onConfirm, onCancel } ) {
    const [ step, setStep ]     = useState( 1 ); // 1 = choose action, 2 = confirm
    const [ action, setAction ] = useState( 'move' );
    const [ dest, setDest ]     = useState( '' );

    const otherFolders = flattenTree( tree ).filter( ( f ) => f.value !== path );

    function handleNext() {
        if ( action === 'move' && ! dest ) return; // require destination
        setStep( 2 );
    }

    return (
        <Modal
            title={ `Delete "${ path }"` }
            onRequestClose={ onCancel }
        >
            { step === 1 && (
                <>
                    <RadioControl
                        label="What should happen to files in this folder?"
                        selected={ action }
                        options={ [
                            { label: 'Move files to another folder', value: 'move' },
                            { label: 'Delete files permanently', value: 'delete' },
                        ] }
                        onChange={ setAction }
                    />

                    { action === 'move' && (
                        <SelectControl
                            label="Move files to:"
                            value={ dest }
                            options={ [ { label: '— select folder —', value: '' }, ...otherFolders ] }
                            onChange={ setDest }
                        />
                    ) }

                    <div style={ { display: 'flex', gap: 8, marginTop: 16 } }>
                        <Button variant="secondary" onClick={ onCancel }>Cancel</Button>
                        <Button
                            variant="primary"
                            isDestructive={ action === 'delete' }
                            disabled={ action === 'move' && ! dest }
                            onClick={ handleNext }
                        >
                            Next
                        </Button>
                    </div>
                </>
            ) }

            { step === 2 && (
                <>
                    <p>
                        { action === 'delete'
                            ? `All files in "${ path }" will be permanently deleted. This cannot be undone.`
                            : `All files in "${ path }" will be moved to "${ dest }".` }
                    </p>
                    <div style={ { display: 'flex', gap: 8, marginTop: 16 } }>
                        <Button variant="secondary" onClick={ () => setStep( 1 ) }>Back</Button>
                        <Button
                            variant="primary"
                            isDestructive
                            onClick={ () => onConfirm( path, action, dest ) }
                        >
                            { action === 'delete' ? 'Delete permanently' : 'Move and delete folder' }
                        </Button>
                    </div>
                </>
            ) }
        </Modal>
    );
}
```

Save to: `src/components/DeleteFolderDialog.js`

- [ ] **Step 2: Commit**

```bash
git add src/components/DeleteFolderDialog.js
git commit -m "feat: DeleteFolderDialog — two-step move-or-delete confirmation modal"
```

---

### Task 10: Build & Commit Assets

**Files:**
- Modify: `build/` (generated, then committed)
- Modify: `.gitignore` (ensure `build/` is NOT ignored)

**Interfaces:**
- Produces: `build/index.js`, `build/index.css`, `build/index.asset.php` — consumed by `Admin::enqueue_scripts()`

- [ ] **Step 1: Verify build/ is not in .gitignore**

Open `.gitignore`. Confirm `build/` is NOT listed. The current `.gitignore` (from Task 1) excludes `vendor/`, `node_modules/`, `.phpunit.cache`, and `*.zip` — `build/` is not excluded. ✓

- [ ] **Step 2: Build the assets**

```bash
npm run build
```

Expected output:
```
asset index.js [emitted] (name: index)
asset index.css [emitted] (name: index)
asset index.asset.php [emitted]
```

Verify: `ls build/` shows `index.js`, `index.css`, `index.asset.php`

- [ ] **Step 3: Commit build output**

```bash
git add build/
git commit -m "build: compile React assets — add build/ to repo"
```

---

### Task 11: CI Workflow

**Files:**
- Create: `.github/workflows/ci.yml`

**Interfaces:**
- Triggers on: push to `main`, pull_request to `main`
- Runs two parallel jobs: `test-php` (PHPUnit) and `test-js` (Jest)

- [ ] **Step 1: Create CI workflow**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test-php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none

      - name: Install Composer dependencies
        run: composer install --no-progress --no-interaction

      - name: Run PHPUnit
        run: vendor/bin/phpunit

  test-js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install npm dependencies
        run: npm ci

      - name: Run Jest
        run: npm test -- --watchAll=false
```

Save to: `.github/workflows/ci.yml`

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add parallel PHP + JS test workflow"
```

---

### Task 12: Release Workflow

**Files:**
- Create: `.github/workflows/release.yml`

**Interfaces:**
- Triggers on: tag push matching `v[0-9]+.[0-9]+.[0-9]+`
- Parallel jobs: `test-php`, `test-js`, `build-js`
- Sequential: `release` (needs all three) — bumps version in `media-folders.php` + `readme.txt`, commits back to main, packages zip excluding dev files, creates GitHub Release

- [ ] **Step 1: Create release workflow**

```yaml
name: Release

on:
  push:
    tags:
      - 'v[0-9]+.[0-9]+.[0-9]+'

permissions:
  contents: write

jobs:
  test-php:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none
      - run: composer install --no-progress --no-interaction
      - run: vendor/bin/phpunit

  test-js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      - run: npm ci
      - run: npm test -- --watchAll=false

  build-js:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      - run: npm ci
      - run: npm run build
      - uses: actions/upload-artifact@v4
        with:
          name: build
          path: build/

  release:
    needs: [test-php, test-js, build-js]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          token: ${{ secrets.GITHUB_TOKEN }}
          fetch-depth: 0

      - name: Download compiled build
        uses: actions/download-artifact@v4
        with:
          name: build
          path: build/

      - name: Extract version from tag
        run: echo "VERSION=${GITHUB_REF_NAME#v}" >> $GITHUB_ENV

      - name: Bump version in plugin file and readme
        run: |
          sed -i "s/^ \* Version:.*/ * Version:           ${{ env.VERSION }}/" media-folders.php
          sed -i "s/define( 'MEDIA_FOLDERS_VERSION', '.*' );/define( 'MEDIA_FOLDERS_VERSION', '${{ env.VERSION }}' );/" media-folders.php
          sed -i "s/^Stable tag:.*/Stable tag: ${{ env.VERSION }}/" readme.txt

      - name: Commit version bump to main
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git add media-folders.php readme.txt
          git commit -m "chore: bump version to ${{ env.VERSION }} [skip ci]"
          git push origin HEAD:main

      - name: Package plugin zip
        run: |
          mkdir -p /tmp/roa-folder-manager
          rsync -a . /tmp/roa-folder-manager/ \
            --exclude='.git' \
            --exclude='.github' \
            --exclude='src' \
            --exclude='node_modules' \
            --exclude='tests' \
            --exclude='vendor' \
            --exclude='package.json' \
            --exclude='package-lock.json' \
            --exclude='composer.json' \
            --exclude='composer.lock' \
            --exclude='phpunit.xml' \
            --exclude='*.md' \
            --exclude='.gitignore'
          cd /tmp && zip -r roa-folder-manager.zip roa-folder-manager/

      - name: Create GitHub Release
        run: |
          gh release create "${{ github.ref_name }}" \
            /tmp/roa-folder-manager.zip \
            --title "ROA Folder Manager ${{ github.ref_name }}" \
            --generate-notes
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

Save to: `.github/workflows/release.yml`

- [ ] **Step 2: Commit and push**

```bash
git add .github/workflows/release.yml
git commit -m "ci: release workflow — parallel test+build, version bump, GitHub Release"
git push origin main
```

- [ ] **Step 3: Verify CI passes on GitHub**

Visit `https://github.com/delharris1981/ROA-folder-manager/actions`

Expected: CI workflow runs on push, both `test-php` and `test-js` jobs pass.

---

## Self-Review

**Spec coverage check:**
- ✅ Nestable folders on disk — `FolderManager::create` with `parent_path`
- ✅ Folder tree in left panel — `FolderPanel` + DOM injection in `src/index.js`
- ✅ Upload into selected folder — `ajax_query_attachments_args` filter + `handleSelect` sets `wp.media` library props; upload routing requires one more step: `pre_option_uploads_use_yearmonth_folders` and `option_upload_path` filters. **Gap found — see note below.**
- ✅ Move attachments — `AttachmentMover::move`
- ✅ Move thumbnails — `AttachmentMover::move` iterates `$metadata['sizes']`
- ✅ Update DB meta — `_wp_attached_file`, `_wp_attachment_metadata`, `guid` all updated
- ✅ Update post content URLs — `replace_urls_in_posts` (500 post cap)
- ✅ Delete folder dialog — `DeleteFolderDialog` two-step
- ✅ OOP PHP namespace — all includes use `MediaFolders\`
- ✅ PSR-4 autoloading — `composer.json`
- ✅ WP 6+ minimum — plugin header + readme
- ✅ Built assets committed — Task 10
- ✅ GitHub repo — created, remote set
- ✅ Version bump on release — release.yml `sed` steps
- ✅ Parallel subagent build — `test-php`, `test-js`, `build-js` parallel jobs
- ✅ Auto-push on release — release.yml pushes commit back to main + creates GH Release

**Upload routing gap:** The spec says "files land at `uploads/my-folder/image.jpg`" when a folder is selected. The current design filters the _display_ of files by folder but does not yet redirect the WordPress upload destination when a folder is selected. This requires hooking `pre_option_upload_path` or using the `upload_dir` filter.

**Add Task 13 to handle upload routing:**

---

### Task 13: Upload Destination Routing

**Files:**
- Modify: `includes/Admin.php`

**Interfaces:**
- Consumes: `?media_folder=` param passed from the React app via a JS global
- Produces: WordPress uploads land in the selected folder instead of the year/month default

- [ ] **Step 1: Pass active folder from JS to the upload handler**

In `src/index.js`, after mount, expose the active folder selection so PHP can read it via a cookie or AJAX param. The simplest approach: when a folder is selected, set a transient via a lightweight AJAX call, and hook `upload_dir` to redirect the upload destination.

Add to `src/api.js`:

```js
export const setActiveFolder = ( folderPath ) =>
    apiFetch( '/active-folder', {
        method: 'POST',
        body: JSON.stringify( { path: folderPath } ),
    } );
```

- [ ] **Step 2: Add REST endpoint + upload_dir hook in RestAPI.php**

In `includes/RestAPI.php`, inside `register_routes()`, add:

```php
register_rest_route( 'media-folders/v1', '/active-folder', [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'set_active_folder' ],
    'permission_callback' => [ $this, 'check_permission' ],
    'args'                => [
        'path' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
    ],
] );
```

Add method to `RestAPI.php`:

```php
public function set_active_folder( \WP_REST_Request $request ): \WP_REST_Response {
    $path = $request->get_param( 'path' );
    set_transient( 'media_folders_active_' . get_current_user_id(), $path, 300 );
    return new \WP_REST_Response( [ 'active' => $path ], 200 );
}
```

- [ ] **Step 3: Hook upload_dir in Admin.php**

In `Admin::register_hooks()`, add:

```php
add_filter( 'upload_dir', [ $this, 'redirect_upload_dir' ] );
```

Add method to `Admin.php`:

```php
public function redirect_upload_dir( array $dirs ): array {
    $folder = get_transient( 'media_folders_active_' . get_current_user_id() );

    if ( ! $folder || str_contains( $folder, '..' ) ) {
        return $dirs;
    }

    $folder = trim( $folder, '/' );

    $dirs['subdir'] = '/' . $folder;
    $dirs['path']   = $dirs['basedir'] . '/' . $folder;
    $dirs['url']    = $dirs['baseurl'] . '/' . $folder;

    return $dirs;
}
```

- [ ] **Step 4: Update FolderPanel.js — call setActiveFolder on select**

In `src/components/FolderPanel.js`, update the import:

```js
import { getFolders, createFolder, renameFolder, deleteFolder, moveAttachment, setActiveFolder } from '../api';
```

Update `handleSelect`:

```js
function handleSelect( folderPath ) {
    setActiveFolder( folderPath ).catch( () => {} );

    if ( ! window.wp?.media?.frame ) return;
    const library = window.wp.media.frame.state().get( 'library' );
    if ( library ) {
        library.props.set( { media_folder: folderPath } );
        library.reset();
        library.more();
    }
}
```

- [ ] **Step 5: Rebuild assets**

```bash
npm run build
```

Expected: Build succeeds with no errors.

- [ ] **Step 6: Commit**

```bash
git add includes/Admin.php includes/RestAPI.php src/components/FolderPanel.js src/api.js build/
git commit -m "feat: route uploads to selected folder via upload_dir filter + transient"
git push origin main
```
