import React, { useState, useEffect } from 'react'
import DataTable from './DataTable'
import { parseTime } from './helpers'

export default function Analytics() {
  const [timeframe, setTimeframe] = useState('7')
  const [page, setPage] = useState(0)
  const [items, setItems] = useState([])
  const [overview, setOverview] = useState({})

  useEffect(() => {
    setItems([])
    setOverview({})
    jQuery.ajax({
      url: window.ajaxurl,
      method: 'POST',
      data: {
        page,
        timeframe: timeframe,
        action: 'cpl-analytics-load-items'
      },
      success: (data) => {
        console.log(data)
        setItems(data)
      },
      error: console.error
    })

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
  }, [timeframe, page])

  return (
    <div className='cpl-analytics postbox'>
      <h3>Analytics</h3>
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
      
      <DataTable items={items} />

      <div className='cpl-analytics-pagination'>
        
      </div>
    </div>
  )
}

