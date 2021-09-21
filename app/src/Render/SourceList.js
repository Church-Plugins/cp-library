import logo from '../images/logo.svg';
import '../css/App.css';

function SourceList() {

	let now = new Date();
	let showNow = now.toString();

	// normalize input parameters
	let params = null;
	if( window && window.cplParams ) {
		params = window.cplParams;
	} else {
		params = {};
	}

	return (
		<div className="SourceList">
			<div>Source List rendered at: {showNow}</div>

			<div class="cpl-source-list">


				<div class="cpl-source-list--source--thumb">
					Thumb
				</div>


				<div class="cpl-source-list--source--details">
					Details
				</div>


				<div class="cpl-source-list--source--actions">
					Actions
				</div>

			</div>
		</div>
	);
}

export default SourceList;