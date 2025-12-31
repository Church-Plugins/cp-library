# CP Library Documentation

This folder contains the documentation that syncs to https://docs.churchplugins.com

## Folder Structure

```
docs/
├── assets/                 # Images and media files
│   └── .gitkeep
├── config.json             # Plugin configuration for docs sync
├── README.md               # This file
├── .image-cache.json       # Tracks uploaded images (auto-generated)
└── *.md                    # Individual documentation files
```

## Markdown File Format

Each markdown file should include YAML frontmatter at the top:

```markdown
---
title: "Article Title"
slug: "article-slug"
excerpt: "Short description for the article"
status: "publish"          # publish, draft, or private
order: 1                   # Optional: controls display order
category_id: 7             # Optional: defaults to config.json category
---

# Your Content Here

Regular markdown content follows...
```

### Frontmatter Fields

- **title** (required): The article title as it appears in WordPress
- **slug** (required): URL-friendly identifier (used to match existing articles)
- **excerpt** (optional): Short description shown in article listings
- **status** (required): `publish`, `draft`, or `private`
- **order** (optional): Numeric value to control display order
- **category_id** (optional): WordPress category ID (defaults to value in config.json)

## Config.json

The `config.json` file contains metadata about this plugin's documentation:

```json
{
  "plugin_name": "CP Library",
  "plugin_slug": "cp-library",
  "category_name": "CP Sermons",
  "category_slug": "cp-library",
  "category_id": 7,
  "docs_url": "https://docs.churchplugins.com",
  "source_folder": "documentation"
}
```

## Syncing to WordPress

To sync these docs to WordPress, run the sync script:

```bash
# Sync all plugins with docs folders
./sync-docs.php

# Sync only cp-library
./sync-docs.php --plugin=cp-library

# Dry run (preview changes without syncing)
./sync-docs.php --dry-run
```

## Content Guidelines

- Use standard markdown syntax
- Internal links should reference other doc files: `[link text](other-doc.md)`
- Code blocks should specify language for syntax highlighting
- Images should be placed in the `assets/` folder

### Working with Images

Place all images in the `docs/assets/` folder and reference them relatively:

```markdown
![Alt text](assets/screenshot.png)
![Settings page](assets/settings-page.jpg)
```

During sync:
- Images are automatically uploaded to WordPress Media Library
- Markdown image references are converted to WordPress URLs
- Duplicate uploads are avoided (images are cached in `.image-cache.json`)
- Supported formats: JPG, PNG, GIF, WebP

## Workflow

1. Edit markdown files in this folder
2. Test locally if needed
3. Commit changes to git
4. Run sync script to update docs.churchplugins.com
5. Verify changes on the live docs site

## Notes

- The sync script will CREATE new articles if the slug doesn't exist
- The sync script will UPDATE existing articles if the slug matches
- Articles are matched by slug, not title
- Changing a slug will create a new article (the old one won't be deleted)
