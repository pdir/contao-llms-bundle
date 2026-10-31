# Changelog

[//]: <> (
Types of changes
    Added for new features.
    Changed for changes in existing functionality.
    Deprecated for soon-to-be removed features.
    Removed for now removed features.
    Fixed for any bug fixes.
    Security in case of vulnerabilities.
)

## [1.0.0](https://github.com/pdir/contao-llms-bundle/tree/1.0.0) – 2026-10-31

Initial release.

**Files**

- [Added] Route `/llms.txt` with title, description, custom instructions and a list of all public pages and article teasers
- [Added] Route `/llms-full.txt` with the content of every page as Markdown, generated from the main section of the rendered page
- [Added] Line with status, language and support address at the top of the `llms-full.txt`
- [Added] Only two heading levels in the `llms-full.txt`: `#` for pages, legal, contact etc., `##` for the sections of the content; deeper headings become bold text, kickers are joined to the heading with a colon
- [Added] Contact details as a list
- [Added] Description of the root page: first paragraph as a quote, further paragraphs as regular Markdown (with a hint in the back end); line breaks are also kept in page descriptions
- [Added] Description of the first page as a fallback if the root page has no description
- [Added] Fixed texts in German or English, matching the language of the root page
- [Added] Support for multi-language websites (all enabled root pages of a domain)

**Structure**

- [Added] Sections via the inheritable page field "LLMs section", including the `Optional` section; sections already in use are suggested during input
- [Added] Link from the `llms.txt` to the `llms-full.txt` and optionally to the `sitemap.xml` in the `Optional` section
- [Added] "Legal" section before the contact details with the pages selected in the root page (e.g. imprint, privacy policy, accessibility statement); omitted if no pages are selected
- [Added] Contact, usage guidelines and attribution; usage guidelines and attribution can be disabled

**Content**

- [Added] Exclusion of pages with `noindex` and of pages without content of their own (redirects, empty reader pages)
- [Added] News and events from the Contao news and calendar bundles in both files, in the LLMs section of their reader page; both bundles are optional and can be excluded in the root page
- [Added] Date and author of news in the `llms-full.txt`
- [Added] Only upcoming and running events, sorted chronologically in ascending order; recurring events with the date of their next occurrence
- [Added] Clean Markdown: decoded HTML entities, no superfluous spaces and blank lines (also in stored and manual content), no link titles and no links without text
- [Added] Tables as Markdown tables
- [Added] Absolute URLs for links
- [Added] No images from the page content; instead the field "LLMs images" in the page settings for specifically selected images, with the alternative text from the file manager
- [Added] Replacement of insert tags in article teasers

**Back end and operation**

- [Added] Settings for root pages and regular pages in the page tree, including manual override of the page description
- [Added] Storage of the generated content; regenerated on changes, after 24 hours at the latest
- [Added] `Last-Modified` header for both files with the time of the last content change; `304 Not Modified` response to requests with `If-Modified-Since`
- [Added] HTTP caching of both files with automatic invalidation via the cache tag `pdir.llms` on changes to pages, articles, content elements, news, events, news archives and calendars
- [Added] Generation of the content in advance with the console command `pdir:llms:generate`, e.g. in a cronjob
- [Added] Purging of the LLMs data with the console command `pdir:llms:purge` and in the system maintenance; manual page descriptions are kept
- [Added] Hook `getLlmsPages` for adding or removing entries
- [Added] Support for Contao 5.3 and 6.0
