import IconButton from '@mui/material/IconButton';
import { ChevronRight } from "react-feather"
import { cplVar, isURL } from '../../utils/helpers';
import Box from '@mui/material/Box';
import PlayAudio from '../../Elements/Buttons/PlayAudio';
import PlayVideo from '../../Elements/Buttons/PlayVideo';
import jQuery from 'jquery';
import api from '../../api';

export default function Actions({ item, callback }) {
	console.log("Actions", item);

	const viewItem = (e) => {
		e.stopPropagation();
		window.location.href = item.permalink;
	};

	const playVideo = (e) => {
		e.stopPropagation();
		api.passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});

		// Slider mark may load up to a second after the frame is open
		setTimeout(
			() => {
				let element = jQuery( '.MuiSlider-root.MuiSlider-marked .MuiSlider-mark' );
				jQuery( element ).attr( 'title', 'Jump to Sermon' );
			}, 1500
		);
	};

	const playAudio = (e) => {
		e.stopPropagation();
		api.passToPersistentPlayer({
      item,
      mode: "audio",
      isPlaying: true,
      playedSeconds: 0.0,
    });
	};

	const isVideoURL = item.video.value && isURL(item.video.value);
	const isAudioURL = item.audio       && isURL(item.audio);

	return (
		<Box className="cpl-list-item--actions" ref={callback}>
			<Box className="cpl-list-item--actions--buttons cpl-touch-hide">
				{!!item.video.value && (
					// when an href is provided, the onclick is ignored and an anchor tag is rendered instead of a button
					<PlayVideo onClick={playVideo} href={isVideoURL ? false : item.permalink} />
				)}
				{!!item.audio && (
					<PlayAudio onClick={playAudio} href={isAudioURL ? false : item.permalink} />
				)}
			</Box>
			<IconButton className="cpl-list-item--to-item cpl-touch-only" onClick={viewItem}>
				<ChevronRight/>
			</IconButton>
		</Box>
	);
}
