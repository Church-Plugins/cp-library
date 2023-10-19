import { registerPlugin } from '@wordpress/plugins';
import Editor from './Editor';

registerPlugin( 'cp-library-template-editor', {
	render: (props) => <Editor {...props} />
} );