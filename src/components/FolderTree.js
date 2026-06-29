import FolderNode from './FolderNode';

export default function FolderTree( { tree, onSelect, onMove, onRename, onDelete, depth = 0 } ) {
	if ( ! tree || ! tree.length ) return null;

	return (
		<ul style={ { margin: 0, padding: 0 } }>
			{ tree.map( ( node ) => (
				<FolderNode
					key={ node.path }
					node={ node }
					onSelect={ onSelect }
					onMove={ onMove }
					onRename={ onRename }
					onDelete={ onDelete }
					depth={ depth }
				/>
			) ) }
		</ul>
	);
}
