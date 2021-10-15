import { useRef } from "react";
import Portal from '@mui/material/Portal';
import Drawer from '@mui/material/Drawer';
import Box from '@mui/material/Box';
import ReactPlayer from 'react-player';

export default function AudioPlayer({
  open = false,
  src,
  onStart,
  onPlay,
  onProgress,
  onPause,
  onEnded,
}) {
  const player = useRef();

  return (
    <Portal>
      <Drawer
        className="audioPlayer__root"
        open={open}
        anchor="bottom"
        hideBackdrop={true}
        variant="persistent"
        PaperProps={{ sx: { border: 0, pointerEvents: "none", background: "transparent" } }}
      >
        <Box
          className="audioPlayer__gradient"
          sx={{ background: "linear-gradient(0deg, black, transparent)" }}
          height={100}
        />
        <Box
          className="audioPlayer__controls"
          paddingX={2}
          paddingY={1}
          sx={{ backgroundColor: "black", pointerEvents: "auto" }}
          height={66}
        >
          {open ? (
            <ReactPlayer
              ref={player}
              controls
              url={src}
              width="100%"
              height="100%"
              onStart={onStart}
              onPlay={onPlay}
              onProgress={onProgress}
              onPause={onPause}
              onEnded={onEnded}
              stopOnUnmount={true}
              progressInterval={50}
            >
              Your browser does not support the audio element.
            </ReactPlayer>
          ) : null}
        </Box>
      </Drawer>
    </Portal>
  );
}
