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

        $current_abs  = $base_dir . '/' . $current_relative;
        $filename     = basename( $current_abs );
        $new_abs      = $dest_abs . '/' . $filename;
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
                    rename( $old_thumb, $new_thumb ); // ponytail: best-effort, failure intentionally ignored
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
