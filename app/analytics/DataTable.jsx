import React from 'react'
import DataRow from './DataRow'

export default function DataTable({ items }) {
  return (
    <table className='cpl-analytics-posts'>
      <tr>
        <th></th>
        <th>Title</th>
        <th>Views</th>
        <th>Avg duration</th>
        <th>Engaged Plays</th>
      </tr>
      {
        items.map((item) => (
          <DataRow item={item} key={item.id} />
        ))
      }
    </table>
  ) 
}