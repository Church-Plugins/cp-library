import React from 'react'
import ReactDOM from 'react-dom'

import Dashboard from './Dashboard'

const root = document.getElementById('cpl-migration-root')

console.log("Hello, react")

if(root) {
	const details = JSON.parse(root.dataset.details)

	ReactDOM.render(<Dashboard data={details} />, root)
}