/**
 * WordPress dependencies
 */
import {
	PanelBody,
	TextControl,
	RangeControl,
	Notice,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalNumberControl as NumberControl,
	SelectControl
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { debounce } from '@wordpress/compose';
import { useEffect, useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import OrderControl from './order-control';
import AuthorControl from './author-control';
import ParentControl from './parent-control';
import { TaxonomyControls } from './taxonomy-controls';
import {
	useIsPostTypeHierarchical,
	useAllowedControls,
	isControlAllowed,
	useTaxonomies,
} from '../../utils';

export default function QueryInspectorControls( {
	attributes,
	setQuery,
	setDisplayLayout,
} ) {
	const { query, displayLayout } = attributes;

	const {
		order,
		orderBy,
		author: authorIds,
		postType,
		inherit,
		taxQuery,
		parents,
	} = query;

	const allowedControls = useAllowedControls( attributes );

	const [ showSticky, setShowSticky ] = useState( postType === 'post' );

	const taxonomies = useTaxonomies( postType );

	const isPostTypeHierarchical = useIsPostTypeHierarchical( postType );

	useEffect( () => {
		setShowSticky( postType === 'post' );
	}, [ postType ] );

	const [ querySearch, setQuerySearch ] = useState( query.search );
	const onChangeDebounced = useCallback(
		debounce( () => {
			if ( query.search !== querySearch ) {
				setQuery( { search: querySearch } );
			}
		}, 250 ),
		[ querySearch, query.search ]
	);
	useEffect( () => {
		onChangeDebounced();
		return onChangeDebounced.cancel;
	}, [ querySearch, onChangeDebounced ] );

	const showInheritControl = isControlAllowed( allowedControls, 'inherit' );

	const showPostTypeControl =
		! inherit && isControlAllowed( allowedControls, 'postType' );
	const showColumnsControl = displayLayout?.type === 'flex';
	const showOrderControl =
		! inherit && isControlAllowed( allowedControls, 'order' );
	const showStickyControl =
		! inherit &&
		showSticky &&
		isControlAllowed( allowedControls, 'sticky' );
	const showSettingsPanel =
		showInheritControl ||
		showPostTypeControl ||
		showColumnsControl ||
		showOrderControl ||
		showStickyControl;
	const showTaxControl =
		!! taxonomies?.length &&
		isControlAllowed( allowedControls, 'taxQuery' );
	const showAuthorControl = isControlAllowed( allowedControls, 'author' );
	const showSearchControl = isControlAllowed( allowedControls, 'search' );
	const showParentControl =
		isControlAllowed( allowedControls, 'parents' ) &&
		isPostTypeHierarchical;

	const showFiltersPanel =
		showTaxControl ||
		showAuthorControl ||
		showSearchControl ||
		showParentControl;

	return (
		<>
			{ showSettingsPanel && (
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'cp-library' ) }>
						<SelectControl
							label={ __( 'Type', 'cp-library' ) }
							value={ postType }
							onChange={(postType) => {
								setQuery({ postType })
							}}
							options={[
								{ label: __( 'Series', 'cp-library' ), value: 'cpl_item_type' },
								{ label: __( 'Sermons', 'cp-library' ), value: 'cpl_item' }
							]}
						/>

						<NumberControl
							__unstableInputWidth="60px"
							label={ __( 'Items to show', 'cp-library' ) }
							labelPosition="edge"
							min={ 1 }
							max={ 100 }
							onChange={ ( value ) => {
								if (
									isNaN( value ) ||
									value < 1 ||
									value > 100
								) {
									return;
								}
								setQuery( {
									perPage: value,
								} );
							} }
							step="1"
							value={ query.perPage }
							isDragEnabled={ false }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Filter by keyword', 'cp-library' ) }
							value={ querySearch }
							onChange={ setQuerySearch }
						/>
						{ showColumnsControl && (
							<>
								<RangeControl
									__nextHasNoMarginBottom
									label={ __( 'Columns' ) }
									value={ displayLayout.columns }
									onChange={ ( value ) =>
										setDisplayLayout( { columns: value } )
									}
									min={ 2 }
									max={ Math.max( 6, displayLayout.columns ) }
								/>
								{ displayLayout.columns > 6 && (
									<Notice
										status="warning"
										isDismissible={ false }
									>
										{ __(
											'This column count exceeds the recommended amount and may cause visual breakage.'
										) }
									</Notice>
								) }
							</>
						) }
						{ showOrderControl && (
							<OrderControl
								{ ...{ order, orderBy } }
								onChange={ setQuery }
							/>
						) }
					</PanelBody>
				</InspectorControls>
			) }
			{ ! inherit && showFiltersPanel && (
				<InspectorControls>
					<ToolsPanel
						className="block-library-query-toolspanel__filters"
						label={ __( 'Filters' ) }
						resetAll={ () => {
							setQuery( {
								author: '',
								parents: [],
								search: '',
								taxQuery: null,
							} );
						} }
					>
						{ showTaxControl && (
							<ToolsPanelItem
								label={ __( 'Taxonomies' ) }
								hasValue={ () =>
									Object.values( taxQuery || {} ).some(
										( terms ) => !! terms.length
									)
								}
								onDeselect={ () =>
									setQuery( { taxQuery: null } )
								}
							>
								<TaxonomyControls
									onChange={ setQuery }
									query={ query }
								/>
							</ToolsPanelItem>
						) }
						{ showAuthorControl && (
							<ToolsPanelItem
								hasValue={ () => !! authorIds }
								label={ __( 'Authors' ) }
								onDeselect={ () => setQuery( { author: '' } ) }
							>
								<AuthorControl
									value={ authorIds }
									onChange={ setQuery }
								/>
							</ToolsPanelItem>
						) }
						{ showParentControl && (
							<ToolsPanelItem
								hasValue={ () => !! parents?.length }
								label={ __( 'Parents' ) }
								onDeselect={ () => setQuery( { parents: [] } ) }
							>
								<ParentControl
									parents={ parents }
									postType={ postType }
									onChange={ setQuery }
								/>
							</ToolsPanelItem>
						) }
					</ToolsPanel>
				</InspectorControls>
			) }
		</>
	);
}
