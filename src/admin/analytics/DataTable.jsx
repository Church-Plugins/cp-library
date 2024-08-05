import React from 'react'
import DataRow from './DataRow'

export default function DataTable({ items, loading = false }) {
  const skeletonItems = Array(10).fill(null)

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
        loading ? 
        skeletonItems.map((_, index) => (
          <tr key={index} className={`cpl-loading-skeleton ${index % 2 ? 'dark' : ''}`}>
            <td><div className='cpl-analytics-sermon--thumbnail'></div></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
          </tr>
        )) :
        items.map((item) => <DataRow item={item} key={item.id} /> )
      }
    </table>
  ) 
}