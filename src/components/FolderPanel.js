import { useState, useEffect } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import FolderTree from './FolderTree';
import DeleteFolderDialog from './DeleteFolderDialog';
import { getFolders, createFolder, renameFolder, deleteFolder, moveAttachment, setActiveFolder } from '../api';

export default function FolderPanel() {
	const [ tree, setTree ]                   = useState( [] );
	const [ loading, setLoading ]             = useState( true );
	const [ error, setError ]                 = useState( null );
	const [ selectedPath, setSelectedPath ]   = useState( null );
	const [ renamingPath, setRenamingPath ]   = useState( null );
	const [ deleteTarget, setDeleteTarget ]   = useState( null );
	const [ search, setSearch ]               = useState( '' );
	const [ addingFolder, setAddingFolder ]   = useState( false );
	const [ newFolderName, setNewFolderName ] = useState( '' );

	function loadTree() {
		setLoading( true );
		getFolders()
			.then( setTree )
			.catch( ( e ) => setError( e?.message || 'Failed to load folders.' ) )
			.finally( () => setLoading( false ) );
	}

	useEffect( () => { loadTree(); }, [] );

	function handleSelect( folderPath ) {
		setSelectedPath( folderPath );
		setActiveFolder( folderPath ).catch( () => {} );

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

	function handleRenameCommit( path, newName ) {
		setRenamingPath( null );
		if ( ! newName ) return;
		renameFolder( path, newName )
			.then( loadTree )
			.catch( ( e ) => setError( e?.message || 'Failed to rename folder.' ) );
	}

	function handleAddFolder( e ) {
		e.preventDefault();
		const name = newFolderName.trim();
		if ( name ) {
			createFolder( name, '' )
				.then( loadTree )
				.catch( ( e ) => setError( e?.message || 'Failed to create folder.' ) );
		}
		setNewFolderName( '' );
		setAddingFolder( false );
	}

	function handleDeleteConfirm( path, action, destinationPath ) {
		setDeleteTarget( null );
		deleteFolder( path, action, destinationPath )
			.then( loadTree )
			.catch( ( e ) => setError( e?.message || 'Failed to delete folder.' ) );
	}

	function filterTree( nodes, term ) {
		if ( ! term ) return nodes;
		return nodes.reduce( ( acc, node ) => {
			const filteredChildren = filterTree( node.children || [], term );
			if ( node.name.toLowerCase().includes( term.toLowerCase() ) || filteredChildren.length ) {
				acc.push( { ...node, children: filteredChildren } );
			}
			return acc;
		}, [] );
	}

	const visibleTree = filterTree( tree, search );

	return (
		<div className="mf-panel">
			<div className="mf-header">
				<span className="mf-header-title">Folders</span>
				<div className="mf-header-actions">
					<button
						type="button"
						className="mf-icon-btn"
						title="New folder"
						onClick={ () => setAddingFolder( true ) }
					>
						<span className="dashicons dashicons-category" aria-hidden="true" />
						<span aria-hidden="true">+</span>
					</button>
				</div>
			</div>

			<div className="mf-toolbar">
				<button
					type="button"
					className="mf-toolbar-btn"
					title="Refresh"
					onClick={ loadTree }
				>
					<span className="dashicons dashicons-update" aria-hidden="true" />
				</button>
				<div className="mf-toolbar-sep" aria-hidden="true" />
				<button
					type="button"
					className="mf-toolbar-btn"
					title="Rename selected folder"
					disabled={ ! selectedPath }
					onClick={ () => setRenamingPath( selectedPath ) }
				>
					<span className="dashicons dashicons-edit" aria-hidden="true" />
				</button>
				<button
					type="button"
					className="mf-toolbar-btn mf-toolbar-btn--danger"
					title="Delete selected folder"
					disabled={ ! selectedPath }
					onClick={ () => setDeleteTarget( selectedPath ) }
				>
					<span className="dashicons dashicons-trash" aria-hidden="true" />
				</button>
			</div>

			<div className="mf-search">
				<input
					type="text"
					placeholder="Search folders…"
					value={ search }
					onChange={ ( e ) => setSearch( e.target.value ) }
				/>
				<span className="dashicons dashicons-search" aria-hidden="true" />
			</div>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			{ addingFolder && (
				<form className="mf-new-folder-form" onSubmit={ handleAddFolder }>
					<input
						autoFocus
						type="text"
						placeholder="Folder name"
						value={ newFolderName }
						onChange={ ( e ) => setNewFolderName( e.target.value ) }
						onBlur={ handleAddFolder }
					/>
				</form>
			) }

			<div className="mf-tree">
				{ loading ? (
					<Spinner />
				) : (
					<FolderTree
						tree={ visibleTree }
						selectedPath={ selectedPath }
						renamingPath={ renamingPath }
						onSelect={ handleSelect }
						onMove={ handleMove }
						onRename={ handleRenameCommit }
					/>
				) }
			</div>

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
