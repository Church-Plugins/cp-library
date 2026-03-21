# SEO for Sermon Archives

This guide covers SEO considerations for your sermon content in CP Sermon Library.

## Overview

CP Sermon Library follows WordPress SEO best practices. The plugin's URL structure, taxonomy system, and content organization work well with popular SEO plugins.

## SEO Plugin Compatibility

CP Sermon Library works with popular WordPress SEO plugins:

- **Yoast SEO** — Full support for sermon posts, series, and taxonomy pages
- **Rank Math** — Full support for sermon content
- **All in One SEO Pack** — Compatible with sermon pages
- **The SEO Framework** — Compatible with sermon pages

These plugins can manage meta titles, descriptions, and canonical URLs for all sermon content types.

## Optimizing Your Sermon Content for SEO

### Sermon Titles

Write clear, descriptive sermon titles that include relevant keywords:

- Good: "Finding Peace in Uncertain Times — Romans 8:28"
- Avoid: "Sunday Morning Message 3/9/2025"

### Sermon Descriptions

Add meaningful descriptions to your sermons:

- Include key topics and scripture references
- Write 2-3 sentences summarizing the sermon content
- Use natural language that matches how people search

### Series Organization

Well-organized series improve SEO:

- Use descriptive series titles
- Write series descriptions with relevant keywords
- Upload unique series artwork

### URL Structure

Plan your URL structure before adding significant content:

- Choose meaningful slugs in Messages → Settings → Main
- Keep URLs short and descriptive
- Avoid changing URLs after publishing (this impacts SEO)

The default archive URL is `/messages/` (based on your plural label slug).

### Transcripts for SEO

Adding transcripts to your sermons significantly improves SEO:

- Search engines can index the full text content
- Increases the keywords your sermons rank for
- Improves accessibility, which search engines reward

See [Timestamps & Transcripts](../features/timestamps-and-transcripts.md) for details.

## Filter URLs and SEO

### How Filters Affect SEO

When visitors filter your sermon archive (e.g., by topic or speaker), the plugin uses URL parameters (e.g., `/messages/?facet-topic=faith`). These filtered pages are typically handled by your SEO plugin's default behavior.

**Best practices for filtered pages:**

- Use your SEO plugin's settings to manage indexing of parameterized URLs
- Filtered views use query parameters that most SEO plugins handle automatically
- Links on filtered pages are still followed, so search engines discover your content

### User-Friendly Filter Display

When filters are active, visitors see:

- A clear indication of which filters are applied
- Easy options to clear individual filters or all filters

## Best Practices

1. **Write descriptive titles** — Include keywords naturally in sermon and series titles
2. **Add transcripts** — Full text content dramatically improves search visibility
3. **Use consistent taxonomy** — Apply topics, scripture, and seasons consistently
4. **Don't change URLs** — Changing permalink settings after publishing can break existing links
5. **Add alt text to images** — Use descriptive alt text for sermon and series artwork
6. **Keep content fresh** — Regularly publish new sermons to signal active content to search engines

## Developer Documentation

For technical details on the filter system, see the [Filter System Documentation](../developers/filter-system.md).
