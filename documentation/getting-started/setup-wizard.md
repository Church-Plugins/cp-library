# Migration Wizard

CP Sermon Library includes a migration wizard to help you transfer sermon data from other WordPress sermon plugins.

## When the Migration Wizard Appears

The migration wizard launches automatically when you first activate CP Sermon Library **if** the plugin detects existing sermon data from a supported source plugin. Detection works by checking the database for posts belonging to each supported plugin's post type. If no legacy data is detected, the wizard will not appear.

After activation, the wizard also remains accessible from the **Messages > Migrate** submenu in the WordPress admin, so you can return to it at any time.

### Supported Source Plugins

The wizard can migrate data from:

- **Sermon Manager** (`wpfc_sermon` posts) — Imports sermons, series, speakers, topics, service types, scripture references, audio/video URLs, thumbnails, sermon notes, and bulletins. Also migrates series and speaker images when the Sermon Manager image plugin data is available.
- **Church Content** (`ctc_sermon` posts) — Imports sermons, series, speakers, topics, scripture (book references), audio/video URLs, PDF attachments, and thumbnails.
- **Series Engine** (`enmse_message` posts) — Imports sermons, series, speakers, topics, scripture, audio/video URLs, embed codes, file attachments, and thumbnails. Reads data from Series Engine's custom database tables.

If more than one supported plugin has data in the database, the wizard will list all available migrations and let you choose which to run.

## Using the Migration Wizard

1. After activating CP Sermon Library, the migration wizard will appear if legacy data is found.
2. The wizard displays each detected source plugin along with the number of items available to migrate.
3. Select the source plugin to migrate from and start the migration.
4. The migration runs as a **background process** -- you can continue using WordPress while it works. The wizard page shows a live progress bar with the percentage of items completed.
5. You can **pause and resume** the migration at any time if needed.
6. Duplicate items are automatically prevented. If a sermon has already been migrated (tracked via a `migration_id` on each imported post), it will be skipped rather than duplicated. This means you can safely re-run a migration without creating duplicate content.

The source plugin does **not** need to be active for migration to work. The wizard reads directly from the database, so it can detect and migrate data even after the original plugin has been deactivated.

## After Migration

Once migration is complete:

1. Review your imported sermons under Messages → All Messages
2. Check that series, speakers, and media transferred correctly
3. Navigate to Messages → Settings to configure your preferences
4. Go to Settings → Permalinks and click "Save Changes" to update URL structure

## Manual Setup (No Migration)

If you're starting fresh without existing sermon data, proceed directly to configuration:

1. **Configure Labels** — Navigate to Messages → Settings to customize content labels (rename "Messages" to "Sermons," etc.)
2. **Set Up Post Types** — Enable Series, Speakers, and optionally Service Types in the Advanced settings tab
3. **Configure Display** — Set your preferred template and layout options in the Messages settings tab
4. **Set Up Podcast** — Configure podcast feed settings in the Podcast tab (if needed)
5. **Add Content** — Start adding speakers, series, and sermons

See the [Installation Guide](installation.md) for detailed first-steps instructions.

## Configuring Essential Settings

After migration or fresh setup, review these key settings areas:

### Messages → Settings → Main Tab
- Primary color for the media player
- Site logo and default thumbnail
- Button labels (Play Video / Play Audio)

### Messages → Settings → Messages Tab
- Content labels (singular/plural names and URL slug for sermons)
- Single page template (default or vertical layout)
- Image aspect ratio for sermon thumbnails
- Info items and meta items to display
- Transcript visibility

### Messages → Settings → Series Tab
- Content labels (singular/plural names and URL slug for series)
- Sort order and items per page

### Messages → Settings → Speakers Tab
- Content labels (singular/plural names for speakers)
- Enable/disable speaker permalink pages

### Messages → Settings → Advanced Tab
- Enable/disable Series, Speakers, and Service Types
- Filter display options (sorting, count thresholds)
- Debug mode for troubleshooting

### Messages → Settings → Podcast Tab
- Podcast title, description, author
- Cover artwork
- iTunes categories
