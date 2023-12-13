import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';


export default function TemplateEdit({ attributes, setAttributes }) {
	const templates = useSelect( ( select ) => {
		const { getEntityRecords } = select( 'core' );

		return getEntityRecords( 'postType', 'cpl_template', {} ) || [];
	}, [])

	const blockProps = useBlockProps({});

	const options = [
		{ label: __( 'Choose a template', 'cp-library' ), value: 0 },
		...templates.map(template => ({
			label: template.title.rendered,
			value: template.id
		}))
	]

	return (
		<div { ...blockProps }>
			<div className='cpl-template-ui-box'>
				<h3>{ __('CP Libarary Template', 'cp-library' ) }</h3>
				<SelectControl 
					label={ __('Choose a Template', 'cp-library' ) }
					value={attributes.templateId}
					options={options}
					onChange={templateId => {
						setAttributes({ templateId })
					}}
				/>
			</div>
		</div>
	);
}