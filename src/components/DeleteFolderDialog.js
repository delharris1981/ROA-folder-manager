import { useState } from '@wordpress/element';
import { Modal, Button, RadioControl, SelectControl } from '@wordpress/components';

function flattenTree( tree, result = [] ) {
	for ( const node of tree ) {
		result.push( { label: node.path, value: node.path } );
		if ( node.children.length ) flattenTree( node.children, result );
	}
	return result;
}

export default function DeleteFolderDialog( { path, tree, onConfirm, onCancel } ) {
	const [ step, setStep ]     = useState( 1 ); // 1 = choose action, 2 = confirm
	const [ action, setAction ] = useState( 'move' );
	const [ dest, setDest ]     = useState( '' );

	const otherFolders = flattenTree( tree ).filter( ( f ) => f.value !== path );

	function handleNext() {
		if ( action === 'move' && ! dest ) return; // require destination
		setStep( 2 );
	}

	return (
		<Modal
			title={ `Delete "${ path }"` }
			onRequestClose={ onCancel }
		>
			{ step === 1 && (
				<>
					<RadioControl
						label="What should happen to files in this folder?"
						selected={ action }
						options={ [
							{ label: 'Move files to another folder', value: 'move' },
							{ label: 'Delete files permanently', value: 'delete' },
						] }
						onChange={ setAction }
					/>

					{ action === 'move' && (
						<SelectControl
							label="Move files to:"
							value={ dest }
							options={ [ { label: '— select folder —', value: '' }, ...otherFolders ] }
							onChange={ setDest }
						/>
					) }

					<div style={ { display: 'flex', gap: 8, marginTop: 16 } }>
						<Button variant="secondary" onClick={ onCancel }>Cancel</Button>
						<Button
							variant="primary"
							isDestructive={ action === 'delete' }
							disabled={ action === 'move' && ! dest }
							onClick={ handleNext }
						>
							Next
						</Button>
					</div>
				</>
			) }

			{ step === 2 && (
				<>
					<p>
						{ action === 'delete'
							? `All files in "${ path }" will be permanently deleted. This cannot be undone.`
							: `All files in "${ path }" will be moved to "${ dest }".` }
					</p>
					<div style={ { display: 'flex', gap: 8, marginTop: 16 } }>
						<Button variant="secondary" onClick={ () => setStep( 1 ) }>Back</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => onConfirm( path, action, dest ) }
						>
							{ action === 'delete' ? 'Delete permanently' : 'Move and delete folder' }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
