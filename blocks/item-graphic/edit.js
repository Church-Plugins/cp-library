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
	context: { postId, postType: postTypeSlug, queryId, thumbnailAction, item },
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
	} = attributes;
	const [ featuredImage, setFeaturedImage ] = useEntityProp(
		'postType',
		postTypeSlug,
		'featured_media',
		postId
	);
	const imageRef = React.useRef()

	const fallbackUrl = cplAdmin.site.thumb


	const { media, postType, loading } = useSelect(
		( select ) => {
			const { getMedia, getPostType, hasFinishedResolution } = select( coreStore );

			const getMediaArgs = [ featuredImage, { context: 'edit' } ];

			return {
				media:
					featuredImage && 
					getMedia( ...getMediaArgs ),
				postType: postTypeSlug && getPostType( postTypeSlug ),
				loading: ! hasFinishedResolution( 'getMedia', getMediaArgs ) || ! hasFinishedResolution( 'getPostType', [ postTypeSlug ] )
			};
		},
		[ featuredImage, postTypeSlug ]
	);
	const mediaUrl = getMediaSourceUrlBySizeSlug( media, sizeSlug );


	const imageSizes = useSelect(
		( select ) => select( blockEditorStore ).getSettings().imageSizes,
		[]
	);
	const imageSizeOptions = imageSizes
		.filter( ( { slug } ) => {
			return media?.media_details?.sizes?.[ slug ]?.source_url;
		} )
		.map( ( { name, slug } ) => ( {
			value: slug,
			label: name,
		} ) );

	const blockProps = useBlockProps( {
		style: { width, height, aspectRatio },
	} );
	const borderProps = useBorderProps( attributes );

	const placeholder = ( content ) => {
		return (
			<Placeholder
				className={ classnames(
					'block-editor-media-placeholder',
					borderProps.className
				) }
				withIllustration={ false }
				style={ {
					...blockProps.style,
					...borderProps.style,
				} }
			>
				{ content }
			</Placeholder>
		);
	};

	const onSelectImage = ( value ) => {
		if ( value?.id ) {
			setFeaturedImage( value.id );
		}
	};

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
				imageSizeOptions={ imageSizeOptions }
			/>
			<InspectorControls>
				<PanelBody title={ __( 'Settings' ) }>
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

	if ( ! loading && featuredImage && media ) {
		image = ( 
			<img
				className={ borderProps.className }
				src={ mediaUrl }
				alt={
					media?.alt_text
						? sprintf(
								// translators: %s: The image's alt text.
								__( 'Featured image: %s', 'cp-library' ),
								media.alt_text
							)
						: __( 'Featured image', 'cp-library' )
				}
				style={ imageStyles }
				ref={imageRef}
			/>
		)
	} else if( !loading && fallbackUrl ) {
		image = ( 
			<img
				className={ borderProps.className }
				src={ fallbackUrl }
				alt={ __( 'Featured image', 'cp-library' ) }
				style={ imageStyles }
				ref={imageRef}
			/>
		)
	}
	else {
		image = placeholder();
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
					Boolean(thumbnailAction && item?.video?.value) ?
					<div className='cpl-play-btn-overlay'>
						<Play fill='currentColor' size='30%' />
					</div> :
					!thumbnailAction ?
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
