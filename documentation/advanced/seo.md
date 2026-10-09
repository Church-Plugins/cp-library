# SEO for Sermon Archives

This guide covers SEO considerations for your sermon content in CP Sermon Library.

## Overview

CP Sermon Library follows WordPress SEO best practices. The plugin's URL structure, taxonomy system, and content organization work well with popular SEO plugins.

## SEO Plugin Compatibility

CP Sermon Library works with popular SEO plugins such as Yoast SEO, Rank Math, All in One SEO and The SEO Framework. The plugin does not add its own SEO tags. Sermons, series and speakers are public content types, so your SEO plugin adds its settings box to their edit screens and outputs the page title and canonical URL for them, using its own default templates unless you set a custom value. Yoast SEO adds no meta description unless you set one. Rank Math, All in One SEO and The SEO Framework use the excerpt as the default description on those single pages. Topic, Scripture and Season archive pages also get titles and canonical URLs from your SEO plugin, and no meta description unless you set one. Per-term SEO settings depend on the plugin: Yoast and The SEO Framework show them on Topic and Season edit screens, Rank Math only after you turn them on in its Titles & Meta settings, and All in One SEO only in its paid version. Scripture terms have no edit screen.

Rank Math only outputs its tags after you complete (or skip) its setup wizard. With no SEO plugin, WordPress prints a canonical URL only on single pages and does not print a meta description.

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

Transcripts add the full text of a message to the sermon page. To output the text on sermon pages, set **Transcript** to **Show** on the **Messages** tab of the Settings page (the default is **Hide**). **Hide** keeps the transcript off the sermon page. When **Show** is on, the full text is part of the page. If that block is 200px or taller, it is shown in a collapsed box with a **Show Transcript** button.

See [Timestamps & Transcripts](../features/timestamps-and-transcripts.md) for details.

## Filter URLs and SEO

### How Filters Affect SEO

Filtered views use URL parameters such as `/messages/?facet-cpl_topic=faith`. The plugin does not add its own canonical or robots tags to these pages. With no SEO plugin, a filtered archive has no canonical URL. SEO plugins such as Yoast SEO, Rank Math, All in One SEO and The SEO Framework point the canonical URL of a filtered view back to the unfiltered sermon archive. Filter options are form checkboxes, not links, so search engines discover sermons through the archive, pagination and individual sermon, series and topic links, which are all followed normally.

### User-Friendly Filter Display

When filters are active, visitors see:

- A clear indication of which filters are applied
- Easy options to clear individual filters or all filters

## Best Practices

1. **Write descriptive titles** — Include keywords naturally in sermon and series titles
2. **Add transcripts** — Full text gives search engines more content to read. Set **Transcript** to **Show** so it appears on the page.
3. **Use consistent taxonomy** — Apply topics, scripture, and seasons consistently
4. **Don't change URLs** — Changing permalink settings after publishing can break existing links
5. **Keep content fresh** — Regularly publish new sermons to signal active content to search engines

## Developer Documentation

For technical details on the filter system, see the [Filter System Documentation](../developers/filter-system.md).
