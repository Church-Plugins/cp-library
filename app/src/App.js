import logo from './logo.svg';
import './App.css';

function App() {

	let now = new Date();
	let showNow = now.toString();

	let showParams = "";

	// normalize input parameters
	let params = null;
	if( window && window.cplParams ) {
		params = window.cplParams;
	} else {
		params = {};
	}



	return (
		<div className="App">
			<div>Current time: {showNow}</div>
			<div class="align-left">Category: {params.category}</div>
			<div class="align-left">Slug: {params.slug}</div>
			<div class="align-left">ID: {params.id}</div>
			<div class="align-left">Foo: {params.foo}</div>
			<div class="align-left">Baz: {params.baz}</div>
			<header className="App-header">
				<img src={logo} className="App-logo" alt="logo" />
				<p>
					Edit <code>src/App.js</code> and save to reload.
				</p>
			</header>
		</div>
	);
}

export default App;
