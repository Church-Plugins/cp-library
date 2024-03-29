import React, { useState, useEffect } from 'react'
import DataTable from './DataTable'
import { parseTime } from './helpers'

import Pagination from './Pagination'

export default function Analytics() {
  const [timeframe, setTimeframe] = useState('7')
  const [page, setPage] = useState(0)
  const [items, setItems] = useState([])
  const [overview, setOverview] = useState({})
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    jQuery.ajax({
      url: window.ajaxurl,
      method: 'POST',
      data: {
        page,
        timeframe: timeframe,
        action: 'cpl-analytics-load-items'
      },
      success: (data) => {
        setItems(data)
        setLoading(false)
      },
      error: console.error
    })
  }, [timeframe, page])

  useEffect(() => {
    jQuery.ajax({
      url: window.ajaxurl,
      method: 'POST',
      data: {
        timeframe: timeframe,
        action: 'cpl-analytics-get-overview'
      },
      success: (data) => {
        setOverview(data)
      },
      error: console.error
    })
  }, [timeframe])

  return (
    <div className='cpl-analytics postbox'>
      <h3>Analytics (Beta)</h3>
      <div className='cpl-analytics--actions'>
        <select className='cpl-analytics-timeframe' value={timeframe} onChange={e => setTimeframe(e.target.value)}>
          <option value='7'>Past 7 days</option>
          <option value='30'>Past month</option>
          <option value='365'>Past year</option>
        </select>

        <div className='cpl-analytics--overview'>
          <div className='cpl-analytics--overview--data'>
            <span className='material-icons'>smart_display</span>
            <span className='cpl-analytics--total-views'>{overview.video_views}</span>
          </div>
          <span className='cpl-analytics--overview--title'>Video plays</span>
          <span>&gt; 30s</span>
        </div>

        <div className='cpl-analytics--overview'>
          <div className='cpl-analytics--overview--data'>
            <span className='material-icons'>volume_up</span>
            <span className='cpl-analytics--total-views'>{overview.audio_views}</span>
          </div>
          <span className='cpl-analytics--overview--title'>Audio plays</span>
          <span>&gt; 30s</span>
        </div>

        <div className='cpl-analytics--overview'>
          <div className='cpl-analytics--overview--data'>
            <span className="material-icons">visibility</span>
            <span className='cpl-analytics--total-views'>{parseTime(overview.average_duration)}</span>
          </div>
          <span className='cpl-analytics--overview--title'>Avg watch time</span>
        </div>

        <div className='cpl-analytics--overview'>
          <div className='cpl-analytics--overview--data'>
            <span className="material-icons">check</span>
            <span className='cpl-analytics--total-views'>{overview.engaged_views}</span>
          </div>
          <span className='cpl-analytics--overview--title'>Engaged plays</span>
          <span>Watched 70% or more</span>
        </div>

      </div>

      <DataTable items={items} loading={loading} />

      <div className='cpl-analytics-pagination'>
        {
          overview &&
          <Pagination
            pages={overview.pages}
            onPageChange={page => setPage(page.selected)}
            currentPage={page}
          />
        }
      </div>
    </div>
  )
}

