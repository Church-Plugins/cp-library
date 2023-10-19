import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { PanelRow, SelectControl, Button, Flex, __experimentalInputControl as InputControl } from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import PostSearchControl from '../../../blocks/query/edit/inspector-controls/post-search-control';
import { parse } from '@wordpress/blocks';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { copy } from '@wordpress/icons';

function Editor({ 
	postType,
	postId, 
	updatePostContent,
	...props
 }) {
	console.log("Editor", props)

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta', postId);

	const { template_type, template_location, post_preview_id } = meta;

	function resetPostContent() {
		const templateDirectory = '/wp-content/plugins/cp-library/templates/default_content';
		const templateFile = `${templateDirectory}/${getTemplateFile()}.html`

		jQuery.ajax({
			url: templateFile,
			success: (data) => {
				console.log("Updating post content", data)
				updatePostContent(data);
			},
			error: (error) => {
				console.log("Template file not found.", error)
			}
		})
	}

	function getTemplateFile() {
		if( template_type === 'shortcode' ) {
			return 'shortcode';
		}
		return `${template_type}-${template_location}`;
	}

	const templateTypeOptions = [
		{ label: __('Shortcode', 'cp-library'),    value: 'shortcode' },
		{ label: __('Archive Page', 'cp-library'), value: 'archive' },
		{ label: __('Single Page', 'cp-library'),  value: 'single' },
	]

	const locationOptions = [
		{ label: __('Series', 'cp-library'),  value: 'cpl_item_type' },
		{ label: __('Sermons', 'cp-library'), value: 'cpl_item' },
	]

	return (
		<>
			<PluginDocumentSettingPanel name="cp-library-template-type" title={__('Template Options', 'cp-library')}>
				<SelectControl
					label={__('Template Type', 'cp-library')}
					value={template_type}
					options={templateTypeOptions}
					onChange={(type) => {
						setMeta({ ...meta, template_type: type })
					}}
				/>

				{
					!template_type || template_type === 'shortcode' ?
					<Flex gap="8px" justify="start">
						<InputControl type="text" disabled value={`[cpl_template id=${postId}]`} />
						<Button 
							variant="tertiary"
							size="compact"
							onClick={() => navigator.clipboard.writeText(`[cpl_template id=${postId}]`)}
							icon={copy}
						/>
					</Flex> :
					<SelectControl
						label={__('Display Location', 'cp-library')}
						value={template_location}
						options={locationOptions}
						onChange={(location) => {
							setMeta({ ...meta, template_location: location })
						}}
						style={{ flex: '1' }}
					/>
				}

				{
					template_type === 'single' &&
					<PanelRow>
						<PostSearchControl
							label={__('Select a  single post to preview.', 'cp-library')}
							value={ post_preview_id ? [ post_preview_id ] : [] }
							postType={ template_location }
							onChange={ (value) => {
								setMeta({ ...meta, post_preview_id: value[0] || 0 })
							} }
						/>
					</PanelRow>
				}
			</PluginDocumentSettingPanel>
		</>
	)
}

export default compose([
	withSelect((select) => {
		return {
			postType:    select('core/editor').getCurrentPostType(),
			postId:      select('core/editor').getCurrentPostId(),
			postContent: select('core/editor').getEditedPostContent(),
			slug:        select('core/editor').getEditedPostAttribute('slug'),
		}	
	}),
	withDispatch((dispatch, { postId, postType, ...props }) => {
		return {
			setPostName: (name) => {
				dispatch(coreStore).editEntityRecord('postType', postType, postId, { slug: name })
			},
			updatePostContent: (content) => {
				const blocks = parse(content);
				dispatch('core/block-editor').resetBlocks(blocks);
			},
		}
	})
])(Editor);