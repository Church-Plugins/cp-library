import { forwardRef, useLayoutEffect, useRef, useState } from "react";
import { cplLog } from "../utils/helpers";
import VideoPlayer from 'react-player'

const countTruthy = (arr) => {
  let count = 0
  for(let i = 0; i < arr.length; i++) {
    if(arr[i]) count++
  }
  return count
}

export default forwardRef(function PlayerWrapper({ item, mode, ...props }, ref) {

  console.log(item, mode)
  const [viewed, setViewed] = useState(false)
  const watchData = useRef()
  const intervalRef = useRef()
  const lastProgressPosition = useRef()

  const handlePlay = () => {
    props.onPlay?.()

    console.log("Playing")

    if(viewed || !mode) return

    intervalRef.current = setTimeout(() => {
      setViewed(true)
      cplLog(item.id, mode + "_view")
    }, 5000)
  }

  const handlePause = () => {
    props.onPause?.()

    clearTimeout(intervalRef.current)

    console.log("Paused")
  }

  const handleDuration = (duration) => {
    console.log("Duration", duration)

    props.onDuration?.(duration)

    watchData.current = new Uint32Array(Math.floor(duration))
  }

  const handleProgress = (played, loaded) => {    
    props.onProgress?.(played, loaded)

    if(!watchData.current) return

    const currentSecond = Math.floor(played.playedSeconds)

    if(lastProgressPosition.current !== currentSecond) {
      // increments views at current second
      watchData.current[currentSecond]++
      lastProgressPosition.current = currentSecond

      console.log("Watched seconds: " + countTruthy(watchData.current))
    }
  }

  const handleSeek = (seconds) => {
    props.onSeek?.(seconds)
  }

  const handleUnload = () => {
    if(!watchData.current) return

    const watchedSeconds = countTruthy(watchData.current)
    const watchedPercentage = watchedSeconds / watchData.current.length

    console.log("UNLOADING")

    alert("MODE: " + mode)

    if(watchedPercentage > 0.1) {
      cplLog(item.id, `engaged_${mode || ''}_view`)
      console.log("Engaged view occured")
    }

    cplLog(item.id, 'view_duration', watchedSeconds)
  }

  const handleUnmount = () => {
    if(!watchData.current) return

    sessionStorage.setItem('cpl_item_id', item.id)
    sessionStorage.setItem('cpl_watch_data', watchData.current.buffer)

    const watchedSeconds = countTruthy(watchData.current)
    const watchedPercentage = watchedSeconds / watchData.current.length

    console.log("UNLOADING")

    alert("MODE: " + mode)

    if(watchedPercentage > 0.1) {
      cplLog(item.id, `engaged_${mode || ''}_view`)
      console.log("Engaged view occured")
    }

    cplLog(item.id, 'view_duration', watchedSeconds)

    console.log("Unmounting")

    return false 
  }

  useLayoutEffect(() => {
    window.addEventListener('beforeunload', handleUnload)

    return () => {
      handleUnmount()
      window.removeEventListener('beforeunload', handleUnload)
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