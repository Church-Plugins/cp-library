# Tools: Import, Export & Maintenance

The Tools page provides utilities for importing and exporting sermon data in bulk, merging duplicate speakers, and viewing debug logs. Access it by navigating to **Messages > Tools** in your WordPress admin.

> **Note:** The default admin menu label is **Messages**. If you've renamed your content label (e.g., to "Sermons"), your menu will reflect that name instead.

The Tools page has two tabs: **Import/Export** and **Log**.

## CSV Import

The CSV importer lets you create sermons in bulk from a spreadsheet file.

### Preparing Your CSV File

A sample CSV file is available for download directly from the import page. Use it as a starting point to ensure your columns are formatted correctly.

Key formatting rules:

- **Multiple values** -- Separate multiple speakers, topics, seasons, or scripture references with commas (e.g., `Pastor Bob, Pastor John`).
- **Downloads** -- Use the format `Name|URL` for named downloads. Separate multiple downloads with commas (e.g., `Notes|https://example.com/notes.pdf,https://example.com/bulletin.pdf`). You can also provide a URL without a name.
- **Date** -- Accepts most standard date formats (e.g., `9/6/2009`, `2009-09-06`) or a Unix timestamp.
- **Series** -- If a series name does not already exist, it will be created automatically. If no series is specified, sermons are assigned to a default "No Series" series.
- **Speakers** -- If a speaker name does not already exist, it will be created automatically.
- **Service Types** -- If a service type does not already exist, it will be created automatically.
- **Topics, Seasons, Scripture** -- Terms that do not already exist will be created automatically.

### Running an Import

1. Navigate to **Messages > Tools**. The Import/Export tab is selected by default.
2. Click **Choose File** and select your CSV file.
3. Click **Import CSV** to upload the file.
4. A column mapping table appears. For each sermon field, select the corresponding CSV column from the dropdown. A data preview shows the first row of data for each mapped column.
5. Any field you do not need can be left set to "Ignore this field."
6. Configure the additional options below the mapping table:
   - **Attempt to import mp3 files to the Media Library** -- When checked, audio file URLs are downloaded and added to your WordPress Media Library. Checked by default.
   - **Attempt to import downloadable files to the Media Library** -- When checked, downloadable file URLs are downloaded and added to your WordPress Media Library. Checked by default.
7. Click **Process Import** to begin.

### Available Import Fields

| Field | Description |
|-------|-------------|
| Title | Sermon title (required if no Series is provided) |
| Description | Sermon content/description |
| Transcript | Sermon transcript text |
| Date | Sermon date |
| Series | Series name (creates the series if it does not exist) |
| Location | Location name (only available when CP Locations is active) |
| Speaker | Speaker name(s), comma-separated (only available when Speakers are enabled) |
| Service Type | Service type name(s), comma-separated (only available when Service Types are enabled and not used as the variation source) |
| Topics | Topic name(s), comma-separated |
| Season | Season name(s), comma-separated |
| Scripture | Scripture reference(s), comma-separated |
| Thumbnail | URL to a thumbnail image |
| Video | Video URL or embed code |
| Audio | Audio file URL |
| Downloads | Downloadable file(s) in `Name|URL` format, comma-separated |
| Variation | Variation identifier (used to group multiple rows as variations of the same sermon) |

### Import Progress and Cancellation

The import runs as a background process. A progress bar displays the percentage complete and the number of sermons processed.

- You can navigate away from the page while the import runs. When you return to the Tools page, the progress bar will show the current status.
- To stop an import before it finishes, click the **Stop import** button.

### Duplicate Handling

The importer checks for existing sermons before creating new ones. A sermon is considered a duplicate if it matches on title and date (and location, if applicable). When a duplicate is found, the existing sermon is updated rather than creating a new entry.

## CSV Export

The export feature downloads all of your sermon data as a single CSV file.

1. Navigate to **Messages > Tools**.
2. Under the **Export data** section, click **Export all Messages as CSV**.
3. A CSV file is generated and downloaded to your computer.

The export includes all sermons regardless of their status (published, draft, private, and scheduled). The exported CSV contains the following columns: Title, Description, Transcript, Series, Date, Passage, Location, Service Type, Speaker, Topics, Season, Scripture, Thumbnail, Video, Audio, and Downloads.

## Merge Duplicate Speakers

After importing sermons, you may end up with duplicate speaker entries that have the same name. The merge tool consolidates these into a single speaker.

When you merge duplicate speakers, the tool:

- Finds all speakers that share the same name.
- Keeps the original speaker entry (the one without a numeric suffix in its URL slug).
- Transfers all sermon assignments from the duplicates to the kept speaker.
- Copies over any taxonomy terms from duplicates to the kept speaker.
- Copies the thumbnail from a duplicate if the kept speaker does not have one.
- Copies the biographical content from a duplicate if the kept speaker's content is empty.
- Deletes the duplicate speaker entries.

To run the merge:

1. Navigate to **Messages > Tools**.
2. Under **Merge Duplicate Speakers**, click **Merge Speakers**.
3. A confirmation message appears when the merge is complete.

## Log Tab

The Log tab displays debug log output from the plugin. This is useful for troubleshooting import issues or other plugin behavior.

- Debug logging must be enabled in **Settings > Advanced** for log entries to appear.
- Click **Clear Log** to remove all existing log entries.
