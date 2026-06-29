import { useState, useEffect } from '@wordpress/element';
import FolderTree from './FolderTree';

// ponytail: plain elements, @wordpress/components Button not available in Jest env
export default function FolderNode( { node, selectedPath, renamingPath, onSelect, onMove, onRename, depth } ) {
	const [ collapsed, setCollapsed ] = useState( false );
	const [ dragOver, setDragOver ]   = useState( false );
	const [ editName, setEditName ]   = useState( node.name );

	const hasChildren = node.children && node.children.length > 0;
	const isSelected  = selectedPath === node.path;
	const isRenaming  = renamingPath === node.path;

	useEffect( () => {
		if ( isRenaming ) setEditName( node.name );
	}, [ isRenaming ] ); // eslint-disable-line react-hooks/exhaustive-deps

	function handleDrop( e ) {
		e.preventDefault();
		setDragOver( false );
		const id = parseInt( e.dataTransfer.getData( 'text/plain' ), 10 );
		if ( id ) onMove( id, node.path );
	}

	function handleRenameSubmit( e ) {
		if ( e && e.preventDefault ) e.preventDefault();
		const trimmed = editName.trim();
		onRename( node.path, trimmed !== node.name ? trimmed : null );
	}

	const rowClass = [
		'mf-folder-row',
		isSelected ? 'selected' : '',
		dragOver   ? 'drag-over' : '',
	].filter( Boolean ).join( ' ' );

	return (
		<li>
			<div
				className={ rowClass }
				style={ { paddingLeft: 8 + depth * 16 } }
				onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
				onDragLeave={ () => setDragOver( false ) }
				onDrop={ handleDrop }
			>
				<span
					className="mf-toggle"
					onClick={ hasChildren ? () => setCollapsed( ! collapsed ) : undefined }
					aria-hidden="true"
				>
					{ hasChildren ? ( collapsed ? '+' : '−' ) : '' }
				</span>

				<span className="dashicons dashicons-category mf-folder-icon" aria-hidden="true" />

				{ isRenaming ? (
					<form onSubmit={ handleRenameSubmit } style={ { flex: 1, margin: 0, minWidth: 0 } }>
						<input
							autoFocus
							className="mf-rename-input"
							value={ editName }
							onChange={ ( e ) => setEditName( e.target.value ) }
							onBlur={ handleRenameSubmit }
						/>
					</form>
				) : (
					<button
						type="button"
						className="mf-folder-name"
						onClick={ () => onSelect( node.path ) }
					>
						{ node.name }
					</button>
				) }

				{ node.count > 0 && (
					<span className="mf-count-badge">{ node.count }</span>
				) }
			</div>

			{ hasChildren && ! collapsed && (
				<FolderTree
					tree={ node.children }
					selectedPath={ selectedPath }
					renamingPath={ renamingPath }
					onSelect={ onSelect }
					onMove={ onMove }
					onRename={ onRename }
					depth={ depth + 1 }
				/>
			) }
		</li>
	);
}
