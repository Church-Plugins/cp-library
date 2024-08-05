import React from 'react'
import { createRoot } from 'react-dom/client'
import Analytics from './Analytics'
import './index.scss'

const analyticsElem = document.getElementById('cpl-analytics')

if(analyticsElem) {
  createRoot(analyticsElem).render(<Analytics />)
}
