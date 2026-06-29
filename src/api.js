const { nonce, restBase } = window.mediaFolders;

async function apiFetch( path, options = {} ) {
    const res = await fetch( restBase + path, {
        headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json',
        },
        ...options,
    } );
    const data = await res.json();
    if ( ! res.ok ) throw data;
    return data;
}

export const getFolders = () => apiFetch( '/folders' );

export const createFolder = ( path, parentPath = '' ) =>
    apiFetch( '/folders', {
        method: 'POST',
        body: JSON.stringify( { path, parent_path: parentPath } ),
    } );

export const renameFolder = ( path, newName ) =>
    apiFetch( '/folders', {
        method: 'PATCH',
        body: JSON.stringify( { path, new_name: newName } ),
    } );

export const deleteFolder = ( path, action, destinationPath = '' ) =>
    apiFetch( '/folders', {
        method: 'DELETE',
        body: JSON.stringify( { path, action, destination_path: destinationPath } ),
    } );

export const moveAttachment = ( id, destinationPath ) =>
    apiFetch( `/attachments/${ id }/move`, {
        method: 'POST',
        body: JSON.stringify( { destination_path: destinationPath } ),
    } );
