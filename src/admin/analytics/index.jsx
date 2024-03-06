import React from 'react'
import { createRoot } from 'react-dom/client'
import Analytics from './Analytics'

const analyticsElem = document.getElementById('cpl-analytics')

if(analyticsElem) {
  createRoot(analyticsElem).render(<Analytics />)
}
