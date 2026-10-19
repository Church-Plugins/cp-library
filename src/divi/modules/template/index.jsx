import React, { Component } from 'react';
import './style.scss';

class Template extends Component {
	static slug = 'cpl_template';

	constructor(props) {
		super(props);
		this.state = {
			templateLoading: true,
			templateHTML: ''
		};
	}

	fetchTemplate() {
		const editor = window.cplTemplateEditor || {}
		jQuery.ajax({
			url: editor.ajax_url || ( window.cplVars && cplVars.ajax_url ),
			data: {
				action: 'cpl_render_template',
				templateId: this.props.template_id,
				nonce: editor.nonce || ''
			},
			success: (response) => {
				this.setState({
					templateLoading: false,
					templateHTML: response
				})
			}
		})
	}

	componentDidMount() {
		this.fetchTemplate();
	}

	componentDidUpdate(prevProps) {
		if (prevProps.template_id !== this.props.template_id) {
			this.setState({
				templateLoading: true,
				templateHTML: ''
			})
			this.fetchTemplate();
		}
	}

	render() {
		if(this.props.template_id == 0) {
			return (
				<div className='cpl-divi-template-module'>No Template Selected</div>
			);
		}

		if(this.state.templateLoading) {
			return (
				<div className='cpl-divi-template-module'>Loading...</div>
			);
		}

		return (
			<div>
				<div className='cpl-divi-template-module' dangerouslySetInnerHTML={{ __html: this.state.templateHTML }}></div>
				<div>{this.props.content()}</div>
			</div>
		);
	}
}

export default Template;
