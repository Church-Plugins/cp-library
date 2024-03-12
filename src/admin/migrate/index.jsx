import React from 'react'
import { createRoot } from 'react-dom/client'

import Dashboard from './Dashboard'

const root = document.getElementById('cpl-migration-root')

if(root) {
	const details = JSON.parse(root.dataset.details)

	createRoot(root).render(<Dashboard data={details} />)
}