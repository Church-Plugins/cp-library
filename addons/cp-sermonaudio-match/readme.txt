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

Zero matches, more than one match, or one match that already has a different `external_id` do nothing. The skip is written to the PHP error log and, when CP Library's logger is available, to that logger. The line includes the title, the calendar day, and the candidate post IDs.

On a sermon this add-on linked, later syncs do not overwrite post content or scripture. Title, date, audio, video, and the rest of the SermonAudio payload still sync.

SermonAudio stores the preach date at noon (`Y-m-d 12:00:00`). The CSV importer stores the same date at 09:00. The day is the `Y-m-d` prefix of `post_date`, so those two times match and a different date does not.

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

The linking sync skips those fields too, so the first match does not replace CSV notes or passages.

== Changelog ==

= 1.0.0 =
* Link a SermonAudio sermon to one existing sermon on the same calendar day with the same normalized title, when that sermon has no external id or the same one.
* Leave content and scripture in place on sermons this add-on linked.
