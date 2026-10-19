# Migration Wizard

CP Sermon Library includes a migration wizard to help you transfer sermon data from other WordPress sermon plugins.

## When the Migration Wizard Appears

The migration wizard launches automatically when you first activate CP Sermon Library **if** the plugin detects existing sermon data from a supported source plugin. Detection works by checking the database for posts belonging to each supported plugin's post type. If no legacy data is detected, the wizard will not appear.

After activation, the wizard also remains accessible from the **Series > Migrate** submenu in the WordPress admin, so you can return to it at any time. The **Migrate** menu is registered only when legacy data is detected on activation.

### Supported Source Plugins

The wizard can migrate data from:

- **Sermon Manager** (`wpfc_sermon` posts) — Imports sermons, series, speakers, topics, service types, scripture references, audio/video URLs, thumbnails, sermon notes, and bulletins. Also migrates series and speaker images when the Sermon Manager image plugin data is available.
- **Church Content** (`ctc_sermon` posts) — Imports sermons, series, speakers, topics, scripture (book references), audio/video URLs, PDF attachments, and thumbnails.
- **Series Engine** (`enmse_message` posts) — Imports sermons, series, speakers, topics, scripture, audio/video URLs, embed codes, file attachments, and thumbnails. Reads data from Series Engine's custom database tables.

If more than one supported plugin has data in the database, the wizard will list all available migrations and let you choose which to run.

## Using the Migration Wizard

1. After activating CP Sermon Library, you are sent to the migration screen if legacy data is found. Open it later from **Series > Migrate**.
2. Click **Launch Wizard**.
3. Each detected plugin is listed with a count such as **3 Sermons found**. Click **Get Started** on the one you want.
4. Click the button labeled **Copy {count} sermon from {plugin name}** or **Copy {count} sermons from {plugin name}**.
5. The migration runs as a **background process**. The wizard shows a progress percentage, then **Migration complete!** Click **Close**.
6. If a sermon was already migrated, it is updated in place. Re-running does not create a second copy. The match is stored as `migration_id` on the imported sermon.

The source plugin does **not** need to be active for migration to work. The wizard reads directly from the database, so it can detect and migrate data even after the original plugin has been deactivated.

## After Migration

Once migration is complete:

1. Review your imported sermons under Series → Messages
2. Check that series, speakers, and media transferred correctly
3. Navigate to Series → Settings to configure your preferences
4. Go to Settings → Permalinks and click "Save Changes" to update URL structure

## Manual Setup (No Migration)

If you're starting fresh without existing sermon data, proceed directly to configuration:

1. **Configure Labels** — Navigate to Series → Settings to customize content labels (rename "Messages" to "Sermons," etc.)
2. **Set Up Post Types** — Enable Series, Speakers, and optionally Service Types in the Advanced settings tab
3. **Configure Display** — On the Messages tab, set **Single Page Template**, **Image Aspect Ratio**, **Info Items**, and **Meta Items**
4. **Set Up Podcast** — On the Advanced tab, set **Enable Podcast Feed** to **Enable**, save, then fill in the Podcast tab
5. **Add Content** — Start adding speakers, series, and sermons

See the [Installation Guide](installation.md) for detailed first-steps instructions.

## Configuring Essential Settings

After migration or fresh setup, review these key settings areas:

### Series → Settings → Main Tab
- Primary color for the media player
- Site logo and default thumbnail
- **Play Video Button** and **Play Audio Button** (under **Labels**)

### Series → Settings → Messages Tab
- Content labels (singular/plural names and URL slug for sermons)
- Single page template (default or vertical layout)
- Image aspect ratio for sermon thumbnails
- Info items and meta items to display
- Transcript visibility

### Series → Settings → Series Tab
- Content labels (singular/plural names and URL slug for series)
- Sort order and items per page

### Series → Settings → Speakers Tab
- Content labels (singular/plural names for speakers)
- **Enable Speaker permalinks**

### Series → Settings → Advanced Tab
- Enable/disable Series, Speakers, and Service Types
- Filter display options (sorting, count thresholds)
- Debug mode for troubleshooting

### Series → Settings → Podcast Tab
- Podcast title, description, and **Provider**
- Cover artwork (**Image**)
- **Category**
