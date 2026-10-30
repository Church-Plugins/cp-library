=== CP Sermons - SermonAudio Match Existing ===
Contributors: churchplugins
Tags: sermons, sermonaudio
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Links a SermonAudio sync to a sermon that already exists, instead of creating a duplicate.

== Description ==

CP Sermon Library's SermonAudio sync reuses a sermon only when a post already has that sermon's `external_id` meta. A sermon that arrived another way, such as a CSV import, has no `external_id`, so the next sync inserts a second sermon.

This add-on runs before that insert. When no post has the SermonAudio id, it looks for `cpl_item` posts on the same calendar day whose normalized title equals the normalized SermonAudio title (`fullTitle`, or `displayTitle` when the full title is blank). Exactly one such post, and only when that post has no `external_id` or the same one, is linked: the formatted item's ID is set, `external_id` meta is stored, and the add-on meta `_cpl_sa_match_linked` is set to `1`.

Zero matches, more than one match, or one match that already has a different `external_id` do nothing. A skip that names at least one candidate (more than one title match, or a different SermonAudio id) is written to the PHP error log and, when CP Library's logger is available, to that logger. The line includes the title, the calendar day, and the candidate post IDs. A skip with no candidates — every new sermon — is written only when `WP_DEBUG` is on. The filter `cpl_sa_match_log_empty_skips` can force that debug log on or off.

The filter sets the sermon's ID so the sync updates that post. It does not write `external_id` or `_cpl_sa_match_linked` yet. Those are stored on `save_post`, after the sync insert has been saved. A failed insert does not mark the sermon as linked.

On a sermon this add-on linked, a SermonAudio save does not overwrite post content or scripture. An edit in wp-admin, the REST API, or bulk edit still saves. Title, audio, video, and the rest of the SermonAudio payload still sync. The publish time moves from the CSV importer's 09:00 to SermonAudio's noon on that same calendar day.

SermonAudio stores the preach date at noon (`Y-m-d 12:00:00`). The CSV importer stores the same date at 09:00. The day is the `Y-m-d` prefix of `post_date`, so those two times match and a different date does not. Matching assumes `preachDate` is a plain date. A value that already includes a time can land on another calendar day once noon is added.

The title comparison, in order: decode HTML entities, turn curly quotes and dashes into plain ones, strip tags, lowercase, strip punctuation, collapse whitespace, and drop a trailing ellipsis.

This plugin is not part of the CP Sermon Library download. The library zip is built from the `files` allowlist in `package.json`, and `addons/` is not on that list.

== Installation ==

1. Copy `addons/cp-sermonaudio-match` to `wp-content/plugins/cp-sermonaudio-match`.
2. Activate **CP Sermons - SermonAudio Match Existing**. CP Sermon Library must be active.
3. Run the SermonAudio sync as usual (Check Now, the scheduled sync, or the SermonAudio WP-CLI command).

No settings screen. Deactivate the add-on to stop linking. Sermons already linked keep `_cpl_sa_match_linked`; while this add-on is active, sync will not replace their content or scripture.

== Skipped fields ==

For a sermon with `_cpl_sa_match_linked` = `1`:

* `post_content` is put back from the existing post in `wp_insert_post_data`, after WordPress sanitizes the SermonAudio body.
* Scripture is removed from `cpl_data` before `load_item()`. `SermonAudio::process_cpl_data()` writes scripture only when that key is set. `update_scripture()` has no filter, so this removal is what blocks the overwrite.

The linking sync skips those fields too, so the first match does not replace CSV notes or passages. The content shield runs only for the sync save. Scripture is removed from that save's `cpl_data` only. Neither one blocks an edit made outside the sync.

== Known limits ==

* A skipped sermon is still inserted, which is what the sync did before this add-on. That includes no title match, more than one title match, and a match that already has a different SermonAudio id.
* A linked sermon does not pick up scripture or post-content corrections from later SermonAudio syncs. Title, time, audio, and video still update.
* The calendar day is taken from `preachDate` as a plain date, the shape SermonAudio sends. A datetime in that field is not given special handling.
* Linking moves `post_date` from 09:00 to noon on the same day.

== Changelog ==

= 1.0.0 =
* Link a SermonAudio sermon to one existing sermon on the same calendar day with the same normalized title, when that sermon has no external id or the same one.
* Leave content and scripture in place on SermonAudio saves of sermons this add-on linked. Editor saves still store.
* Log skips that name a candidate. Log a skip with no candidates only when WP_DEBUG is on.
