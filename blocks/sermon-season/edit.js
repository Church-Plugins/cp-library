/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch'

export default function SermonSeasonEdit({
	context: { postId, postType, queryId }
}) {
	const [scriptures, setScriptures] = React.useState([])

	React.useEffect(() => {
		apiFetch({
			path: `/cp-library/v1/items/${postId}`
		}).then(data => {
			if(data?.scripture) {
				setScriptures(Object.values(data.scripture))
			}
		})
	}, [postId])

	const blockProps = useBlockProps({})

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>menu_book</span>
			{
				scriptures.length ?
				scriptures.map((scripture, index) => (
					<span className='cpl-scripture-link' key={scripture.slug}>{scripture.name}{index < scriptures.length - 1 && ','}</span>
				)) :
				__( 'No Scriptures', 'cp-library' )
			}
			</div>
		</>
	)
}