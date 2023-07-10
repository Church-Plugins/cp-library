import React from 'react'
import { parseTime, validNumber } from './helpers'


export default function DataRow({ item }) {
  const percentageEngaged = validNumber(item.views) === 0 ? 0 : Math.floor(
    validNumber(item.engaged_views) / validNumber(item.views) * 100
  )

  let timeString = '0:00' // default

  if(item.view_duration) {
    timeString = parseTime(item.view_duration)
  }
  
  return (
    <tr className='cpl-analytics-sermon' key={item.id}>
      <td><div className='cpl-analytics-sermon--thumbnail'></div></td>
      <td className='cpl-analytics-sermon--title'>{item.title}</td>
      <td className='cpl-analytics-sermon--plays'>{item.views || 0}</td>
      <td className='cpl-analytics-sermon--avd'>{timeString}</td>
      <td className='cpl-analytics-sermon--engagement'>{percentageEngaged}%</td>
    </tr>
  )
}
