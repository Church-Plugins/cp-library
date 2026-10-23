/**
 * Href for the share menu's audio download.
 *
 * Hosted audio goes through the site download endpoint, which reads the
 * sermon post id from originID. SoundCloud audio, and any item whose
 * originID is missing, links directly to item.audio so the request never
 * carries item_id=undefined.
 *
 * The download filename is chosen by the caller. This function only places
 * that name on the endpoint URL.
 *
 * @param {{ audio: string, originID?: number|string }} item
 * @param {string} siteUrl
 * @param {string} downloadName
 * @returns {string}
 */
export function audioDownloadUrl (item, siteUrl, downloadName) {
	if ( item.audio.includes( 'soundcloud' ) || ! item.originID ) {
		return item.audio;
	}

	return siteUrl + '?item_id=' + item.originID + '&key=audio&name=' + downloadName;
}
