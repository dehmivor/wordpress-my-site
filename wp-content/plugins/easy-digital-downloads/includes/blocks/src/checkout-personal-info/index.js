import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import Edit from './edit';
import Save from './save';
import metadata from './block.json';
import { Icon } from '../utilities/icons';

registerBlockType( metadata.name, {
	icon: Icon( 'admin-users' ),
	edit: Edit,
	save: Save,
} );
