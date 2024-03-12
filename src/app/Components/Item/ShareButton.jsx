import { useState, useRef } from 'react';
import { cplLog } from "../../utils/helpers";
import Box from '@mui/material/Box';
import MenuItem from '@mui/material/MenuItem';
import Menu from "@mui/material/Menu";
import Rectangular from "../../Elements/Buttons/Rectangular";
import { Share2 } from "react-feather";
import Facebook from '@mui/icons-material/Facebook';
import Twitter from '@mui/icons-material/Twitter';
import Download from '@mui/icons-material/Download';
import LinkIcon from '@mui/icons-material/Link';

export default function ShareButton({ item, variant = "outlined" }) {
  const [anchorEl, setAnchorEl] = useState(null);
  const open = Boolean(anchorEl);
	const copyLinkRef = useRef(null);

  const handleClick = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClose = () => {
    setAnchorEl(null);
  };

  const handleFBShare = () => {
		cplLog(item.id, 'share_facebook');
		window.open('http://www.facebook.com/sharer.php?u='+encodeURIComponent( item.permalink )+'&t='+encodeURIComponent( item.title ),'sharer','toolbar=0,status=0,width=626,height=436');
    setAnchorEl(null);
	};

	const handleTwitterShare = () => {
		cplLog(item.id, 'share_twitter');

		window.open( "http://twitter.com/intent/tweet?text=" + encodeURIComponent( item.title + ' ' + item.permalink ),'sharer','toolbar=0,status=0,width=626,height=436');
    setAnchorEl(null);
	};

	const handleFileDownload = () => {
		cplLog(item.id, 'download');

		const link = document.createElement('a');

		if ( item.audio.search('soundcloud') ) {
	    link.href = item.audio;
		} else {
	    link.href = cplVar( 'url', 'site' ) + '?item_id=' + item.originID + '&key=audio&name=' + item.title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.mp3';
		}

		link.setAttribute(
			'target',
			'_blank',
		);

    // Append to html link element page
    document.body.appendChild(link);

    // Start download
    link.click();

    // Clean up and remove the link
    link.parentNode.removeChild(link);
    setAnchorEl(null);
	};

	const handleCopyLink = (e) => {
		copyLinkRef.current.select();
		document.execCommand('copy');
		e.target.focus();
    setAnchorEl(null);
	};

  return (
    <Box
      className="itemDetail__share"
    >
      <Rectangular
        aria-controls="itemDetail__share"
        aria-haspopup="true"
        aria-expanded={open ? 'true' : undefined}
        onClick={handleClick}
        variant={variant}>
        <Share2 />
      </Rectangular>
      <Menu
        id="itemDetail__share__menu"
        className="itemDetail__share__menu"
        aria-labelledby="demo-positioned-button"
        anchorEl={anchorEl}
        open={open}
        onClose={handleClose}
        anchorOrigin={{
          vertical  : 'bottom',
          horizontal: 'right',
        }}
        transformOrigin={{
          vertical  : 'top',
          horizontal: 'right',
        }}
      >
        <MenuItem onClick={handleFBShare}><Facebook /> Share on Facebook</MenuItem>
        <MenuItem onClick={handleTwitterShare}><Twitter /> Share on Twitter</MenuItem>
        {item.audio && (
          <MenuItem onClick={handleFileDownload}><Download /> Download Audio</MenuItem>
        )}
        <MenuItem onClick={handleCopyLink}><LinkIcon /> Copy Link <textarea ref={copyLinkRef} value={item.permalink} className="cpl-sr-only" /></MenuItem>
      </Menu>
    </Box>
  )
}