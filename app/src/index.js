import React from 'react';
import ReactDOM from 'react-dom';
import './css/index.css';
import App from './App';

const root = document.getElementById( 'cpl-root' );

const itemList = document.getElementById( 'cpl-item_list' );
const item = document.getElementById( 'cpl-item' );
const sourceList = document.getElementById( 'cpl-source_list' );
const source = document.getElementById( 'cpl-source' );
const player = document.getElementById( 'cpl-player' );

// TODO: These will be different things accpting different parameters, based on the shortcode we're fulfilling

if( root ) {
	ReactDOM.render( <App />, root );
}
if( itemList ) {
	ReactDOM.render( <App />, itemList );
}
if( item ) {
	ReactDOM.render( <App />, item );
}
if( sourceList ) {
	ReactDOM.render( <App />, sourceList );
}
if( source ) {
	ReactDOM.render( <App />, source );
}
if( player ) {
	ReactDOM.render( <App />, player );
}

