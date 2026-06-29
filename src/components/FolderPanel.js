import { useState, useEffect } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import FolderTree from './FolderTree';
import AddFolderButton from './AddFolderButton';
import DeleteFolderDialog from './DeleteFolderDialog';
import { getFolders, createFolder, renameFolder, deleteFolder, moveAttachment } from '../api';

export default function FolderPanel() {
    const [ tree, setTree ]                  = useState( [] );
    const [ loading, setLoading ]            = useState( true );
    const [ error, setError ]                = useState( null );
    const [ deleteTarget, setDeleteTarget ]  = useState( null );

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
