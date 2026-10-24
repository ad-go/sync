# Third-party assets vendored under this directory

Fetched once from jsdelivr (an npm CDN mirror) and committed as static files — SyncUI serves them itself
(`SyncUiAdmin::asset()`), no runtime CDN calls, no build step. Update by re-fetching a newer pinned version
the same way and editing the version folder name + the `<script>`/`<link>` tags in `Views/syncui/_layout.php`
and `Views/syncui/showcase.php`.

| Library | Version | License | Used for |
|---|---|---|---|
| [jQuery](https://jquery.com) | 3.7.1 | MIT | required by DataTables and Select2 |
| [DataTables](https://datatables.net) | 3.1.4 | MIT | instant client-side search over the current page of browsed rows |
| [Select2](https://select2.org) | 4.1.0 | MIT | searchable "jump to table" dropdown in the top bar |
| [Toastify-js](https://github.com/apvarun/toastify-js) | 1.12.0 | MIT | toast notifications for flash messages |
| [Lucide](https://lucide.dev) (`icons/*.svg`) | 1.61.0 (lucide-static) | ISC | inline SVG icons (`Libraries\Icon`) |
| [Quill](https://quilljs.com) | 2.0.3 | BSD-3-Clause | showcase: WYSIWYG editor |
| [Signature Pad](https://github.com/szimek/signature_pad) | 5.1.4 | MIT | showcase: mouse/touch signature |
| [Cropper.js](https://fengyuanchen.github.io/cropperjs/) (v2, web components) | 2.3.1 | MIT | showcase: image editor (crop/rotate/zoom) |
| [html5-qrcode](https://github.com/mebjas/html5-qrcode) | 2.3.8 | Apache-2.0 | showcase: barcode/QR scan via camera |
| [PDF.js](https://mozilla.github.io/pdf.js/) | 6.4.299 | Apache-2.0 | showcase: PDF preview |

Speech synthesis in the showcase uses the browser's native Web Speech API (`window.speechSynthesis`) — nothing
to vendor.
