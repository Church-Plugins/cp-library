import { FormTokenField } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useState } from 'react';
import { store as coreStore } from '@wordpress/core-data';


export default function RelatedPostsControls({ query, onChange, postType }) {
	if(postType !== 'cpl_item') {
		return false;
	}

	const postTypes = postType === 'cpl_item' ? {
		'cpl_speaker': {
			...cplAdmin.postTypes.cpl_speaker,
			queryKey: 'cpl_speakers'
		},
		'cpl_service_type': {
			...cplAdmin.postTypes.cpl_service_type,
			queryKey: 'cpl_service_types'
		}
	} : {}

	return Object.entries(postTypes).map(([postType, meta]) => (
		<RelatedPostsControl 
			key={postType} 
			postType={postType} 
			onChange={(changed) => {
				onChange({
					...query,
					...changed
				})
			}} 
			postIds={query[meta.queryKey] || []}
			queryKey={meta.queryKey}
			label={meta.pluralLabel}
		/>
	))
}

function RelatedPostsControl({ postType, onChange, postIds, queryKey, label }) {
	const [search, setSearch] = useState('');
	const [suggestions, setSuggestions] = useState([]);
	const [value, setValue] = useState(postIds);
	const debouncedSearch = useDebounce(setSearch, 250);

	const { searchResults, searchHasResolved } = useSelect((select) => {
		if(!search) {
			return { searchResults: [], searchHasResolved: true }
		}

		const { getEntityRecords, hasFinishedResolution } = select(coreStore);

		const selectorArgs = [
			'postType',
			postType,
			{
				search,
				per_page: 20,
				exclude: postIds,
				_fields: ['id', 'title', 'slug']
			}
		];

		return {
			searchResults: getEntityRecords(...selectorArgs),
			searchHasResolved: hasFinishedResolution('getEntityRecords', selectorArgs)
		}
	}, [search, postIds, postType])

	const handleChange = (newPostIds) => {
		setValue(newPostIds);
		onChange({
			[queryKey]: newPostIds
		})
		setSuggestions([]);
	}

	React.useEffect( () => {
		if ( ! searchHasResolved || ! searchResults ) return;
		setSuggestions(searchResults.map(({ id, title }) => ({ id, label: title.rendered })));
	}, [ searchResults, searchHasResolved ] );

	return (
		<FormTokenFieldPosts
			postType={postType}
			value={value}
			label={label}
			onChange={handleChange}
			onInputChange={debouncedSearch}
			suggestions={suggestions}
			__experimentalShowHowTo={false}
		/>
	)
}

function FormTokenFieldPosts({ postType, value: controlledValue, suggestions, onChange, ...props }) {
	const [value, setValue] = useState([]);

	const formattedValue = useSelect((select) => {
		const { getEntityRecords } = select(coreStore);

		if(!controlledValue.length) return [];

		const posts =  getEntityRecords(
			'postType',
			postType,
			{
				per_page: controlledValue.length,
				_fields: ['id', 'title', 'slug'],
				include: controlledValue
			}
		)

		if(!posts) return false;

		return posts.map(post => ({ id: post.id, value: post.title.rendered }))
	}, [controlledValue])

	React.useEffect(() => {
		if(formattedValue) {
			setValue(formattedValue)
		}
	}, [formattedValue])

	const handleChange = (newItems) => {
		const formattedItems = []

		for(const item of newItems) {
			if(item?.id) {
				formattedItems.push(item)
				continue;
			}
			const suggestion = suggestions.find((s) => s.label === item)
			if(suggestion) {
				formattedItems.push({ id: suggestion.id, value: suggestion.label })
			}
		}
		setValue(formattedItems)
		onChange?.(formattedItems.map(({ id }) => id))
	}

	return (
		<FormTokenField
			value={value}
			onChange={handleChange}
			suggestions={suggestions.map(({ label }) => label)}
		 {...props}
		/>
	)
}