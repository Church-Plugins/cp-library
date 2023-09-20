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
		jQuery.ajax({
			url: cplVars.ajax_url,
			data: {
				action: 'cpl_render_template',
				templateId: this.props.templateId
			},
			success: (response) => {
				this.setState({
					templateLoading: false,
					templateHTML: response
				})
			}
		})
	}

	componentDidUpdate(prevProps) {
		if (prevProps.templateId !== this.props.templateId) {
			this.setState({
				templateLoading: true,
				templateHTML: ''
			})
			this.fetchTemplate();
			console.log("Updating template")
		}
	}

	render() {
		console.log(this.state)

		if(this.props.templateId == 0) {
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
