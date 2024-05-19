import React from 'react';
import Box from '@mui/material/Box';
import { Volume1 } from "react-feather"
import Rectangular from '../../Elements/Buttons/Rectangular';
import { cplVar } from '../../utils/helpers';

export default function PlayAudio({onClick, disabled = false, variant = "outlined", href = false }) {
  return (
    <Box>
      <Rectangular variant={variant} leftIcon={<Volume1 />} onClick={onClick} href={href}>
	      {cplVar('playAudio', 'i18n' )}
      </Rectangular>
    </Box>
  )
}
