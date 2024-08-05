import IconButton from '@mui/material/IconButton';
import PauseCircle from "@mui/icons-material/PauseCircle";
import PlayCircle from "@mui/icons-material/PlayCircle";
import Pause from "@mui/icons-material/Pause";
import PlayArrow from "@mui/icons-material/PlayArrow";
import CircularProgress from '@mui/material/CircularProgress';

export default function PlayPause({
  // See MUI docs for possible values
  variant = "contained",
	circleIcon = true,
  leftIcon,
  fullWidth = false,
  onClick,
  disabled = false,
  children,
	isPlaying,
	size = 36,
	playedSeconds = 1,
	...props
}) {

  return isPlaying && !playedSeconds ? (
		  <IconButton
				{...props}
			  className={`roundButton__root roundButton__${variant} button__play button__loading`}
			  variant={variant}
			  onClick={onClick}
			  sx={{padding: circleIcon ? size * .25 + 'px' : 0}}
				aria-label='Loading playback'
		  >
			  <CircularProgress size={size * .5}/>
		  </IconButton>
	  ) : (
		  <IconButton
				{...props}
			  className={`roundButton__root roundButton__${variant} button__play`}
			  variant={variant}
			  onClick={onClick}
			  disabled={disabled}
			  sx={{padding: 0}}
				aria-label='Play/pause playback'
		  >
			  {circleIcon ? (
				  <>
					  {isPlaying ? <PauseCircle sx={{fontSize: size}}/> :
						  <PlayCircle sx={{fontSize: size}}/>}
				  </>
			  ) : (
				  <>
					  {isPlaying ? <Pause sx={{fontSize: size}}/> : <PlayArrow
						  sx={{fontSize: size}}/>}
				  </>
			  )}
		  </IconButton>
	  );
}
