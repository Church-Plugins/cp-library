import React from 'react';
import Box from '@mui/material/Box';
import { PlayArrow } from "@mui/icons-material"
import Rectangular from '../../Elements/Buttons/Rectangular';
import { cplVar } from '../../utils/helpers';

export default function PlayVideo({onClick, disabled = false}) {
  return (
    <Box>
      <Rectangular variant="outlined" leftIcon={<PlayArrow />} onClick={onClick}>
	      {cplVar('playVideo', 'i18n' )}
      </Rectangular>
    </Box>
  )
}
