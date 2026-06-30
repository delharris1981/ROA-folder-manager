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

    // While dragging an attachment, WP's file-upload handler intercepts
    // dragenter/dragover on the whole page and shows a full-screen upload overlay.
    // We suppress that by intercepting in capture phase at document level for any
    // drag that: (a) carries no files, and (b) is not targeting our folder panel.
    // Events targeting the panel are left alone so folder-row drop zones still work.
    function suppressWpUploadOverlay( e ) {
        const hasFiles = e.dataTransfer &&
            Array.from( e.dataTransfer.types ).includes( 'Files' );
        if ( ! hasFiles && ! panel.contains( e.target ) ) {
            e.stopPropagation();
        }
    }

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

            // Install capture-phase suppressor for the duration of this drag
            document.addEventListener( 'dragenter', suppressWpUploadOverlay, true );
            document.addEventListener( 'dragover',  suppressWpUploadOverlay, true );
        } );

        el.addEventListener( 'dragend', () => {
            document.removeEventListener( 'dragenter', suppressWpUploadOverlay, true );
            document.removeEventListener( 'dragover',  suppressWpUploadOverlay, true );
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
