import CardMedia from '@mui/material/CardMedia';
import Portal from '@mui/material/Portal';
import Drawer from '@mui/material/Drawer';
import Box from '@mui/material/Box';

export default function AudioPlayer({
  open = false,
  src,
}) {
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
        >
          {open ? (
            <CardMedia component="audio" controls src={src} sx={{ width: "100%" }}>
              Your browser does not support the audio element.
            </CardMedia>
          ) : null}
        </Box>
      </Drawer>
    </Portal>
  );
}
