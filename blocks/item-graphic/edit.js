/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	ToggleControl,
	PanelBody,
	TextControl,
} from '@wordpress/components';
import {
	InspectorControls,
	InnerBlocks,
	useBlockProps,
	__experimentalUseBorderProps as useBorderProps,
} from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import DimensionControls from './dimension-controls';
import Overlay from './overlay';
import { getAllowedBlocks } from '../utils/allowed-blocks';
import { PlayOverlayWrapper } from '../../src/app/Templates/PlayOverlay';

export default function ItemGraphicEdit( {
	clientId,
	attributes,
	setAttributes,
	context: { postId, postType: postTypeSlug, queryId, item },
} ) {

	const {
		isLink,
		aspectRatio,
		height,
		width,
		scale,
		rel,
		linkTarget,
		playIcon
	} = attributes;

	const imageRef = React.useRef()

	const mediaUrl = item.thumb || cplAdmin.site.thumb

	const { postType } = useSelect( ( select ) => {
		const { getPostType } = select( coreStore );

		return {
			postType: postTypeSlug && getPostType( postTypeSlug ),
		};
	})

	const blockProps = useBlockProps( {
		style: { width, height, aspectRatio },
	} );
	const borderProps = useBorderProps( attributes );

	const controls = (
		<>
			<DimensionControls
				clientId={ clientId }
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>

			<InspectorControls>
				<PanelBody title={ __( 'Settings' ) }>
					{
						<ToggleControl
							label={ __( 'Show play button on graphic', 'cp-library' ) }
							checked={playIcon}
							onChange={(checked) => {
								setAttributes({ playIcon: checked })
							}}
							help={ __( 'If checked, a play button will be shown on the sermon graphic. Otherwise the buttons will be displayed to the side. Will only display if sermon has a video.', 'cp-library' ) }
					 	/>
					}
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							postType?.labels.singular_name
								? sprintf(
										// translators: %s: Name of the post type e.g: "post".
										__( 'Link to %s' ),
										postType.labels.singular_name.toLowerCase()
								  )
								: __( 'Link to post' )
						}
						onChange={ () => setAttributes( { isLink: ! isLink } ) }
						checked={ isLink }
						help={__( 'Warning: will not act as a link if there is nested content.', 'cp-library' )}
					/>
					{ isLink && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Open in new tab' ) }
								onChange={ ( value ) =>
									setAttributes( {
										linkTarget: value ? '_blank' : '_self',
									} )
								}
								checked={ linkTarget === '_blank' }
							/>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'Link rel' ) }
								value={ rel }
								onChange={ ( newRel ) =>
									setAttributes( { rel: newRel } )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
		</>
	);

	let image;

	const imageStyles = {
		...borderProps.style,
		height: ( !! aspectRatio && '100%' ) || height,
		width: !! aspectRatio && '100%',
		objectFit: !! ( height || aspectRatio ) && scale,
	};

	if( mediaUrl ) {
		image = (
			<img
				className={ borderProps.className }
				src={ mediaUrl }
				alt={ __( 'Featured image', 'cp-library' ) }
				style={ imageStyles }
				ref={imageRef}
			/>
		)
	}
	else {
		image = placeholder 
	}


	if( postTypeSlug !== 'cpl_item' && postTypeSlug !== 'cpl_item_type' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with the current post type.', 'cp-library' ) }</div>
		)
	}

	/**
	 * When the post featured image block:
	 * - Has an image assigned
	 * - Is not inside a query loop
	 * Then display the image and the image replacement option.
	 */
	return (
		<>
			{ controls }
			<figure { ...blockProps }>
				{ image }
				<Overlay
					attributes={ attributes }
					setAttributes={ setAttributes }
					clientId={ clientId }
				/>
				{
					item && playIcon &&
					<PlayOverlayWrapper item={item} />
				}
				{
					!playIcon &&
					<div className='cpl-item-graphic-inner-blocks-wrapper'>
						{
							<InnerBlocks allowedBlocks={ getAllowedBlocks( postTypeSlug ) } />
						}
					</div>
				}
			</figure>
		</>
	);
}
