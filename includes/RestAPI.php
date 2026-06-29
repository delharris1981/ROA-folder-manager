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

		register_rest_route( 'media-folders/v1', '/active-folder', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'set_active_folder' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'path' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
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
		global $wpdb;
		$tree  = $this->folder_manager->get_tree();
		$paths = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'"
		);
		return new \WP_REST_Response( $this->add_counts( $tree, $paths ), 200 );
	}

	private function add_counts( array $nodes, array $paths ): array {
		foreach ( $nodes as &$node ) {
			$prefix        = $node['path'] . '/';
			$plen          = strlen( $prefix );
			$node['count'] = count( array_filter( $paths, function ( string $p ) use ( $prefix, $plen ): bool {
				return str_starts_with( $p, $prefix ) && strpos( $p, '/', $plen ) === false;
			} ) );
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = $this->add_counts( $node['children'], $paths );
			}
		}
		unset( $node );
		return $nodes;
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
		$old_path = $request->get_param( 'path' );
		$new_name = $request->get_param( 'new_name' );

		$result = $this->folder_manager->rename( $old_path, $new_name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Compute new relative path: same parent dir, new leaf name.
		$parent   = dirname( $old_path );
		$new_path = ( '.' === $parent ) ? $new_name : $parent . '/' . $new_name;

		// Update attachment meta for every file that lived under old_path.
		global $wpdb;
		$like           = $wpdb->esc_like( ltrim( $old_path, '/' ) . '/' );
		$attachment_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attached_file'
			   AND meta_value LIKE %s",
			$like . '%'
		) );

		foreach ( $attachment_ids as $id ) {
			$old_file = get_post_meta( $id, '_wp_attached_file', true );
			$new_file = $new_path . '/' . substr( $old_file, strlen( $old_path ) + 1 );

			update_post_meta( $id, '_wp_attached_file', $new_file );

			$meta = wp_get_attachment_metadata( $id );
			if ( is_array( $meta ) && isset( $meta['file'] ) ) {
				$meta['file'] = $new_path . '/' . substr( $meta['file'], strlen( $old_path ) + 1 );
				wp_update_attachment_metadata( $id, $meta );
			}

			$post = get_post( $id );
			if ( $post ) {
				$old_guid = $post->guid;
				// Replace the old_path segment in the guid URL, not a simple substr.
				$new_guid = str_replace( '/' . $old_path . '/', '/' . $new_path . '/', $old_guid );
				if ( $new_guid !== $old_guid ) {
					wp_update_post( [ 'ID' => $id, 'guid' => $new_guid ] );
				}
			}
		}

		return new \WP_REST_Response( [ 'renamed' => true ], 200 );
	}

	public function delete_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$path   = $request->get_param( 'path' );
		$action = $request->get_param( 'action' );
		$dest   = $request->get_param( 'destination_path' );

		global $wpdb;
		$like           = $wpdb->esc_like( ltrim( $path, '/' ) . '/' );
		$attachment_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attached_file'
			   AND meta_value LIKE %s",
			$like . '%'
		) );

		if ( 'move' === $action ) {
			foreach ( $attachment_ids as $id ) {
				$result = $this->attachment_mover->move( $id, $dest );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		} else {
			foreach ( $attachment_ids as $id ) {
				wp_delete_attachment( $id, true );
			}
		}

		$upload_dir = wp_upload_dir();
		$abs_path   = $upload_dir['basedir'] . '/' . ltrim( $path, '/' );
		$this->delete_dir_recursive( $abs_path );

		return new \WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	private function delete_dir_recursive( string $abs_path ): void {
		if ( ! is_dir( $abs_path ) ) {
			return;
		}
		$entries = array_diff( scandir( $abs_path ), [ '.', '..' ] );
		foreach ( $entries as $entry ) {
			$full = $abs_path . '/' . $entry;
			is_dir( $full ) ? $this->delete_dir_recursive( $full ) : @unlink( $full );
		}
		@rmdir( $abs_path );
	}

	public function set_active_folder( \WP_REST_Request $request ): \WP_REST_Response {
		$path = $request->get_param( 'path' );
		if ( str_contains( $path, '..' ) ) {
			return new \WP_REST_Response( [ 'error' => 'Invalid path.' ], 400 );
		}
		set_transient( 'media_folders_active_' . get_current_user_id(), $path, 300 );
		return new \WP_REST_Response( [ 'active' => $path ], 200 );
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
