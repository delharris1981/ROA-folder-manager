import { render } from '@wordpress/element';
import FolderPanel from './components/FolderPanel';
import './index.css';

// Mount folder panel on upload.php — insert before the media grid
const grid = document.getElementById( 'wp-media-grid' );
if ( grid ) {
    const panel = document.createElement( 'div' );
    panel.id = 'media-folders-panel';

    // Wrap grid + panel in a flex layout container
    const wrapper = document.createElement( 'div' );
    wrapper.className = 'media-folders-layout';
    grid.parentNode.insertBefore( wrapper, grid );
    wrapper.appendChild( panel );
    wrapper.appendChild( grid );

    render( <FolderPanel />, panel );

    // Make attachment thumbnails draggable so they can be dropped onto folders.
    // WP renders .attachment[data-id] elements dynamically via Backbone; use a
    // MutationObserver so newly loaded items are captured too.
    function makeDraggable( el ) {
        if ( el.dataset.mfDrag ) return; // already set up
        el.dataset.mfDrag = '1';
        el.draggable = true;
        el.addEventListener( 'dragstart', ( e ) => {
            // Stop WP's own dragstart handler (lasso-select mode) from firing
            e.stopPropagation();
            e.dataTransfer.setData( 'text/plain', el.dataset.id );
            e.dataTransfer.effectAllowed = 'move';
            // Use the thumbnail img as drag ghost instead of the whole card
            const thumb = el.querySelector( 'img' );
            if ( thumb ) e.dataTransfer.setDragImage( thumb, 16, 16 );
        } );
    }

    // Handle items already in the DOM at mount time
    grid.querySelectorAll( '.attachment[data-id]' ).forEach( makeDraggable );

    // Handle items added by WP's infinite scroll / filter changes
    new MutationObserver( ( mutations ) => {
        for ( const mutation of mutations ) {
            mutation.addedNodes.forEach( ( node ) => {
                if ( node.nodeType !== 1 ) return;
                if ( node.matches( '.attachment[data-id]' ) ) {
                    makeDraggable( node );
                }
                node.querySelectorAll?.( '.attachment[data-id]' ).forEach( makeDraggable );
            } );
        }
    } ).observe( grid, { childList: true, subtree: true } );
}
