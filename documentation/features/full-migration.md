# Full Migration: Export & Import

Full Migration moves an entire sermon library from one WordPress site to another using a single export file. It is available in CP Sermons 1.7.0 and later, both on the Tools page and through WP-CLI.

Full Migration is separate from the CSV import and export on the same Tools tab. CSV is still available for spreadsheet-style bulk edits. Use Full Migration when you want to copy a whole library, including series, speakers, settings, and related content, to another site.

## Where to Find It

1. In your WordPress admin, navigate to **Tools** (under **Series** by default, or **Messages** if you changed Set default menu item).
2. Stay on the **Import/Export** tab (it is selected by default).
3. Scroll down to the **Full Migration Export** and **Full Migration Import** boxes. They appear below the CSV tools.

> **Note:** The default admin menu label is **Messages**. If you've renamed your content label (e.g., to "Sermons"), your menu will reflect that name instead.

You need a user who can manage site options (typically an Administrator) to use Full Migration.

## What Gets Exported

The export is a single portable file (for example, `sermons.ndjson.gz`). It includes:

- Sermons, including variations, timestamps, transcripts, and downloads
- Series
- Speakers
- Service types
- Templates
- Taxonomy terms (topics, seasons, scripture, and other CP Sermons taxonomies)
- Plugin settings, optionally (off by default; check **Include plugin settings** on export, or pass `--include-settings` with WP-CLI)

Featured image URLs travel with the content. Whether those files (and downloadable files) are copied into the destination site's Media Library depends on the import option described below.

## Exporting from the Admin

1. On the source site, navigate to **Tools** (under **Series** by default, or **Messages** if you changed Set default menu item) > **Import/Export**.
2. Under **Full Migration Export**, check **Include plugin settings** if you also want to copy your CP Sermons settings.
3. Click **Download Export File**.
4. Save the file somewhere you can reach from the destination site.

For very large libraries (roughly 5,000 sermons or more), we recommend using WP-CLI instead. See [WP-CLI](#wp-cli) below.

## Importing from the Admin

1. On the destination site, navigate to **Tools** (under **Series** by default, or **Messages** if you changed Set default menu item) > **Import/Export**.
2. Under **Full Migration Import**, choose the `.ndjson.gz` (or `.ndjson`) file you exported from the source site.
3. Choose your options:
   - **Download media files into this site (slower; without it, media keeps pointing at the source site)** -- Copies featured images and downloadable files into this site's Media Library.
   - **Update existing content that shares a URL slug** -- Only use this when re-importing content that originally came from the source site. Matching posts are overwritten in place (title, content, dates, and meta), and this cannot be undone. Leave it unchecked to import same-slug items as new posts.
4. Click **Upload and Import**.
5. Leave the page open until the import finishes. The import runs in small batches so large libraries do not hit a PHP timeout.
6. If you leave the page and come back while an import is in progress, click **Resume Import** to continue. Click **Cancel Import** to stop; anything already imported stays on the site.

### Re-importing the Same File

Content is matched by its original ID from the source site. Importing the same file again updates the content you imported earlier instead of creating duplicates.

## WP-CLI

Use WP-CLI on the server when your library is very large, or when you want to preview an import before anything is written.

### Export

```
wp cpl export ~/sermons.ndjson.gz
wp cpl export ~/sermons.ndjson.gz --include-settings
wp cpl export ~/sermons.ndjson.gz --batch-size=200
```

| Flag | What it does |
|------|--------------|
| `--include-settings` | Also export plugin settings (general, sermon, series, speaker, service type, advanced, podcast). Off by default. |
| `--batch-size=<n>` | Posts processed per batch before caches are cleared. Default 200. |

### Import

```
wp cpl import ~/sermons.ndjson.gz --dry-run
wp cpl import ~/sermons.ndjson.gz
wp cpl import ~/sermons.ndjson.gz --download-media
wp cpl import ~/sermons.ndjson.gz --match-by-slug
wp cpl import ~/sermons.ndjson.gz --batch-size=200
```

| Flag | What it does |
|------|--------------|
| `--dry-run` | Reads the file and reports what would be created or updated without writing anything. |
| `--download-media` | Copies featured images and downloadable files into this site's Media Library. Without it, media URLs keep pointing at the source site. Files that fail to download are logged and skipped. |
| `--match-by-slug` | Also updates existing posts that share a URL slug with an imported record. This overwrites them in place and cannot be undone. Off by default. Use it only for re-imports over content that came from the source site. |
| `--batch-size=<n>` | Records processed between cache flushes. Default 200. |

Settings are imported only if they were included in the export file; there is no separate settings flag on import.

If the export included settings, your permalinks may need to be rebuilt after the import. They rebuild on the next page load, or you can run `wp rewrite flush`.

## Related

For CSV import and export, merging speakers, and the debug Log, see [Tools: Import, Export & Maintenance](https://docs.churchplugins.com/knowledge-base/tools-cp-library/).
