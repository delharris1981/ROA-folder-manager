import FolderNode from './FolderNode';

export default function FolderTree( { tree, selectedPath, renamingPath, onSelect, onMove, onRename, depth = 0 } ) {
	if ( ! tree || ! tree.length ) return null;

	return (
		<ul className="mf-tree-list">
			{ tree.map( ( node ) => (
				<FolderNode
					key={ node.path }
					node={ node }
					selectedPath={ selectedPath }
					renamingPath={ renamingPath }
					onSelect={ onSelect }
					onMove={ onMove }
					onRename={ onRename }
					depth={ depth }
				/>
			) ) }
		</ul>
	);
}
