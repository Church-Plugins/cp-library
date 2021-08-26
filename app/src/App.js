import logo from './logo.svg';
import './App.css';

function App() {

	let now = new Date();
	let showNow = now.toString();

	let params = window.cplParams;

	return (
		<div className="App">
			<div>Current time: {showNow}</div>
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
