import { render } from '@wordpress/element';
import FolderPanel from './components/FolderPanel';
import './index.css';

// Mount folder panel on upload.php — insert before the media grid
const grid = document.getElementById( 'wp-media-grid' );
if ( grid ) {
    const panel = document.createElement( 'div' );
    panel.id = 'media-folders-panel';

    const wrapper = document.createElement( 'div' );
    wrapper.className = 'media-folders-layout';
    grid.parentNode.insertBefore( wrapper, grid );
    wrapper.appendChild( panel );
    wrapper.appendChild( grid );

    render( <FolderPanel />, panel );

    function makeDraggable( el ) {
        if ( el.dataset.mfDrag ) return;
        el.dataset.mfDrag = '1';
        el.draggable = true;

        el.addEventListener( 'dragstart', ( e ) => {
            e.stopPropagation(); // stop WP's lasso-select on dragstart
            e.dataTransfer.setData( 'text/plain', el.dataset.id );
            e.dataTransfer.effectAllowed = 'move';
            const thumb = el.querySelector( 'img' );
            if ( thumb ) e.dataTransfer.setDragImage( thumb, 16, 16 );
            // CSS hides .uploader-window while this class is present (see index.css)
            document.body.classList.add( 'mf-dragging' );
        } );

        el.addEventListener( 'dragend', () => {
            document.body.classList.remove( 'mf-dragging' );
        } );
    }

    grid.querySelectorAll( '.attachment[data-id]' ).forEach( makeDraggable );

    new MutationObserver( ( mutations ) => {
        for ( const mutation of mutations ) {
            mutation.addedNodes.forEach( ( node ) => {
                if ( node.nodeType !== 1 ) return;
                if ( node.matches( '.attachment[data-id]' ) ) makeDraggable( node );
                node.querySelectorAll?.( '.attachment[data-id]' ).forEach( makeDraggable );
            } );
        }
    } ).observe( grid, { childList: true, subtree: true } );
}
