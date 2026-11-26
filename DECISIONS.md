# Design decisions

This document records why SiteForge AI is built the way it is, what was deliberately left out, and what remains to be done.

## Why Laravel

The brief asks for one backend that owns everything: authentication, authorization, file handling, background jobs, persistence and AI integration. Laravel provides all of these as first-class parts that work together: form requests, policies, scoped route bindings, the filesystem abstraction, the database queue and the HTTP client with `Http::fake()` for testing. Using them avoided a second service and kept the security-critical code (authorization, validation, outbound HTTP) in one place that the test suite exercises directly.

## Why React + Inertia

The Studio is a genuinely interactive application. It has live preview, optimistic edits, autosave, reordering and side-by-side AI proposals, and React is a good fit for that. Inertia keeps routing, authentication, validation errors and flash messages on the Laravel side, so there is no separate API, token handling or duplicated route definitions. The Studio's frequent operations (autosave, reorder, regenerate, publish) use small JSON endpoints called with axios, because a full Inertia page visit on every keystroke would be wasteful. These endpoints are still ordinary Laravel controllers behind the same middleware, CSRF protection and policies.

TypeScript is used throughout the frontend. The website content types (`resources/js/types`) mirror the PHP schema (`WebsiteSchema`).

## Why MySQL

The data is relational: users → businesses → sources → facts, and websites → sections. MySQL gives foreign keys with cascading deletes, transactions for atomic section replacement and slug locking, and JSON columns for section content and themes. The migrations were run, and the full test suite was executed, against MariaDB 10.11 as well as SQLite.

## Why Gemini

The brief specifies it. Gemini also supports `responseMimeType: application/json` with a `responseSchema`, which constrains output to the expected shape before our own validation even runs. The client talks to the REST API directly through Laravel's HTTP client rather than an SDK, which keeps dependencies minimal and makes every request easy to fake in tests. The key travels in the `x-goog-api-key` header, never in the URL, so it cannot leak into logs.

## Groq as an alternative provider

All AI calls go through an `AiClient` interface (`GeminiClient`, `GroqClient`) that shares the retry loop and defensive JSON decoding. Groq's OpenAI-compatible JSON mode takes no schema, so the schema is included in the system message. Safety does not depend on the provider: grounding, `ClaimGuard`, `SiteAssembler` and `WebsiteSchema` validate whatever comes back.

## How source processing works

Each input becomes a `sources` row and is processed by `ProcessSourceJob`:

| Type | Processing |
|---|---|
| Text (owner) | Stored as-is and marked **trusted**: facts extracted from it start confirmed. |
| PDF | `smalot/pdfparser`, with header validation, a 60-page / 60k-character cap and page-by-page error tolerance. Corrupt, empty and image-only (scanned) PDFs fail with a clear message. |
| Website | `UrlGuard` performs SSRF checks (http/https only, standard ports, no credentials, every resolved IP must be public, DNS pinned to the checked IP to stop rebinding, every redirect re-checked). `UrlFetcher` enforces size and time limits. `HtmlContentExtractor` reads JSON-LD business data, `tel:` and `mailto:` links, metadata and visible text (scripts, styles and forms stripped). Up to three same-site about/contact/services pages are also read. |
| Social / Google Business | Public metadata only. HTTP 401/403/429/999 responses, redirects to sign-in pages, and pages that are mostly a login prompt are marked **Blocked**, with a message inviting the owner to add the details manually. The app never logs in or works around access controls. |
| Image | Stored as media for the gallery (re-encoded with GD). No text extraction. |
| Search result | See research below. |

Each source ends in exactly one honest state: Completed, Failed (with the reason) or Blocked. Nothing is marked successful when it was not.

## How research and identity resolution work

`ResearchService` queries the configured provider with the business name, city and (if known) category. `IdentityResolver` scores each result:

- Name match: full +0.45, partial +0.2
- City match: +0.3
- Same domain or handle as a link the owner provided: +0.5
- A known phone number appears: +0.3

Scores of 0.75 or more are **matched**, and those pages are fetched and read. Scores between 0.4 and 0.75 are **uncertain**: only the search snippet is kept, its facts are placed in *Needs review*, and the UI asks "Is this your business?". The page is fetched only after the owner confirms. Lower scores are discarded. A name match alone can never reach the matching threshold. If AI extraction later reports that a page describes a different business, the source is downgraded to uncertain.

Research runs after the owner's own sources in a job chain, so that phone numbers the owner supplied can be used for matching.

## How fact extraction works

1. **Rule-based:** JSON-LD fields, `tel:` and `mailto:` links, email patterns, and phone patterns. Phone numbers are accepted only with an international prefix, a nearby keyword ("call", "phone", "WhatsApp"…) or an Indian mobile pattern. Dates, years and time ranges are excluded.
2. **AI (when configured):** Gemini extracts facts against a fixed list of keys, with the source wrapped as an untrusted document.
3. **Grounding:** every candidate must be traceable to the source text. Phone digits must appear in the source; emails must appear literally; other values need 50–70% token overlap. Ungrounded values are dropped and logged.
4. Values are cleaned, normalised (phones compared on their last 10 digits; text lower-cased and stripped of punctuation) and de-duplicated per source. Re-processing a source replaces its undecided facts but keeps the owner's decisions.

## How conflicts are resolved

Conflicts apply to single-value keys (business name, phone, email, address, opening hours). A conflict exists when the active facts for a key hold more than one *normalised* value and not all of them are confirmed. Formatting differences are therefore not conflicts. The UI shows each distinct value with every source it came from. The owner can:

- pick one value (the others are rejected),
- type the correct value (stored as a confirmed manual fact; all others are rejected), or
- keep all values (phone and email only, where several can legitimately be true).

The system **never chooses a value itself**. "Confirm all" deliberately skips conflicting keys and facts from unconfirmed search results.

## How AI output is validated

- Gemini is asked for JSON matching a response schema. The response is decoded defensively (code fences are stripped and the outermost object extracted). A malformed response is retried and, if it is still malformed, reported as a failure.
- **AI writes copy only.** `SiteAssembler` fills contact details, the call-to-action link, images and the services list from confirmed facts. Service names suggested by the AI must match a confirmed service or product, or they are dropped. Every confirmed service is listed. Highlights must match confirmed highlights.
- `ClaimGuard` removes sentences containing specific claims (years in business, "since 19xx", awards, certifications, ratings, customer counts, percentages, prices, guarantees, superlatives such as "the best" or "#1") unless the claim appears in the owner's confirmed evidence. The owner is told when this happens during section regeneration.
- `WebsiteSchema` is the single validator for AI output, editor saves and publishing. It accepts only known section types, strips HTML and control characters, caps lengths, allows only `https`, `mailto:`, `tel:` and `#anchor` links, accepts only images owned by the business, and validates theme colours as hex.
- Generation builds the new site in memory and replaces sections in one transaction **only after it has been validated**. Any failure (outage, rate limit, invalid key, validation) leaves the existing website untouched and shows a clear error.
- Section regeneration returns a *proposal*. Nothing is written until the owner accepts it, and only that section changes.

## How prompt injection is handled

- Website, PDF and search content is wrapped in `<untrusted_document>` tags with a random ID. Any closing tags inside the content are neutralised, and control characters are removed.
- The system instruction states that document content is data, never instructions, and forbids code, HTML, links and disclosure of the configuration.
- Website generation does **not** send raw source text at all. It sends only a structured summary of *confirmed* facts plus the owner's own notes, which are also wrapped. Unconfirmed or rejected facts never reach the generation prompt (this is checked by a test).
- Even if a model were manipulated, it could only produce text inside known JSON fields. That text then passes through grounding, `ClaimGuard` and schema validation, and is rendered by React as plain text.
- Facts taken from external pages always start as suggestions that the owner reviews.

## Why structured JSON instead of AI-generated HTML

Generated HTML would be an XSS vector, could not be edited reliably field by field, could not be validated for factual accuracy, and would look inconsistent. Structured JSON gives one schema shared by generation, editing, saving, preview and publishing. It allows per-field editing and per-section regeneration, makes it possible to keep facts deterministic, and lets a single trusted renderer guarantee quality and responsiveness. The renderer never uses `dangerouslySetInnerHTML`.

## Other decisions

- **Publishing snapshots:** publishing stores a validated copy of the current content, so editing never changes the live site by accident. A content version counter drives the "unpublished changes" indicator.
- **Private media:** images are served by an authorised route. They are public only if they appear on a published snapshot. PDFs are never served.
- **Basic mode:** without a Gemini key, an honest fallback builds the site from facts with neutral wording, and the UI labels it as such.
- **Container queries** in the renderer make the Studio's mobile preview match a real phone, because the layout responds to the frame width rather than the browser window.
- **Owner input is trusted:** text the owner types is their own statement, so facts from it start confirmed. Everything gathered from elsewhere needs review.

## MVP scope

Everything listed in the README *Features* table is implemented and tested: authentication, dashboard, business CRUD, uploads, PDF/website/social/Google processing, research with identity resolution, fact review and conflict resolution, Gemini generation and per-section regeneration, the Studio (sections, design, assets), preview, publishing, updating, unpublishing and public sites.

## Features intentionally excluded

As required by the brief: e-commerce, payments, domains/DNS, template marketplace, analytics, teams and roles, an arbitrary HTML/JS editor, booking, translation, version history and a drag-and-drop page builder. Testimonials are also excluded, because reviews cannot be verified and must never be invented.

## Known limitations

- **Live APIs not exercised here:** in the build environment, Gemini and the search providers were tested only with faked HTTP responses and a local mock server, because no keys were available. Real-world prompt quality depends on the chosen model.
- **JavaScript-heavy sites** (including most Google Maps and Instagram pages) expose little content without a browser. They are marked Blocked or Failed honestly rather than scraped with headless browsers or by bypassing login walls.
- **Scanned PDFs** have no text layer. OCR is not included, so the owner is asked to type the details instead.
- **Public pages are client-rendered** (Inertia without SSR). The `<title>` and meta description are rendered on the server, but the body requires JavaScript. Inertia SSR can be enabled later.
- **Uploaded images are not analysed** by AI (no alt-text generation or photo selection).
- **Section reordering** uses up/down controls rather than drag and drop, as allowed by the brief.
- **Single locale (English)** UI.

## Future improvements

- Gemini vision for image captions, choosing a hero photo and OCR of scanned menus
- Inertia SSR for public pages, plus a sitemap and Open Graph images
- Custom domains, and edge caching of published snapshots
- More section types (FAQ from confirmed Q&A, menu/price list, map embed) and more presets
- Publication history with one-click rollback
- A per-user monthly AI usage quota
- Drag-and-drop reordering and inline text editing on the canvas
