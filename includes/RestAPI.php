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
		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_wp_attached_file',
					'value'   => $old_path . '/',
					'compare' => 'LIKE',
				],
			],
		] );

		foreach ( $attachments as $id ) {
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
