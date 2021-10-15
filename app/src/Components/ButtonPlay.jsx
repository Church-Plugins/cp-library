import IconButton from '@mui/material/IconButton';
import { Pause, PlayArrow } from "@mui/icons-material"
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
}) {
  return (
    <IconButton
      className={`roundButton__root roundButton__${variant} button__play`}
      variant={variant}
//      startIcon={leftIcon}
      fullWidth={fullWidth}
      onClick={onClick}
      disabled={disabled}
//      size="small"
      sx={{ borderRadius: 100, backgroundColor: "primary" }}
    >
      {isPlaying ? <Pause /> : <PlayArrow/>}
    </IconButton>
  );
}
