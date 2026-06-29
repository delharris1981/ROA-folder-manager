import { useState } from '@wordpress/element';
import FolderTree from './FolderTree';

// ponytail: plain buttons instead of @wordpress/components Button — components is an external WP script, not installed as npm dep

export default function FolderNode( { node, onSelect, onMove, onRename, onDelete, depth } ) {
	const [ dragOver, setDragOver ] = useState( false );
	const [ editing, setEditing ]   = useState( false );
	const [ editName, setEditName ] = useState( node.name );

	function handleDrop( e ) {
		e.preventDefault();
		setDragOver( false );
		const attachmentId = parseInt( e.dataTransfer.getData( 'text/plain' ), 10 );
		if ( attachmentId ) {
			onMove( attachmentId, node.path );
		}
	}

	function handleRenameSubmit( e ) {
		e.preventDefault();
		setEditing( false );
		if ( editName && editName !== node.name ) {
			onRename( node.path, editName );
		}
	}

	return (
		<li style={ { listStyle: 'none', paddingLeft: depth * 12 } }>
			<span
				style={ {
					display: 'flex',
					alignItems: 'center',
					gap: 4,
					padding: '2px 4px',
					borderRadius: 3,
					background: dragOver ? '#e0f0ff' : 'transparent',
					cursor: 'pointer',
				} }
				onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
				onDragLeave={ () => setDragOver( false ) }
				onDrop={ handleDrop }
			>
				{ /* ponytail: dashicon class for folder, no emoji */ }
				<span className="dashicons dashicons-category" aria-hidden="true" />

				{ editing ? (
					<form onSubmit={ handleRenameSubmit } style={ { margin: 0 } }>
						<input
							autoFocus
							value={ editName }
							onChange={ ( e ) => setEditName( e.target.value ) }
							onBlur={ handleRenameSubmit }
							style={ { width: 120 } }
						/>
					</form>
				) : (
					<button
						type="button"
						style={ { background: 'none', border: 'none', cursor: 'pointer', padding: 0, fontWeight: 'inherit' } }
						onClick={ () => onSelect( node.path ) }
						onDrop={ handleDrop }
						onDragOver={ ( e ) => { e.preventDefault(); setDragOver( true ); } }
						onDragLeave={ () => setDragOver( false ) }
					>
						{ node.name }
					</button>
				) }

				<span style={ { marginLeft: 'auto', display: 'flex', gap: 2 } }>
					<button
						type="button"
						className="button button-small"
						aria-label="Rename"
						onClick={ () => { setEditing( true ); setEditName( node.name ); } }
					>
						<span className="dashicons dashicons-edit" aria-hidden="true" />
					</button>
					<button
						type="button"
						className="button button-small button-link-delete"
						aria-label="Delete"
						onClick={ () => onDelete( node.path ) }
					>
						<span className="dashicons dashicons-trash" aria-hidden="true" />
					</button>
				</span>
			</span>

			{ node.children.length > 0 && (
				<FolderTree
					tree={ node.children }
					onSelect={ onSelect }
					onMove={ onMove }
					onRename={ onRename }
					onDelete={ onDelete }
					depth={ depth + 1 }
				/>
			) }
		</li>
	);
}
