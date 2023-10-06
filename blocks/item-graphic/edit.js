/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	ToggleControl,
	PanelBody,
	Placeholder,
	TextControl,
} from '@wordpress/components';
import {
	InspectorControls,
	InnerBlocks,
	useBlockProps,
	store as blockEditorStore,
	__experimentalUseBorderProps as useBorderProps,
} from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { Play } from 'react-feather';

/**
 * Internal dependencies
 */
import DimensionControls from './dimension-controls';
import Overlay from './overlay';
import { getAllowedBlocks } from '../utils/allowed-blocks';

const ALLOWED_MEDIA_TYPES = [ 'image' ];

function getMediaSourceUrlBySizeSlug( media, slug ) {
	return (
		media?.media_details?.sizes?.[ slug ]?.source_url || media?.source_url
	);
}

export default function ItemGraphicEdit( {
	clientId,
	attributes,
	setAttributes,
	context: { postId, postType: postTypeSlug, queryId, item },
} ) {
	const isDescendentOfQueryLoop = Number.isFinite( queryId );
	
	const {
		isLink,
		aspectRatio,
		height,
		width,
		scale,
		sizeSlug,
		rel,
		linkTarget,
		playIcon
	} = attributes;

	const [ featuredImage, setFeaturedImage ] = useEntityProp(
		'postType',
		postTypeSlug,
		'featured_media',
		postId
	);

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

	const { createErrorNotice } = useDispatch( noticesStore );
	const onUploadError = ( message ) => {
		createErrorNotice( message, { type: 'snackbar' } );
	};

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

	const label = __( 'Add a featured image' );
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
					Boolean(playIcon && item?.video?.value) ?
					<div className='cpl-play-btn-overlay'>
						<Play fill='currentColor' size='30%' />
					</div> :
					!playIcon ?
					<div className='cpl-item-graphic-inner-blocks-wrapper'>
						{
							<InnerBlocks allowedBlocks={ getAllowedBlocks( postTypeSlug ) } />
						}
					</div> :
					null
				}
			</figure>
		</>
	);
}
