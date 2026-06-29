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
