/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function SermonSeriesEdit({
	context: { item, postType }
}) {
	const blockProps = useBlockProps({})

	if( postType !== 'cpl_item' ) {
		return (
			<div {...blockProps}>{ __( 'This block is not compatible with this post type', 'cp-library' ) }</div>
		)
	}

	const series = item?.types || []

	return (
		<>
			<div {...blockProps}>
			<span className='material-icons-outlined'>view_list</span>

			{
				postType !== 'cpl_item' ?
				__( 'This block only works in a Sermon query.', 'cp-library' ) :
				series.length ?
				series.map((singleSeries, index) => (
					<a className='cpl-series-link' key={singleSeries.id}>{singleSeries.title + (index < series.length - 1 ? ', ' : '')}</a>
				)) :
				__( 'No Series', 'cp-library' )
			}
			</div>
		</>
	)
}