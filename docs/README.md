# CP Library — Docs Sync

This folder contains WordPress-specific metadata for syncing documentation to https://docs.churchplugins.com. The actual content lives in `../documentation/`.

## How It Works

Each `.md` file here is a thin wrapper with YAML frontmatter that references a source file:

```yaml
---
title: "Article Title"
slug: "article-slug"
source: "getting-started/introduction.md"
excerpt: "Short description"
status: "publish"
order: 1
category_id: 10
---
```

The sync script reads frontmatter from `docs/`, loads content from `documentation/` via the `source` field, converts to Gutenberg blocks, and publishes via the WordPress REST API.

## Frontmatter Fields

- **title** (required): Article title in WordPress
- **slug** (required): URL-friendly identifier (matches existing articles)
- **source** (required): Path to the markdown file in `documentation/`
- **excerpt** (optional): Short description for listings
- **status** (required): `publish`, `draft`, or `private`
- **order** (optional): Display order
- **category_id** (optional): KB subcategory ID (not parent)

## KB Categories

| ID | Name | Parent |
|----|------|--------|
| 7 | CP Sermons | — |
| 10 | Getting Started | CP Sermons |
| 13 | Options and Features | CP Sermons |
| 12 | Advanced | CP Sermons |
| 20 | Developers | CP Sermons |
| 11 | Support | CP Sermons |

## Syncing

```bash
php sync-docs.php --plugin=cp-library --dry-run --verbose
php sync-docs.php --plugin=cp-library --verbose
```
