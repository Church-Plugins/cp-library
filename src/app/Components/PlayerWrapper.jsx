import { forwardRef, useEffect, useLayoutEffect, useRef, useState } from "react";
import { cplLog } from "../utils/helpers";
import VideoPlayer from 'react-player'
import Cookies from 'js-cookie'

/**
 * @typedef {import('react-player').ReactPlayerProps} ReactPlayerProps
 */

/**
 * @typedef {ReactPlayerProps & {
 *  item: object
 *  mode: string
 * }} PlayerWrapperProps
 */

/**
 * Counts the number of truthy values in an array.
 *
 * @param {Uint32Array} arr
 * @returns {number}
 */
const countTruthy = (arr) => {
  let count = 0
  for(let i = 0; i < arr.length; i++) {
    if(arr[i]) count++
  }
  return count
}

/**
 * Wrapper for the VideoPlayer component that handles tracking of video views.
 *
 * @param {PlayerWrapperProps} props
 * @param {object} ref
 * @returns {React.ReactElement}
 */
function PlayerWrapper({ item, mode, ...props }, ref) {
  const compoundId = `${mode}-${item.id}`
  const viewedRef = useRef(false)
  const isEngagedRef = useRef(false)
  /** @type {{ current: Uint32Array|null }} */
  const watchData = useRef(null)
  /** @type {{ current: number|null  }} */
  const intervalRef = useRef(null)
  /** @type {{ current: number }} */
  const lastProgressPosition = useRef(0)

  const [rand] = useState(Math.random)

  const handlePlay = () => {
    props.onPlay?.()

    if(viewedRef.current || !mode || intervalRef.current) return

    intervalRef.current = setTimeout(() => {
      viewedRef.current = true
      cplLog(item.id, mode + "_view")
    }, 30 * 1000) // TODO: should not be hardcoded
  }

  const handlePause = () => {
    props.onPause?.()

    clearTimeout(intervalRef.current)
    intervalRef.current = null
  }

  /** @param {number} duration */
  const handleDuration = (duration) => {
    props.onDuration?.(duration)

    watchData.current = new Uint32Array(Math.floor(duration))
  }

  /**
   * @param {import("react-player/base").OnProgressProps} data
   */
  const handleProgress = (data) => {    
    props.onProgress?.(data)

    if(!watchData.current) return

    const currentSecond = Math.floor(data.playedSeconds)

    if(lastProgressPosition.current !== currentSecond) {
      // increments number of views at current second
      watchData.current[currentSecond]++
      lastProgressPosition.current = currentSecond
    }
  }

  /** @param {number} seconds */
  const handleSeek = (seconds) => {
    props.onSeek?.(seconds)
  }

  const handleUnmount = () => {
    clearInterval(intervalRef.current)

    if(!watchData.current || !mode || !viewedRef.current) return

    const watchedSeconds    = countTruthy(watchData.current)
    const watchedPercentage = watchedSeconds / watchData.current.length

    const record = {
      id: compoundId,
      engaged: isEngagedRef.current
    }

    // TODO: Should not be hardcoded, get based on user preference
    if(watchedPercentage > 0.7) {
      cplLog(item.id, `engaged_${mode}_view`)
      record.engaged = true
    }

    cplLog(item.id, 'view_duration', {
      watchedSeconds,
      maxDuration: watchData.current.length
    })

    updateWatchedVideos(record)
  }

  const getWatchedVideos = () => {
    let watchedVideos = Cookies.get( 'cpl_watched_videos' )

    try {
      return JSON.parse(watchedVideos)
    }
    catch(err) {
      return []
    }
  }

  const updateWatchedVideos = (record) => {
    const watchedVideos = getWatchedVideos()

    const videoIndex = watchedVideos.findIndex(v => v.id === record.id)

    if(videoIndex !== -1) {
      watchedVideos[videoIndex] = record
    }
    else {
      watchedVideos.push(record)
    }

    Cookies.set( 'cpl_watched_videos', JSON.stringify(watchedVideos), {
      expires: 28
    } )
  }

  useEffect(() => {
    const watchedVideos = getWatchedVideos()
    
    const video = watchedVideos.find(v => {
      return v.id === compoundId
    })

    if(!video) return

    viewedRef.current = true

    if(video.engaged) {
      isEngagedRef.current = true
    }
  }, [])

  useLayoutEffect(() => {
    window.addEventListener('beforeunload', handleUnmount)

    return () => {
      handleUnmount()
      window.removeEventListener('beforeunload', handleUnmount)
    }
  }, [])

  return (
    <VideoPlayer
      {...props}
      ref={ref}
      onPlay={handlePlay}
      onPause={handlePause}
      onDuration={handleDuration}
      onProgress={handleProgress}
      onSeek={handleSeek}
      progressInterval={100}
    />
  )
}

export default forwardRef(PlayerWrapper);
