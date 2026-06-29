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
