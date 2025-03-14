import React from 'react';
import Box from '@mui/material/Box';
import PlayArrow from "@mui/icons-material/PlayArrow";
import Rectangular from './Rectangular';
import { cplVar } from '../../utils/helpers';

export default function PlayVideo({onClick, disabled = false, variant = "primary", href = false}) {
  return (
    <Box className='cpl-action--play-video'>
      <Rectangular variant={variant} leftIcon={<PlayArrow />} onClick={onClick} href={href}>
	      {cplVar('playVideo', 'i18n' )}
      </Rectangular>
    </Box>
  )
}
