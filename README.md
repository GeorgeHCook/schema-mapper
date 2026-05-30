# Schema Mapper

A WordPress plugin that maps post and ACF fields to Schema.org structured data and outputs JSON-LD on the front end. Designed so non-developers can configure schema per post type from the WordPress admin.

Inspired by Schema Pro, but lightweight, free, and extensible via code.

## What's in v0.1

- Settings page under **Settings > Schema Mapper**
- Per-post-type configuration: pick a schema type, map every schema field to a source (ACF field / post field / static value / permalink), optionally apply a transform
- Optional output gate: only emit schema when a chosen field matches a condition (useful for "only output for Live, not Archived")
- One schema type included out of the box: **JobPosting**
- Built-in transforms for JobPosting:
  - `employment_type_map`: Temp → TEMPORARY, Perm → FULL_TIME, etc.
  - `gbp_salary_range`: parses "£20,000-£30,000", "£60,000+" into MonetaryAmount
  - `work_setting`: Home/Remote → TELECOMMUTE
  - `iso8601`: any date string → ISO 8601
- Anonymous-employer handling: when the employer is confidential, the plugin sets the recruiter as `hiringOrganization` and puts the disambiguating description (e.g. "Independent school") on the hiring org, per Google for Jobs policy
- Output appears alongside any existing Yoast schema, not instead of it
- Works with or without ACF (degrades gracefully if ACF is not active)

## Install

Drop the folder into `wp-content/plugins/schema-mapper` and activate. Or install via Git Updater (the plugin header declares its GitHub URI).

## Configure

1. Go to **Settings > Schema Mapper**
2. Find your post type, enable schema output, pick **Job Posting** (or another type as more are added)
3. Save. The page reloads and the field mapping table appears.
4. Map each schema field to either an ACF field, a post field (post_title, post_content, post_date, etc.), or a static value
5. For JobPosting, set the recruiter name as a Static value
6. Optionally set an output condition (e.g. only output when `live_or_archive == "Live"`)
7. Save. The plugin starts outputting `<script type="application/ld+json">` on single pages of that post type.
8. Validate with https://search.google.com/test/rich-results

## Roadmap

- More schema types: `FAQPage`, `EmploymentAgency` organisation upgrade, `Article`, `Person`, `Service`, `ItemList`
- "Compose" source type (concatenate multiple fields, e.g. content + duties)
- Per-post overrides (a metabox on individual posts to override the mapping for that post)
- Integration with Yoast's `@graph` (merge instead of emitting a second script)
- WP-CLI commands to inspect and validate

## License

GPL-2.0-or-later. See LICENSE.
