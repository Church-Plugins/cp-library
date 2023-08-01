/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function SermonSeriesEdit({
	context: { item, postType }
}) {
	const series = item?.types || []

	const blockProps = useBlockProps({})

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>view_list</span>

			{
				postType !== 'cpl_item' ?
				__( 'This block only works in a Sermon query.', 'cp-library' ) :
				series.length ?
				series.map((series, index) => (
					<span className='cpl-series-link' key={series.id}>{series.title}{index < series.length - 1 && ','}</span>
				)) :
				__( 'No Series', 'cp-library' )
			}
			</div>
		</>
	)
}