import { render, screen, fireEvent } from '@testing-library/react';
import FolderTree from '../FolderTree';

const noop = () => {};

const tree = [
	{
		name: 'portraits',
		path: 'portraits',
		children: [
			{ name: '2024', path: 'portraits/2024', children: [] },
		],
	},
	{ name: 'products', path: 'products', children: [] },
];

test( 'renders top-level folder names', () => {
	render( <FolderTree tree={ tree } onSelect={ noop } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
	expect( screen.getByText( 'portraits' ) ).toBeInTheDocument();
	expect( screen.getByText( 'products' ) ).toBeInTheDocument();
} );

test( 'renders nested child folder', () => {
	render( <FolderTree tree={ tree } onSelect={ noop } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
	expect( screen.getByText( '2024' ) ).toBeInTheDocument();
} );

test( 'calls onSelect with folder path when folder name is clicked', () => {
	const onSelect = jest.fn();
	render( <FolderTree tree={ tree } onSelect={ onSelect } onMove={ noop } onRename={ noop } onDelete={ noop } /> );
	fireEvent.click( screen.getByText( 'products' ) );
	expect( onSelect ).toHaveBeenCalledWith( 'products' );
} );

test( 'calls onMove with attachment id and folder path on drop', () => {
	const onMove = jest.fn();
	render( <FolderTree tree={ tree } onSelect={ noop } onMove={ onMove } onRename={ noop } onDelete={ noop } /> );
	const folder = screen.getByText( 'products' );
	fireEvent.drop( folder, {
		dataTransfer: { getData: () => '42' },
	} );
	expect( onMove ).toHaveBeenCalledWith( 42, 'products' );
} );
