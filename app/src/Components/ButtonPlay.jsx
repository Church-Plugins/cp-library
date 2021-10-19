import IconButton from '@mui/material/IconButton';
import { PauseCircle, PlayCircle } from "@mui/icons-material"
import * as React from 'react';
import { styled } from '@mui/material/styles';
import Stack from '@mui/material/Stack';
import { purple } from '@mui/material/colors';

export default function ButtonPlay({
  // See MUI docs for possible values
  variant = "contained",
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
      {isPlaying ? <PauseCircle color="primary" sx={{fontSize: size}} /> : <PlayCircle color="primary" sx={{fontSize: size}} />}
    </IconButton>
  );
}
