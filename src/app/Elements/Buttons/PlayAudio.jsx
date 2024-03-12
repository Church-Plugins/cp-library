import React from 'react';
import Box from '@mui/material/Box';
import { Volume1 } from "react-feather"
import Rectangular from './Rectangular';
import { cplVar } from '../../utils/helpers';

export default function PlayAudio({onClick, disabled = false, variant = "outlined"}) {
  return (
    <Box>
      <Rectangular variant={variant} leftIcon={<Volume1 />} onClick={onClick}>
	      {cplVar('playAudio', 'i18n' )}
      </Rectangular>
    </Box>
  )
}
