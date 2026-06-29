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
