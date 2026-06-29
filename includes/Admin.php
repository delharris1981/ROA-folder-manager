<?php

declare( strict_types=1 );

namespace MediaFolders;

class Admin {

	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'enqueue_for_modal' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'filter_attachments_by_folder' ] );
		add_filter( 'upload_dir', [ $this, 'redirect_upload_dir' ] );
	}

	public function enqueue_scripts( string $hook ): void {
		if ( 'upload.php' !== $hook ) {
			return;
		}
		$this->enqueue_assets();
	}

	public function enqueue_for_modal(): void {
		$this->enqueue_assets();
	}

	private function enqueue_assets(): void {
		$asset_file = MEDIA_FOLDERS_PATH . 'build/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [], 'version' => MEDIA_FOLDERS_VERSION ];

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

	public function filter_attachments_by_folder( array $query ): array {
		$folder = sanitize_text_field( wp_unslash( $_REQUEST['query']['media_folder'] ?? '' ) );

		if ( '' === $folder ) {
			return $query;
		}

		// Validate: no traversal, relative path only
		if ( str_contains( $folder, '..' ) || str_starts_with( $folder, '/' ) ) {
			return $query;
		}

		global $wpdb;
		$like     = $wpdb->esc_like( $folder . '/' );
		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attached_file'
			   AND meta_value LIKE %s",
			$like . '%'
		) );

		unset( $query['meta_query'] );

		if ( empty( $post_ids ) ) {
			$query['post__in'] = [ 0 ];
		} else {
			$query['post__in'] = array_map( 'intval', $post_ids );
		}

		return $query;
	}
}
