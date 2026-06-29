import { useState } from '@wordpress/element';

export default function AddFolderButton( { onAdd } ) {
	const [ open, setOpen ] = useState( false );
	const [ name, setName ] = useState( '' );

	function handleSubmit( e ) {
		e.preventDefault();
		if ( name.trim() ) {
			onAdd( name.trim(), '' );
		}
		setName( '' );
		setOpen( false );
	}

	if ( ! open ) {
		return (
			<button
				onClick={ () => setOpen( true ) }
				style={ { marginTop: 8, width: '100%', padding: '8px 12px', cursor: 'pointer' } }
			>
				<span className="dashicons dashicons-plus-alt" aria-hidden="true" style={ { marginRight: '4px' } } />
				Add Folder
			</button>
		);
	}

	return (
		<form onSubmit={ handleSubmit } style={ { marginTop: 8 } }>
			<input
				autoFocus
				type="text"
				placeholder="Folder name"
				value={ name }
				onChange={ ( e ) => setName( e.target.value ) }
				onBlur={ handleSubmit }
				style={ { width: '100%', boxSizing: 'border-box', padding: '6px 8px' } }
			/>
		</form>
	);
}
