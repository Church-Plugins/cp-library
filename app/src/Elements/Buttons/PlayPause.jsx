import IconButton from '@mui/material/IconButton';
import { PauseCircle, PlayCircle, Pause, PlayArrow } from "@mui/icons-material"
import * as React from 'react';

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
	size = 36
}) {
  return (
    <IconButton
      className={`roundButton__root roundButton__${variant} button__play`}
      variant={variant}
      onClick={onClick}
      disabled={disabled}
      sx={{padding: 0}}
    >
	    {circleIcon ? (
	    	<>
	        {isPlaying ? <PauseCircle sx={{fontSize: size}} /> : <PlayCircle sx={{fontSize: size}} />}
	      </>
			) : (
        <>
          {isPlaying ? <Pause sx={{fontSize: size}} /> : <PlayArrow sx={{fontSize: size}} />}
        </>
	    )}
    </IconButton>
  );
}
