import Box from "@mui/material/Box";
import PlayVideo from "../../Elements/Buttons/PlayVideo";
import PlayAudio from "../../Elements/Buttons/PlayAudio";
import { usePersistentPlayer } from "../../Contexts/PersistentPlayerContext";
import ShareButton from "./ShareButton";

export default function Controls({ 
  isVariation,
  item,
  handleSelect
}) {
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer } = usePersistentPlayer()

  const speakers = item.speakers?.map(speaker => speaker.title).join(', ');

  return (
    <Box className="itemDetail__actions">

      {
        isVariation &&
        <Box>
          <div className="cpl-variation-name">{ item.title }</div>
          <div className="cpl-variation-speaker">Speaker: { speakers }</div>
        </Box>
      }

      {item.video.value && (
        <Box className="itemDetail__playVideo">
          <PlayVideo
            onClick={() => {
              handleSelect({
                item         : item,
                mode         : 'video',
                isPlaying    : true,
                playedSeconds: 0.0,
              })
            }}
            fullWidth
          />
        </Box>
      )}

      {item.audio && (
        <Box className="itemDetail__playAudio" >
          <PlayAudio
            onClick={() => {
              handleSelect({
                item         : item,
                mode         : 'audio',
                isPlaying    : true,
                playedSeconds: 0.0,
              })
            }}
          />

        </Box>
      )}

      <ShareButton item={item} />
    </Box>
  )
}