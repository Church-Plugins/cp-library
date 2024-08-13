import Box from "@mui/material/Box";
import PlayVideo from "../../Elements/Buttons/PlayVideo";
import PlayAudio from "../../Elements/Buttons/PlayAudio";
import { usePersistentPlayer } from "../../Contexts/PersistentPlayerContext";
import ShareButton from "./ShareButton";
import { isURL } from "../../utils/helpers";

export default function Controls({
  isVariation,
  item,
  handleSelect
}) {
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer } = usePersistentPlayer()
	const containerClass   = isVariation ? ' cpl-is-variation' : '';
  const speakers = item.speakers?.map(speaker => speaker.title).join(', ');

  return (
    <Box className={"itemDetail__actions" + containerClass}>

      {
        isVariation &&
        <Box className="cpl-list-item--details">
          <h6 className="cpl-list-item--variations--title">{ item.variation }</h6>
          <div className="cpl-item--speakers"><span className="material-icons-outlined">person</span> { speakers }</div>
        </Box>
      }

      {item.video.value && (
        <Box className="itemDetail__playVideo">
          <PlayVideo
            onClick={() => {
              handleSelect({
                item         : item,
                mode         : isURL(item.video.value) ? 'video' : 'embed',
                url          : item.video.value,
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
                mode         : isURL(item.audio) ? 'audio' : 'embed',
                url          : item.audio,
                isPlaying    : true,
                playedSeconds: 0.0,
              })
            }}
            variant={ item.layout === 'vertical' ? 'light' : 'outlined' }
          />

        </Box>
      )}

      <ShareButton item={item} variant={ item.layout === 'vertical' ? 'light' : 'outlined' } />
    </Box>
  )
}
