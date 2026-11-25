# Word content in event announcements and information

Event information (create, edit and settings) and event announcements use the
shared `EventRichTextEditor` in `public/js/event-rich-text.js`. It retains HTML
document structure rather than converting pasted content into Quill Delta.
The public content styles are in `public/css/event-rich-text.css`.

## Supported content and limits

- Paste common text formatting, headings, lists and tables, including merged
  cells. Safe inline font, colour, alignment, spacing and border styles survive
  the event-content sanitizer.
- PNG, JPEG and GIF pictures can be pasted when the browser supplies usable
  image data. Use **Insert image** when Word supplies only an inaccessible local
  picture reference. The editor reports missing images rather than promising
  that every Word picture was imported.
- Each embedded image is limited to 1 MB; the complete HTML content is limited
  to 4 MB. Images are stored inside the content, without a separate upload
  endpoint. Large photographs should be resized before insertion.
- Browser rendering is not Word pagination. Page breaks, floating shapes,
  columns, unsupported fonts and exact page layout can change. Check the saved
  event page, particularly on a phone. Email clients may display embedded
  images and styling differently; web-page fidelity does not establish email
  fidelity.

## Persistence and safety

`Event` and `Announcement` validate content size on writes and sanitize content
on reads and writes through the event-specific `RichTextSanitizer` profile.
Other consumers retain the original, narrower sanitizer profile. Clipboard
markup is filtered in the browser before insertion; server-side sanitization
remains authoritative. Scripts, event handlers and unsafe resource schemes
must not survive sanitization.

Create/edit views also sanitize validation-returned `old('information')` before
rendering it as HTML; client cleanup cannot protect markup already rendered by
the server.

`database/migrations/2026_11_24_120000_expand_event_rich_content.php` widens
`events.information` and `announcements.message` to MEDIUMTEXT for embedded
images. Its exact path is listed in `deploy.config`. The rollback deliberately
retains the wider columns to avoid truncating saved content. Deploying this
feature requires the reviewed migration and updated assets; allowlisting does
not authorize production execution.

Formatting or images already removed by an earlier save cannot be recovered
from the saved HTML; paste the original document again after the update.
