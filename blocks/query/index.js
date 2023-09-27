/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import queryInspectorControls from './hooks';
import './editor.scss';

registerBlockType(metadata, { 
	edit, 
	save, 
	icon: (
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 195.02 156.81">
			<g id="Layer_2" data-name="Layer 2">
				<g id="Layer_1-2" data-name="Layer 1">
					<path d="M147.19,57a6.35,6.35,0,0,0-1.85-4.42l-3.72-3.73A6.39,6.39,0,0,0,137.2,47a6.17,6.17,0,0,0-4.42,1.85L81.43,100.22a36.62,36.62,0,0,1-27,11A37.31,37.31,0,0,1,27.73,99.53c-13.85-14.6-13.26-38.31,1.34-52.91L55.93,19.77a6.28,6.28,0,0,0,1.81-4.39A6.18,6.18,0,0,0,55.93,11L53.77,8.84a8.54,8.54,0,0,0-12,0L16.6,34C-4.92,55.48-5.61,90.51,15.07,112a54.86,54.86,0,0,0,39.29,17h.56a55.19,55.19,0,0,0,39-16.16l51.39-51.39A6.35,6.35,0,0,0,147.19,57Z"/><path d="M165.62,121.67l-2.17-2.16a6.72,6.72,0,0,1,0-9.5l15-15C200,73.51,200.62,38.51,179.93,17a55.21,55.21,0,0,0-78.87-.8l-51.4,51.4a6.25,6.25,0,0,0,0,8.82l3.77,3.77a6.27,6.27,0,0,0,8.81,0l51.36-51.4a37.48,37.48,0,0,1,53.65.71c13.87,14.58,13.3,38.32-1.28,52.9l-19.85,19.85-2,2L142,106.34a12,12,0,0,0,0,16.89L173.77,155a6.25,6.25,0,0,0,8.82,0l3.76-3.76a6.25,6.25,0,0,0,0-8.82Z"/>
				</g>
			</g>
		</svg>
  )
})

addFilter( 'editor.BlockEdit', 'cp-groups/query', queryInspectorControls )