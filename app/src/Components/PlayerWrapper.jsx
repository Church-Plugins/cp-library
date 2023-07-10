import { forwardRef, useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { cplLog } from "../utils/helpers";
import VideoPlayer from 'react-player'
import Cookies from 'js-cookie'

const countTruthy = (arr) => {
  let count = 0
  for(let i = 0; i < arr.length; i++) {
    if(arr[i]) count++
  }
  return count
}


export default forwardRef(function PlayerWrapper({ item, mode, ...props }, ref) {
  const compoundId = `${mode}-${item.id}`
  const viewedRef = useRef(false)
  const isEngagedRef = useRef(false)
  const watchData = useRef()
  const intervalRef = useRef(null)
  const lastProgressPosition = useRef()

  const handlePlay = () => {
    props.onPlay?.()

    if(viewedRef.current || !mode || intervalRef.current) return

    console.log("Starting timeout")

    intervalRef.current = setTimeout(() => {
      viewedRef.current = true
      console.log("View occured")
      cplLog(item.id, mode + "_view")
    }, 30 * 1000) // TODO: should not be hardcoded
  }

  const handlePause = () => {
    props.onPause?.()

    console.log("Clearing timeout", intervalRef.current)
    clearTimeout(intervalRef.current)
    intervalRef.current = null
  }

  const handleDuration = (duration) => {
    props.onDuration?.(duration)

    watchData.current = new Uint32Array(Math.floor(duration))
  }

  const handleProgress = (played, loaded) => {    
    props.onProgress?.(played, loaded)

    if(!watchData.current) return

    const currentSecond = Math.floor(played.playedSeconds)

    if(lastProgressPosition.current !== currentSecond) {
      // increments number of views at current second
      watchData.current[currentSecond]++
      lastProgressPosition.current = currentSecond
    }
  }

  const handleSeek = (seconds) => {
    props.onSeek?.(seconds)
  }

  const handleUnmount = () => {
    console.log("Unmounting")

    clearInterval(intervalRef.current)

    if(!watchData.current || !mode || !viewedRef.current) return

    console.log("Unload in progress")

    const watchedSeconds = countTruthy(watchData.current)
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

    console.log("Saving record", record)

    cplLog(item.id, 'view_duration', watchedSeconds)

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

    console.log("Getting user watched videos", watchedVideos)
    
    const video = watchedVideos.find(v => {
      return v.id === compoundId
    })

    console.log("Is this video already watched?", video)

    if(!video) return

    viewedRef.current = true

    if(video.engaged) {
      console.log("It was an engaged view")
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
})

/*
<VideoPlayer
  ref={playerInstance}
  className="itemDetail__video"
  url={playingURL}
  width="100%"
  height="100%"
  controls={false}
  playbackRate={playbackRate}
  playing={isPlaying}
  onPlay={() => setIsPlaying(true)}
  onPause={() => setIsPlaying(false)}
  onDuration={duration => {
    setDuration(duration);
    playerInstance.current.seekTo(playedSeconds, 'seconds');
    setIsPlaying(true);
  }}
  onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
  progressInterval={100}
/>
*/