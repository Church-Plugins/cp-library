# Updating & Versioning

## Version requirements

CP Sermon Library **1.7.0** requires:

- WordPress **6.0** or newer
- PHP **7.4** or newer

It is tested up to WordPress **6.9**.

Those are the plugin header values **Requires at least**, **Requires PHP**, and **Tested up to**. `readme.txt` lists the same three values.

## Changelog

Release notes are published on the [CP Sermons changelog](https://churchplugins.com/wordpress-plugins/cp-sermons/changelog/). The same notes are in the plugin's `readme.txt` file, under Changelog.

Read the notes for the version you are installing. The 1.7.0 notes say a one-time cleanup runs on upgrade. It removes orphaned Speaker, Series, and Service Type associations and collapses duplicated ones. The 1.6.2 notes add **Tools → Migrate Visibility Settings** and **Tools → Reset All Sermons to Visible**. The 1.6.2 upgrade notice in `readme.txt` says to open **Messages → Tools** after updating to migrate legacy visibility settings or reset sermons that were hidden.

## What an upgrade runs

When the version stored in WordPress does not match the installed plugin version, the plugin saves the new version and runs its upgrade routines.

The routine that ships with this release deletes orphaned Speaker, Series, and Service Type association rows and collapses duplicate Speaker and Series rows, keeping the oldest row. It does not delete sermon posts. An earlier routine, for upgrades from before 1.5.0, sets **Set default menu item** to Series when that setting has no saved value.

## Theme overrides

The default content template can be overridden by placing `cp-library/default-template.php` in your theme.

## Grouping sermons

Group sermons with the content types the plugin registers:

- **Series** groups sermons. It is its own content type (the default label is Series).
- **Seasons** and **Topics** are taxonomies you can assign to a sermon.

## Exporting sermons

**Messages → Tools**, on the Import/Export tab, can export every sermon as CSV. With the default plural label, the button reads **Export all Messages as CSV**.
