# Updating & Versioning

## Version requirements

CP Sermon Library **1.8.0** requires:

- WordPress **6.0** or newer
- PHP **7.4** or newer

It is tested up to WordPress **6.9**.

Those are the plugin header values **Requires at least**, **Requires PHP**, and **Tested up to**. `readme.txt` lists the same three values.

## Changelog

Release notes are published on the [CP Sermons changelog](https://churchplugins.com/wordpress-plugins/cp-sermons/changelog/). The same notes are in the plugin's `readme.txt` file, under Changelog.

Read the notes for the version you are installing. The 1.8.0 notes say updating is recommended. Draft, private, and password-protected content is no longer exposed through sermon downloads, templates, the REST API, or shortcodes. Updating to 1.8.0 also deletes CSV files that earlier sermon exports left in the uploads folders. The notes also cover fixes for YouTube transcript import, SermonAudio import of sermons that are not part of a series, and filtering on pages that use the `[cp-sermons]` shortcode. The 1.7.0 notes say a one-time cleanup runs on upgrade. It removes orphaned Speaker, Series, and Service Type associations and collapses duplicated ones. The 1.6.2 notes add **Tools → Migrate Visibility Settings** and **Tools → Reset All Sermons to Visible**. The 1.6.2 upgrade notice in `readme.txt` says to open **Tools** after updating to migrate legacy visibility settings or reset sermons that were hidden. **Tools** is under **Series**, or under Messages if Series is turned off.

On a new install the top-level admin menu is **Series**. To change it, go to Series > Settings > Advanced > **Set default menu item** (this option only appears when Series is enabled). If you choose Messages, turn Series off, or rename a label, the menu name changes to match.

## What an upgrade runs

When the version stored in WordPress does not match the installed plugin version, the plugin saves the new version and runs its upgrade routines.

The routine that ships with this release deletes CSV files that earlier sermon exports left in the uploads folders, whatever the sermon label was when they were made. It does not delete sermon posts. Earlier routines still run when the stored version is older than the version that added them. The 1.7.0 notes say a one-time cleanup runs on upgrade. It removes orphaned Speaker, Series, and Service Type associations and collapses duplicated ones. An earlier routine, for upgrades from before 1.5.0, sets **Set default menu item** to Series when that setting has no saved value.

## Theme overrides

Any template file can be overridden by copying it to `cp-library/` in your theme, keeping the same path it has under the plugin's `templates/` directory. For example, `templates/default-template.php` is overridden by `cp-library/default-template.php` in your theme.

## Grouping sermons

Group sermons with the content types the plugin registers:

- **Series** groups sermons. It is its own content type (the default label is Series).
- **Seasons** and **Topics** are taxonomies you can assign to a sermon.

## Exporting sermons

**Series > Tools > Import/Export**, or under Messages if Series is turned off, can export every sermon as CSV. With the default plural label, the button reads **Export all Messages as CSV**.

On a new install the top-level admin menu is **Series**. To change it, go to Series > Settings > Advanced > **Set default menu item** (this option only appears when Series is enabled). If you choose Messages, turn Series off, or rename a label, the menu name changes to match.
