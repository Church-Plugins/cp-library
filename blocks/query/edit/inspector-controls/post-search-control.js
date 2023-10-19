import { useDebounce } from '@wordpress/compose';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { FormTokenField } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { coreStore } from '@wordpress/core-data';

export default function PostSearchControl( { value, postType, onChange, ...props } ) {
	const [ search, setSearch ] = useState('');
	const [ suggestions, setSuggestions ] = useState([]);
	const debouncedSearch = useDebounce( setSearch, 250 );

	const { searchResults, searchHasResolved } = useSelect(
		( select ) => {
			if ( ! search ) {
				return { searchResults: [], searchHasResolved: true };
			}

			const { getEntityRecords, hasFinishedResolution } =
				select( 'core' );

			const selectorArgs = [ 'postType', postType, { search, per_page: 10, _fields: 'id,title' } ];
			
			return {
				searchResults: getEntityRecords( ...selectorArgs ),
				searchHasResolved: hasFinishedResolution(
					'getEntityRecords',
					selectorArgs
				),
			};
		},
		[ search ]
	);

	const posts = useSelect((select) => {
		const { getEntityRecords } = select( 'core' )

		return getEntityRecords(
			'postType', 
			postType, 
			{ include: value, per_page: 10, _fields: 'id,title' }
		) || []
	}, [value])

	// Update suggestions only when the query has resolved.
	useEffect( () => {
		if ( ! searchHasResolved ) return;

		setSuggestions( [ ...new Set(searchResults.map( ( result ) => result.title.rendered ) ) ] );
	}, [ searchResults, searchHasResolved ] );

	return (
		<div className="block-library-query-inspector__taxonomy-control">
			<FormTokenField
				label={ __( 'Search', 'cp-library' ) }
				value={posts.map(post => post.title.rendered)}
				onInputChange={ debouncedSearch }
				suggestions={ suggestions }
				onChange={ (items) => {
					const values = new Set()

					for(const item of items) {
						values.add(searchResults.find(r => r.title.rendered === item).id)
					}
					
					// return an invalid post as we don't want anything to display if nothing is selected
					onChange(items.length ? Array.from(values) : [ 0 ])
				} }
				maxLength={1}
				__experimentalShowHowTo={ false }
			/> 
		</div>
	);
}
