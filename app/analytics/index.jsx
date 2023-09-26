import React from 'react'
import ReactDOM from 'react-dom'
import Analytics from './Analytics'

const analyticsElem = document.getElementById('cpl-analytics')


if(analyticsElem) {
  ReactDOM.render(<Analytics />, analyticsElem)
}



