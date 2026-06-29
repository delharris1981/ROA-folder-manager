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

        // Verify dest stays within base_dir (use realpath on both to resolve symlinks, e.g. macOS /tmp)
        $real_base     = realpath( $this->base_dir );
        $real_dest_dir = realpath( dirname( $abs_dest ) );
        if ( false === $real_dest_dir || ! str_starts_with( $real_dest_dir . '/', $real_base . '/' ) ) {
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
