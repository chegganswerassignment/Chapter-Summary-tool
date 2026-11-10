# Chapter Summary Tool — Source Report for Chapters V, VI, VII

**Audit date:** 2026-10-16. **Method:** direct code read, `php artisan test` run, `tsc --noEmit` run, read-only queries of the dev MySQL DB, and `storage/logs/laravel.log`.
**Not done:** no browser was driven, so any visual or UI check below is marked *user-reported*.

## How to read this report

- Every claim cites `path:line`.
- **[V]** = verified in code, DB or log. **[T]** = verified by a test I ran. **[U]** = user-reported, or not verifiable from the repo.
- `project_progress_report.md` (dated 2026-10-01), `README.md` and `Documentation.md` are **out of date**. They still describe local PDF/DOCX text extraction, per-task routing (Gemini + OpenAI) and `smalot/pdfparser` / `phpoffice/phpword`. Do not use them as sources. Examples:
  - The stale README claims Gemini 2.5 Flash plus GPT-4.1 mini (`README.md:12-13`).
  - `composer.json` has neither `smalot/pdfparser` nor `phpoffice/phpword`.
- **Git history is empty.** The only commit is `3d3f246 first commit`, and it contains only `README.md` (`git show --stat HEAD`). All other files are untracked (28 entries in `git status`). The history therefore cannot document the "problems encountered" in Part A5. That section relies on the code's current state, comments, tests, the log and what the user described.

---

# PART A — Chapter V (Implementation)

## A1. Architecture as built

Three tiers, in one Laravel monolith plus external AI APIs.

| Layer | Technology | Real responsibility | Key paths |
|---|---|---|---|
| **Frontend** | React 18.3 + TypeScript 5.9 + Inertia 2 (`package.json:9-40`) | Renders pages, client-side Zod validation, tab UI (summary / translation / quiz / pronunciation), live updates over WebSocket via Echo, admin AI panel, EN/AR i18n with RTL | `resources/js/Pages/*`, `Components/document/*`, `hooks/useDocumentChannel.ts:25-45`, `lib/echo.ts:13-40`, `lib/schemas.ts` |
| **Backend** | Laravel 12.69.2, PHP 8.2.12 | Auth, upload and validation, file storage, queue orchestration, AI abstraction, persistence, broadcasting, PDF export, admin CRUD | `app/Http/Controllers/*`, `app/Jobs/*`, `app/Services/Ai/*`, `app/Services/Export/*`, `routes/web.php:12-46` |
| **External AI** | Google Gemini, OpenAI, Anthropic REST APIs | Receive the **whole uploaded file** plus a prompt and return text or JSON | `app/Services/Ai/Clients/{Google,OpenAi,Anthropic}AiClient.php` |

Supporting infrastructure [V]:

- MySQL is the live dev DB. `.env` has `DB_CONNECTION=mysql` and the query confirmed `driver: mysql`. `.env.example:17` says `pgsql`, and `database/database.sqlite` is also present.
- Redis queue (`QUEUE_CONNECTION=redis`).
- Laravel Reverb WebSocket server.
- Local-disk file storage (`storage/app/private/documents`).
- Headless Chrome via Browsershot for PDFs.

Process model (`run-dev.bat:72-79`): `php artisan serve`, `npm run dev`, `reverb:start`, `queue:listen redis --tries=2 --timeout=600`, plus a log tail.

**Caveat:** the `composer dev` script (`composer.json:52`) does **not** start Reverb and uses `--tries=1`. `run-dev.bat` is the only launcher that starts all required services.

## A2. Core modules

### A2.1 File upload & validation

- **Client side:** Zod `documentUploadSchema` checks `instanceof File`, a MIME whitelist (PDF/DOC/DOCX) and a 20 MB cap. It is applied on the drop zone in `Pages/Documents/Index.tsx` using `lib/schemas.ts`. Upload progress comes from Inertia `onProgress`.
- **Server side:**
  - `StoreDocumentRequest::rules` (`app/Http/Requests/StoreDocumentRequest.php:17-27`) requires `file` with `mimes:pdf,docx,doc` and `max:20480` KB.
  - Both limits come from `config/documents.php:4,6`; the size limit is overridable via env `DOCUMENT_MAX_UPLOAD_KB`.
  - `mimes` inspects file content, not only the extension. The test comment at `tests/Feature/DocumentUploadTest.php:15-19` records that fake junk files are detected as `text/plain`, so the test builds a real `%PDF-` header.
- **Storing:** `DocumentController::store` (`:29-46`) stores the file on the `local` (private) disk under `documents/` (`:32`).
  - It creates a `Document` row with status `uploaded` (`:34-40`).
  - It dispatches `ExtractDocumentTextJob` (`:42`) and redirects to the document page.
- **Access control:**
  - `authorizeAccess` (`:76-82`) allows only the owner or an admin (403 otherwise). The same check is in `DocumentExportController.php:25-28`.
  - The private Reverb channel has the same rule (`routes/channels.php:10-14`).
  - Deleting removes the file and the row (`:59-67`).

### A2.2 Multimodal AI pipeline (native file submission)

**What the code does now** [V]:

- `ExtractDocumentTextJob` no longer extracts text. The comment says "Local text extraction is bypassed in favor of native multimodal processing" (`app/Jobs/ExtractDocumentTextJob.php:39`).
- The job only checks the file exists (`:33-37`), sets status `queued` (`:40-42`), broadcasts, then creates three `ProcessingResult` rows (summary, translation, quiz) and dispatches one `ProcessDocumentAiJob` per row (`:62-76`).
- `ProcessDocumentAiJob` resolves the absolute file path and MIME type (`ProcessDocumentAiJob.php:46-47`). It passes them to a task service, which passes them to `AiProviderFactory::chat(... filePath, mimeType ...)` (`AiProviderFactory.php:72-97`).
- The provider client base64-encodes the file bytes and sends them in the provider's native document format:

| Provider | PDF | DOCX | Other |
|---|---|---|---|
| Anthropic | `document` block, base64 (`AnthropicAiClient.php:37-45`) | text read locally from the zip's `word/document.xml` and sent as a text block (`:55-64`, `168-193`) | `.doc` falls to the `else` branch and is sent as a PDF block (`:70-79`); this is a latent bug (see C2) |
| Google | `inlineData` base64 (`GoogleAiClient.php:58-65`) | local XML text, falling back to `inlineData` (`:44-57`) | any other MIME goes to `inlineData` as-is |
| OpenAI | `file` part with a `data:` URL (`OpenAiClient.php:62-69`) | local XML text, falling back to `file` (`:39-54`) | images supported (`:55-61`) |

- **Nuance for the report:** PDFs are fully native. **DOCX is not.** All three clients do a minimal, dependency-free text extraction of DOCX (`ZipArchive` plus tag stripping) because the provider APIs do not accept DOCX natively. The rest of the code never extracts text.
- **Vestigial leftovers** [V]:
  - `documents.extracted_text` is never written. The dev DB has 0 non-null rows.
  - `Document::STATUS_EXTRACTING` is never set (`Document.php:17`).
  - The job keeps its old name `ExtractDocumentTextJob`.
  - `DocumentController.php:100-102` computes `original_word_count` from `extracted_text`, so it is always `null`. The Summary tab's "N words → M words" badge (`SummaryTab.tsx:20-27`) can therefore **never display**.

**Reasoning as it would appear in a report** (derived from the code and comments; the decision itself is user-described):

- Local PDF parsing and DOCX parsing lose layout, tables, figures and scanned or non-Latin content.
- Local extraction needs per-format parser dependencies and has format-specific failure modes.
- Modern multimodal models read PDFs directly (page text plus images), which gives higher fidelity and removes the extraction stage, its dependencies and its failure paths.
- The trade-offs (cost, privacy, payload size) are listed in C2.

### A2.3 AI provider abstraction layer

- **Contract:** `AiProviderClient::chat(systemPrompt, userPrompt, AiModel, apiKey, filePath, mimeType, jsonMode): AiChatResponse` (`Contracts/AiProviderClient.php:8-30`). The DTO carries `content`, `promptTokens` and `completionTokens` (`AiChatResponse.php:5-12`).
- **Factory:** `AiProviderFactory` has a slug-to-class registry (`:22-26`; google, openai, anthropic). `clientFor` throws `AiProviderException` on an unknown slug (`:28-37`). Adding a provider means adding a client class and one registry line.
- **Model resolution:** `resolveModelFor(task)` (`:44-67`) returns the `is_default` model for the task from an **active provider** (`:46-51`). It falls back to the `all`-specialty default (`:53-60`) and throws if none exists (`:62-64`). `chat()` then checks the API key (`:84-86`), builds the client, calls it and returns `{model, response}` (`:88-102`).
- **Single-active-model design** [V]:
  - Added on top of the factory, not in place of it: the per-task lookup path and its tests were kept as a legacy fallback (`AiProviderFactoryTest.php:27-97`).
  - One row has `task_specialty='all'` and `is_default=true`. `AiModel::TASK_ALL` is at `AiModel.php:15`.
  - The invariant is enforced at three layers:
    1. **Model layer:** a `saving` hook throws `DomainException` for a second active row (`AiModel.php:32-39`).
    2. **Validation layer:** `SingleActiveModel` rule (`app/Rules/SingleActiveModel.php:24-35`), used in `StoreAiModelRequest.php:29` and `UpdateAiModelRequest.php:31`.
    3. **Atomic switch:** `AiModel::activate()` clears all defaults and sets the new one in one DB transaction (`AiModel.php:80-90`).
  - Admin endpoint: `PUT admin/ai-models/active` (`routes/web.php:34`, `AiModelController.php:32-47`). It refuses a model whose provider is inactive (`:40-42`). Deleting the active model is blocked (`:49-52`).
  - Admin UI: a single `<select>` of all models grouped by provider (`resources/js/Pages/Admin/AiRouting.tsx:142-167`). Models of inactive providers are `disabled` (`:157`) and labelled.
- **API keys** are encrypted at rest. The `AiProvider::api_key` mutator calls `encrypt()` (`AiProvider.php:37-40`). The accessor decrypts and returns `null` on failure (`:42-53`). The column is hidden from serialization (`:21-23`). The admin page exposes only `has_api_key` (`AiProviderController.php:28`). A blank key on update keeps the old key (`:55-57`).
- **Why single-model instead of per-task routing:** this is a design decision (the user described it). The code supports these justifications:
  - One selector replaces a matrix of three per-task defaults (the old state allowed duplicates, which the seeder had to repair: `AiProviderSeeder.php:35,65-70`).
  - Impossible-state bugs are removed, such as "Claude is summary-only default" and "duplicate Gemini defaults" (`AiProviderFactoryTest.php:99-138`).
  - Every request goes through the same model, so behavior is consistent.
  - The cost is that one model must be good at all three tasks, and a single provider outage affects everything (see C2).

### A2.4 Asynchronous job pipeline & realtime status

Flow [V]:

1. `store()` creates the document (`uploaded`) and dispatches `ExtractDocumentTextJob`.
2. `ExtractDocumentTextJob` moves the document to `queued` then `processing` and broadcasts `DocumentStatusUpdated` (`ExtractDocumentTextJob.php:40-44,64-65`).
3. It creates three `ProcessingResult` rows (`pending`) and dispatches three independent `ProcessDocumentAiJob`s (`:67-75`).
4. Each `ProcessDocumentAiJob`:
   - sets `processing` (`ProcessDocumentAiJob.php:41`);
   - runs the matching task (`:53-58`);
   - saves `content_json`, `model_id`, token counts and `processing_time_ms` on success (`:62-69`);
   - on failure saves `failed` plus `error_message` (`:94-98`).
5. After each job it broadcasts `ProcessingResultReady` (`:101`) and calls `finalizeDocumentStatusIfComplete` (`:103,119-144`).
6. Finalize runs only once all 3 results exist and none is pending or processing. The document becomes `completed` unless **all** results failed (`:137-141`), then it broadcasts the final status.

Facts to carry into the chapter:

- Job settings: `ExtractDocumentTextJob` has `$tries=2`, `$timeout=60` (`:22,24`). `ProcessDocumentAiJob` has `$tries=2`, `$timeout=180`, `$backoff=15` (`:28,30,32`). The three AI jobs run independently, so they fail independently.
- **Partial failure is reported as `completed`.** If summary and quiz succeed and translation fails, the document status is `completed` and only that result is `failed`. This is real: the dev DB document is `completed` with a failed translation. `$expectedTaskCount = 3` is hard-coded (`:122`).
- **Realtime:**
  - Events implement `ShouldBroadcast` on the private channel `documents.{id}`, as `.document.status.updated` and `.document.result.ready` (`app/Events/DocumentStatusUpdated.php:18-37`, `ProcessingResultReady.php:18-40`).
  - Channel authorization is in `routes/channels.php:10-14`.
  - Frontend: `useDocumentChannel` subscribes (`hooks/useDocumentChannel.ts:28-44`) and `Pages/Documents/Show.tsx:50-78` patches local state. Echo is created in `lib/echo.ts:13-40`.
  - Because the events are `ShouldBroadcast` and the queue is Redis, the broadcast runs as a queued `BroadcastEvent` job (confirmed by the log stack trace at `laravel.log:756-775`).
- **Known unresolved defect — realtime push of results fails for large content** [V]:
  - `ProcessingResultReady::broadcastWith` includes the full `content` (`ProcessingResultReady.php:37`).
  - `config/reverb.php:89` sets `max_message_size` to 10,000 bytes.
  - `laravel.log` has 6 occurrences of `Pusher error: Payload too large` on 2026-10-12 (first at `:756`), coinciding with the successful live run.
  - The results themselves were saved to the DB (summary and quiz are `completed`). The push of the content to the browser failed, so the user would see results only after reloading the page. I did not verify the UI behavior (no browser run).
  - The small `DocumentStatusUpdated` payload is unaffected.

### A2.5 Pronunciation feature

- It is entirely client-side. There is no AI call, no job and no DB table (`Components/document/PronunciationTab.tsx`).
- It extracts candidate terms from the **summary text** with the regex `/\b[A-Z][a-zA-Z-]{3,}\b/g` (`:10`). It de-duplicates, drops a small stopword list (`:4-7`) and caps at 30 (`:13`).
- Each term is a button that calls the browser Web Speech API with `lang='en-US'` (`:16-23`). A "not supported" notice is shown when `speechSynthesis` is missing (`:28,37-43`).
- **Important caveat:** the summary prompt asks for **Arabic** output (`SummarizeText.php:23`). The extractor only matches capitalized Latin words. For an Arabic summary it will find only embedded English terms, so the term list is often empty or sparse. The pronunciation language is hard-coded to US English (`:21`). This interaction is a design gap, not observed live.
- The tab also depends on the summary having completed (`Show.tsx:81-82,172`).

### A2.6 PDF export (Browsershot / headless Chrome, RTL/Arabic)

- **Route:** `GET /documents/{document}/export?type=summary|translation|all` (`routes/web.php:24`). Handled by `DocumentExportController` (`:23-82`).
  - `quiz` is rejected as a standalone type (`EXPORTABLE_TYPES`, `:21`; `Controller.php:32-34`; test at `DocumentExportTest.php:232`). It is included only inside `all`.
  - `SECTION_ORDER` is summary, translation, quiz (`:19`).
- **Pipeline:**
  1. Fetch completed results only (`:38-42`).
  2. Build sections (`:44-47`, `buildSection` at `:92-120`). Text goes through `Str::markdown(..., html_input=strip, allow_unsafe_links=false)` (`:126-136`) so model output cannot inject HTML. Translations keep soft line breaks as `<br>` (`:130-132`).
  3. Detect direction per section. RTL means Arabic letters are present and at least as common as Latin letters (`isRtl`, `:138-144`). The frontend uses the same rule (`resources/js/lib/textDirection.ts:5-10`).
  4. Render the Blade view `resources/views/pdf/document-export.blade.php` with root `dir`/`lang` taken from the first section (`:55-65`; view line 15).
  5. `DocumentPdfRenderer::render` runs `Browsershot::html()->format('A4')->showBackground()->margins(15,12,18,12)->disableJavascript()->timeout(...)` (`DocumentPdfRenderer.php:16-21`). Chrome, node and npm paths are configurable (`:23-33`, `config/pdf.php:3-12`). The timeout defaults to 60 s.
  6. Stream the PDF as a download with `Cache-Control: private, no-store` (`:75-81`). The filename is `<name without extension> - <type>.pdf` with illegal characters stripped (`:146-152`).
- **Failure handling:** any renderer failure becomes `PdfRenderingException` (`DocumentPdfRenderer.php:36-40`). The controller logs it and redirects back with a friendly flash error, never a 500 (`:67-73`).
- **Arabic / RTL specifics:**
  - The app's compiled Tailwind CSS is inlined from `public/build/manifest.json` so Chrome needs no network (`DocumentPdfRenderer.php:48-64`; view line 21). If no build exists, a plain fallback style is used (view line 46).
  - The font stack is `'Segoe UI', Tahoma, 'Noto Naskh Arabic', 'Noto Sans Arabic', Arial, sans-serif` (view line 25). These are **system fonts, not bundled** (see C2).
  - Per-section `dir`/`lang` and logical CSS (`padding-inline-start`, `border-inline-start`) handle mixed content. Code blocks are forced LTR.
  - Headings are localized (`الخلاصة المعرفية` / `التعريب الأكاديمي` / `التقييم الذاتي`) at view lines 2-6.
  - The document title and date are wrapped in `<bdi>` (view lines 61, 64) so bidirectional text does not reorder (see A5-5).
- **UI:** `ExportButton.tsx` is a plain `<a>` when enabled and a disabled `<span aria-disabled>` with a tooltip otherwise (`:16-38`). There is a per-tab export for summary and translation plus an "Export All" button (`Show.tsx:85-87,141-160`).

## A3. Database schema as implemented

Migrations are in `database/migrations/`. Eloquent models are in `app/Models/`.

| Table | Purpose | Key columns and decisions | Cite |
|---|---|---|---|
| `users` | Auth, role and locale | Breeze default plus `role` (default `student`) and `locale(5)` (default `en`) | `2026_01_01_000001_add_role_to_users_table.php:12-13` |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` | Framework tables | Laravel defaults | `0001_01_01_00000{0,1,2}_*` |
| `ai_providers` | AI vendors | `slug` UNIQUE, nullable `base_url`, `api_key_encrypted` TEXT, `is_active` default true | `…000002_create_ai_providers_table.php:12-18` |
| `ai_models` | Models offered per provider | FK `provider_id` with cascade delete; `context_window` default 8192; `task_specialty` string (`all`/`summary`/`translation`/`quiz`); `is_default`; composite index `(task_specialty, is_default)` for the factory lookup | `…000003_create_ai_models_table.php:13-21` |
| `documents` | Uploaded chapters | **UUID primary key**; FK `user_id` cascade; `original_name`, `file_url` (path on the local disk), `mime_type`, `file_size`; `extracted_text` longText **(now unused)**; `status`; `failure_reason`; index on `status` | `…000004_create_documents_table.php:12-23` |
| `processing_results` | One row per AI task per document | **UUID primary key**; FK `document_id` (uuid) cascade; `model_id` nullable FK with `nullOnDelete` (history survives model deletion); `result_type`; `status`; `content_json` JSON; `prompt_tokens`, `completion_tokens`, `processing_time_ms`; `error_message`; index `(document_id, result_type)` | `…000005_create_processing_results_table.php:12-24` |
| `system_settings` | Key/value settings | `key` UNIQUE, `value`, `type`, `description`; model `SystemSetting::get/set` with `Cache::rememberForever` | `…000006_create_system_settings_table.php:12-17`, `SystemSetting.php:17-43` |

Decisions worth explaining:

- **UUID keys** on `documents` and `processing_results` (`HasUuids`, `Document.php:13`, `ProcessingResult.php:12`) avoid enumerable IDs in URLs and in the broadcast channel name `documents.{uuid}`. This is my inference; the code shows no stated rationale.
- **Integer keys** on config tables (providers and models).
- **Status as string constants** on the models (`Document.php:15-25`, `ProcessingResult.php:14-20`), not DB enums, which keeps the schema portable across MySQL, PostgreSQL and SQLite.
- **JSON content column** stores summary and translation as `{text}` and quiz as `{questions:[...]}`. This lets one table serve all result types (`ProcessDocumentAiJob.php:60`).
- **Token and latency columns** exist for cost and performance analysis. They are never displayed in the UI.
- **Indexes:** only the three above. There is no unique DB index on the "one active model" rule; it is enforced in application code (see A2.3).
- **`system_settings` has no consumer** (grep of `SystemSetting::` use in app code finds only the model itself). It is unused at runtime.

## A4. Engineering decisions & trade-offs

1. **One active AI model for all tasks vs per-task routing**
   - Ease of use and invariant safety vs flexibility; see A2.3.
   - The per-task path is still present as a fallback (`AiProviderFactory.php:53-60`).
2. **Retry / backoff for transient provider errors** (`ProcessDocumentAiJob.php:70-85,110-117`; `AiProviderException.php:11,31-38`)
   - Retryable: `ConnectionException`, HTTP 408, 429 and any status of 500 or above. 529 "overloaded" is covered by the `>= 500` rule.
   - Not retryable: 400, 401, 403, 404, 422, empty or invalid-JSON responses (no status), and a missing model or key.
   - Mechanism: the job logs a warning, resets the result to `pending`, calls `release(15)` and returns *without* broadcasting or finalizing, so the UI doesn't flash a false failure (`:81-84`; asserted by `ProcessDocumentAiJobRetryTest.php:99-100`).
   - Limit: `$tries = 2`, so at most **one** retry with a fixed 15 s delay (no exponential backoff, no jitter).
   - The retry applies per result, not per document.
3. **Browsershot (headless Chrome) vs dompdf / mPDF for Arabic PDF**
   - Chrome shapes Arabic (contextual forms, ligatures) and handles bidi, modern CSS and the app's own Tailwind CSS.
   - The code shows the app CSS being reused (`DocumentPdfRenderer.php:48-64`). The user stated the reason for choosing Chrome over dompdf/mPDF. That history is not in the repo.
   - Costs: it needs Node and Chrome on the server; a render launches a browser process per request, bounded by the timeout; it depends on system fonts.
   - Tests mock the renderer. One test uses real Chrome (`DocumentExportTest.php:253`).
4. **Whole-file submission instead of local extraction**
   - Fidelity and simplicity vs cost, privacy and size limits; see A2.2 and C2.
   - There is no chunking or page cap. Provider-side file-size limits apply and are not handled in code.
5. **Queue + broadcast for UX**
   - A fast redirect plus live updates instead of a synchronous request.
   - It adds Redis and Reverb as hard dependencies (no sync fallback is configured; `QUEUE_CONNECTION=redis` in `.env`).
6. **Defense against model-generated HTML**
   - Server: `html_input=strip`, `allow_unsafe_links=false` (`DocumentExportController.php:128`).
   - Client: `react-markdown` with `skipHtml`, images replaced by alt text and links forced to `rel=noopener noreferrer nofollow` (`MarkdownContent.tsx:19-27,48`).
7. **API keys encrypted with the app key**
   - Rotating `APP_KEY` makes stored keys undecryptable; the accessor then returns `null` and the job fails with "no API key configured" (`AiProvider.php:48-52`, `AiProviderFactory.php:84-86`).
8. **Quiz robustness**
   - JSON mode on Google and OpenAI. Anthropic gets a system-prompt instruction (`AnthropicAiClient.php:87-90`).
   - Fences are stripped and the shape is validated, with at least 2 options per question (`GenerateQuiz.php:58-82`).
   - The client validates again with Zod (`QuizTab.tsx:19`).
   - Malformed JSON fails the task with no retry (see C3).
9. **Seeder idempotency**
   - Never overwrites a stored key with a blank env value (`AiProviderSeeder.php:80-82`).
   - Never re-enables a provider an admin disabled (`:84-88`).
   - Resets to a single default (`:35,41-49`).
10. **i18n and RTL**
    - i18next with localStorage and browser detection (EN/AR).
    - The `locale` column is persisted through `LocaleController`. `SetLocale` picks user locale, then cookie, then config (`SetLocale.php:16-18`).

## A5. Real problems encountered and how they were solved

**Evidence-level warning:** with no git history, each item below is graded by what the repo can prove. Do not present the unverifiable parts as measured fact.

| # | Problem | Symptom | Root cause | Fix (where visible) | Evidence |
|---|---|---|---|---|---|
| 1 | **Gemini 503 "high demand"** | All three tasks failed with `UNAVAILABLE` / "This model is currently experiencing high demand" | Provider-side overload, a transient error. Originally every error was a permanent failure | Retry classification plus release with 15 s backoff (A4-2). `AiProviderException::isRetryable` (`:31-38`) | Log: first 503 failures 2026-09-28 19:50 (`laravel.log:127`), 2026-10-12 18:27 (`:~700-718`). Warning/retry entries `:834,842`; final failure on attempt 2 `:928`. **Still reproducible**: the dev DB translation result is `failed` with a 503 after the retry [V]. [T] 15 retry tests |
| 2 | **Swallowed-exception retry bug** | A retry was intended but the failure was not retried or the exception was swallowed | The catch block handled the exception without releasing; `release()` needs a `return` to avoid fall-through into the broadcast and finalize code | Current code: warn, set `pending`, `release($this->backoff)`, `return` (`ProcessDocumentAiJob.php:71-85`) | The original buggy code is not in the repo [U]. The correct behavior is asserted by `ProcessDocumentAiJobRetryTest.php:85-101` (no broadcast on retry) and `:189-209` (the retry succeeds) |
| 3 | **`getCode()` status-parsing bug** | HTTP status not available to the retry logic | Clients throw `"… request failed (503): …"` with no code, so `getCode()` was 0 | `AiProviderException` parses the status from the message when none is passed and stores it in `getCode()` too (`:19-24,40-45`) | Fix present. [T] `AiProviderExceptionTest.php:11-17` asserts `getStatusCode()` and `getCode()` both 503 |
| 4 | **Provider re-activation seeder bug** | Re-running the seeder re-enabled a provider the admin had turned off, and could also overwrite keys with null | `updateOrCreate` set `is_active=true` and the key unconditionally | `upsertProvider` sets `is_active` only on creation and skips blank keys (`AiProviderSeeder.php:73-93`) | [T] `AiProviderFactoryTest.php:140-148` |
| 5 | **PDF title bidi reordering** | Mixed Arabic/English document titles or dates rendered in the wrong order in the PDF header | Bidirectional text reordering of a title inside a different-direction container | Wrapped title and date in `<bdi>` (`document-export.blade.php:61,64`) | The `<bdi>` fix is visible [V]. The original symptom is unobserved in the repo [U]; no automated test asserts `<bdi>` |
| 6 | **Pint reformatting incident** | A code formatter run reformatted files unexpectedly | Laravel Pint (`composer.json:23`, v1.30.4 installed) applied project-wide style | Not recoverable from the repo | **Unverifiable** [U]. No git history, no diff and no log entry. Do not cite specifics without the user's account |
| 7 | **Duplicate / legacy per-task defaults** | Several `is_default=true` rows per task (the old architecture allowed it) | Per-task default design | Single-active invariant (3 layers) plus seeder cleanup (`AiProviderSeeder.php:35,65-70`) | [T] `ActiveModelTest.php` (8 tests) and `AiProviderFactoryTest.php:99-138` |
| 8 | **Task failures from missing config** | Every task failing immediately | No API key (09-28, 10-01), "No default AI model for task [translation]" (10-01 to 10-12) while only some tasks had a default | Single active model and seeded defaults | Log lines 92-94, 269-271, 635-643 [V] |
| 9 | **Anthropic account/API errors during live testing** | Summary failed with 401 "API key is invalid" (10-01, 10-12), 400 "key not scoped to a workspace" (10-05), 400 "credit balance too low" (10-12) | Key and account configuration, not code | None needed in code; these are non-retryable by design | Log lines 635-641, 667, 699 [V]. **No Anthropic call has completed successfully in the dev DB** |
| 10 | **Realtime payload too large** | The push of the AI result to the browser fails | Full result content included in the broadcast payload, above Reverb's 10 KB limit | **Not fixed** | `laravel.log:756,795,850,889,936,975`; `ProcessingResultReady.php:37`; `config/reverb.php:89` |
| 11 | **Queue listener timed out** | `queue:listen` child exceeded 600 s | A long-running or idle worker process | n/a | `laravel.log:2715` [V]; root cause not determined |
| 12 | **Test-suite hygiene** | Fake uploads detected as `text/plain` and rejected | `UploadedFile::fake()` content | Real `%PDF-` header fixture | `DocumentUploadTest.php:15-29` |

## A6. Tooling / environment actually used

**Runtime (measured):**

| Item | Version | Source |
|---|---|---|
| PHP | 8.2.12 (ZTS, VC++ 2019), served from XAMPP (`C:\xampp\php\php.exe`, `laravel.log:2715`) | `php -v` |
| Laravel | 12.69.2 | `php artisan --version` |
| Node / npm | v22.13.0 / 11.0.0 | |
| DB | MySQL (dev `.env`); tests use SQLite `:memory:` | `.env`; `phpunit.xml:26-27` |
| Queue / broadcast / cache | Redis (predis) / Reverb / `database` store | `.env`; `phpunit.xml:24-30` |
| OS | Windows 11 | |

**Backend packages** (`composer.json:8-27`; installed versions from `composer show`):

| Package | Version (constraint) | Why chosen |
|---|---|---|
| laravel/framework | 12.69.2 (`^12.0`) | Core |
| inertiajs/inertia-laravel | 2.0.28 | Server-driven React SPA without a separate API |
| laravel/reverb | 1.12.0 | First-party WebSocket server for live status |
| laravel/sanctum | 4.3.3 | Present via Breeze; not used for token auth |
| predis/predis | 3.6.1 | Redis queue client |
| spatie/browsershot | 5.4.0 | Headless Chrome PDF |
| tightenco/ziggy | 2.6.4 | Laravel named routes in JS (`route()` in `Show.tsx`) |
| laravel/tinker | 2.11.1 | |
| Dev: laravel/breeze 2.4.2, pint 1.30.4, pail 1.2.7, sail 1.68.0, phpunit 11.5.56, mockery 1.6.15, collision 8.9.5, faker 1.24.1 | | Auth scaffold, formatter, log tail, tests |

**Frontend packages** (`package.json:9-40`; installed versions from `npm ls`):

- React 18.3.1, TypeScript 5.9.3, Vite 7.3.6.
- Tailwind 3.4.19 (+ `@tailwindcss/forms` 0.5.11 and `@tailwindcss/typography` 0.5.20).
- Inertia React 2.3.28.
- `@headlessui/react` 2.2.10 (modals and switches).
- Zod 3.25.76 (validation).
- i18next 23.16.8 (+ react-i18next 15.7.4 and the browser language detector).
- laravel-echo 1.19.0 and pusher-js 8.6.0 (the Reverb client).
- react-markdown 10.1.0 and remark-gfm 4.0.1.
- lucide-react 1.48.0 (icons).
- puppeteer 25.13.0, which supplies Chrome for Browsershot.
- axios 1.20.0.

The build step is `tsc && vite build` (`package.json:6`). `npx tsc --noEmit` printed no errors during this audit.

**Dropped dependencies:** `smalot/pdfparser` and `phpoffice/phpword` are mentioned in the stale docs but are **absent** from `composer.json`.

---

# PART B — Chapter VI (Testing & Evaluation)

## B1. Automated test suite inventory

**Result of `php artisan test --testdox` on 2026-10-16: OK — 93 tests, 264 assertions, 0 failures, 0 skipped, about 52–61 s.**

Test environment (`phpunit.xml:24-30`): SQLite in memory, `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=null`, `CACHE_STORE=array`. This differs from production-like dev (MySQL, Redis, Reverb).

Breakdown by feature area:

| Area | File (tests) | What is actually asserted |
|---|---|---|
| **Upload** (4) | `Feature/DocumentUploadTest.php` | A valid PDF upload redirects, creates a `documents` row (`user_id`, name, status `uploaded`), stores the file on the (faked) disk and pushes `ExtractDocumentTextJob` with the document id (`:31-55`). A `.exe` is rejected with a session error on `file` and creates no row (`:57-70`). A guest is redirected to login (`:72-75`). Another user's document view returns 403 (`:77-94`) |
| **AI provider factory / model selection** (6 + 8) | `Unit/AiProviderFactoryTest.php` | Resolves a task-specific default (`:27`). Falls back to the `all` model for summary, translation and quiz (`:46`). A task-specific model beats `all` (`:71`). The seeder leaves exactly one default and collapses duplicate Gemini rows (`:99`). The seeder does not re-activate a disabled provider (`:140`). Throws `No default AI model is configured for task [summary]` (`:150`) |
| | `Feature/ActiveModelTest.php` | Activation makes one default and clears legacy per-task defaults, and the factory resolves it for all 3 tasks (`:40`). A model of an inactive provider cannot be activated (`:62`). A student gets 403 (`:75`). A second `all` default is rejected on create (`:84`) and on update (`:103`). The model layer throws `DomainException` (`:120`). The active model cannot be deleted but spares can (`:130`). The admin page's Inertia props contain `activeModelId` and `providers.0.models.0.is_active` (`:144`) |
| **AI client (Anthropic only)** (3) | `Unit/AnthropicAiClientTest.php` | Correct URL and headers (`x-api-key`, `anthropic-version`, `anthropic-beta`), model code and system prompt; parses content and token usage (`:35`). A PDF is sent as a `document` block with media type `application/pdf` (`:79`). A 401 raises `AiProviderException` (`:135`) |
| **Quiz parsing** (3) | `Unit/GenerateQuizTest.php` | Valid JSON is parsed (`:37`). Markdown code fences are stripped (`:69`). Non-JSON raises `AiProviderException` (`:91`). The factory is mocked. The first argument passed to `handle()` is a text string, not a real file path |
| **Exception classification** (13) | `Unit/AiProviderExceptionTest.php` | The status is parsed from `"request failed (503)"` and `getCode()` equals 503 (`:11`). An explicit status wins over the message (`:20`). No status means not retryable (`:28`). Data set: 408, 429, 500, 503, 529 are retryable; 400, 401, 403, 404, 422 are not (`:36-49`) |
| **Jobs / retry logic** (15) | `Feature/ProcessDocumentAiJobRetryTest.php` | Uses `Http::fake` and the job's fake queue interactions. 503 on attempt 1 is released with a 15 s delay, status goes back to `pending`, no error message, no broadcasts (`:85`). 503 on the final attempt marks the result `failed` with the status and message in `error_message`, and broadcasts (`:103`). 401 fails at once with exactly 1 HTTP call (`:119`). Data sets: 408, 429, 500, 502, 529 are released (`:147`). 400, 403, 404, 422 fail immediately (`:157`). `ConnectionException` is released (`:167`). A missing default model fails with no HTTP call (`:176`). 503 followed by 200 ends in `completed` with the content saved (`:189`) |
| **PDF export** (16) | `Feature/DocumentExportTest.php` | With the renderer mocked: a single type returns a PDF download with the right headers and filename (`:91`). `all` includes a heading per type and the quiz content (`:105`). The type defaults to `all` (`:124`). In-progress results are skipped (`:136`). Arabic gives `<html lang="ar" dir="rtl">`, Markdown becomes `<h2>`, `<strong>` and `<li>`, and `<script>` is stripped (`:148`). English gives `ltr` and "Knowledge Summary" (`:162`). The filename drops the extension and survives Arabic names (`:173`). A non-owner gets 403 (`:186`). An admin can export any document (`:193`). A guest is redirected (`:201`). Not-ready, missing and empty cases give a flash error and **never launch Chrome** (`:206-230`). Unknown types including `quiz` and `../../etc/passwd` are rejected (`:232`). A renderer failure gives a friendly error, not a 500 (`:241`). **One real-Chrome test** produces a response starting with `%PDF-` (`:253`) and skips if Chrome is unavailable; it **ran and passed** this time |
| **Auth** (18) | `Feature/Auth/*` (6 files) | Breeze defaults: login screen, login ok/bad, logout (4); email-verification screen, verify, bad hash (3); confirm password (3); reset link/screen/reset (4); password update (2); registration (2) |
| **Profile** (5) | `Feature/ProfileTest.php` | Breeze defaults: page, update, unchanged email keeps verification state, delete account, wrong password blocks delete |
| **Boilerplate** (2) | `Feature/ExampleTest.php`, `Unit/ExampleTest.php` | Skeleton smoke tests (home returns 200; `true`) |

Totals: ActiveModel 8, Export 16, Upload 4, Retry 15, Exception 13, Factory 6, Anthropic 3, Quiz 3 = **68 project-specific**. Auth 18, Profile 5, Example 2 = 25 scaffold. 68 + 25 = **93**.

Other static check: `npx tsc --noEmit` ran clean (no output). The frontend has **no test runner** (no vitest/jest in `package.json`).

## B2. Manual / live verification performed

**What the repo and DB can prove (evidence of live runs):**

1. **One real end-to-end run on 2026-10-12 ~18:40** [V]:
   - A 38,223-byte DOCX (`Senior_Project_Report_Revised.docx`) was uploaded and processed with **Gemini 3.8 Flash**. The document ended as `completed`.
   - Summary: completed in 18,508 ms, 6,207 prompt tokens, 1,835 completion tokens.
   - Quiz: completed in 39,949 ms, 6,306 prompt tokens, 1,591 completion tokens.
   - Translation: `failed` (503, high demand) after the retry (`laravel.log:834` retry warning, `:928` final failure).
   - Source: read-only DB query. `processing_time_ms` is measured inside the job (`ProcessDocumentAiJob.php:43,68`).
2. **Earlier live attempts that failed** [V]:
   - No API key (09-28).
   - Google 404 (09-28 19:41).
   - Google 503 (09-28 and 10-01).
   - Anthropic 401 (10-01, 10-12), 400 workspace-scoping (10-05) and 400 insufficient credit (10-12).
   - They prove a real app, real queue and real provider calls ran.
3. **Real headless Chrome PDF** [T]: the automated test at `DocumentExportTest.php:253` generated a valid PDF from Arabic content. It only asserts the `%PDF-` prefix, not glyph correctness.
4. **Realtime**: the log shows the `BroadcastEvent` job executing in a Redis worker (`:756-775`), so Redis and Reverb were running. The Payload-too-large errors (A2.4) were observed there.
5. **Leftover uploaded files**: `storage/app/private/documents` holds 3 files (2 DOCX, 1 PDF) from 09-28, 10-01 and 10-12. The DB has only 1 document row.

**What I cannot verify from the repo (mark as user-reported if the user supplies it) [U]:**

- Visual inspection of the rendered Arabic PDF. No exported PDF artifact exists in the repo.
- Admin UI dropdown behavior and disabled states. The code does set `disabled` on options of inactive providers (`AiRouting.tsx:157`), disables Delete on the active model (`:317`) and disables export buttons until ready (`ExportButton.tsx:16-27`). The render in a browser was not observed by me.
- Real browser upload via drag-and-drop.
- Whether the Pronunciation tab produced usable terms on a real (Arabic) summary.
- Cross-browser behavior.

## B3. Known gaps in test coverage

- **OpenAI client: no tests.** **Google client: no tests.** Only `AnthropicAiClient` has tests, and none cover DOCX, image, `.doc` or `jsonMode`.
- `AiProviderFactory::chat()` (key check, client dispatch) and `clientFor()` are not tested. Only `resolveModelFor` is.
- **No full-pipeline test:** upload, `ExtractDocumentTextJob`, three `ProcessDocumentAiJob`s, finalization and broadcast has never run end-to-end in automation.
  - `ExtractDocumentTextJob` has no test of its own. The upload test only asserts the job was pushed (`DocumentUploadTest.php:54`).
  - `finalizeDocumentStatusIfComplete` is not tested: the partial-failure → `completed` rule, the all-failed → `failed` rule, and the hard-coded count of 3.
- `SummarizeText`, `TranslateText`: untested. `GenerateQuiz` is tested only through a mocked factory and not for question-shape validation failures (`GenerateQuiz.php:71-79`).
- Broadcast events and channel authorization (`routes/channels.php`) are untested. The oversized-payload defect would not be caught by any current test.
- Admin provider CRUD (`AiProviderController`) is not tested, including the "blank key keeps the old key" rule (`:55-57`). `LocaleController`/`SetLocale` are untested.
- Document destroy and list are untested. `DocumentController::show` is tested only for 403.
- PDF content quality: the real-Chrome test checks only the `%PDF-` prefix. Arabic shaping and font availability are not asserted.
- Environment parity: tests run on SQLite in memory with sync queue and no broadcasting, never on MySQL, Redis or Reverb.
- No frontend tests of any kind (no unit, component or browser tests). Frontend correctness relies on `tsc` plus manual use.
- No load, concurrency, security (beyond access-control tests) or accessibility testing.
- `GenerateQuizTest` passes a text string where `handle()` now expects a file path (`GenerateQuizTest.php:62,86,103`). It still passes only because the factory is mocked, so this is stale-test evidence of the architecture change.
- Three Auth/Profile test groups are Breeze scaffolding (25 tests) and say nothing about the project's own logic.

## B4. Non-functional requirements verification

I did not have the text of Chapter III. Verify its NFR wording against what follows.

| NFR | Evidence found | Status |
|---|---|---|
| **30-second summary response time** | One live sample: summary job duration **18.5 s** (Gemini 3.8 Flash, 38 KB DOCX, 6.2k prompt tokens). The quiz in the same run took 39.9 s. | **Never systematically measured.** n = 1, one provider, one small file. `processing_time_ms` excludes queue wait, websocket delivery and the user's perception. Retry adds a fixed 15 s on a transient error (`ProcessDocumentAiJob.php:32`). Anthropic and OpenAI never completed successfully in the dev data. HTTP client timeout is 180 s (`*AiClient.php`), job timeout 180 s. No evidence for larger files (20 MB limit). |
| **Browser compatibility** | Not tested. The only compatibility handling is the Web Speech API feature check (`PronunciationTab.tsx:28,37`). | **Never tested** in any browser matrix. |
| **Multi-user handling** | Per-user isolation is tested: owner-only access (`DocumentUploadTest.php:77`, `DocumentExportTest.php:186`) and admin override. The queue (Redis) supports concurrent workers by design. | **No concurrency or load test.** The dev DB contains 2 users and 1 document. Behavior with parallel uploads, several workers, or Reverb connection limits was not measured. |
| **Security (if listed)** | Private disk storage (`DocumentController.php:32`). Encrypted API keys (`AiProvider.php:37-40`). Admin middleware (`EnsureUserIsAdmin.php`). HTML sanitisation on server and client. CSRF on Echo auth (`echo.ts:20-34`). | Partially evidenced by code and tests. No penetration or dependency audit. |
| **Localization / RTL** | EN and AR UI strings, RTL layout. Server and client direction detection share one rule. | Verified by code; the RTL rendering was not observed by me. |

---

# PART C — Chapter VII (Conclusion & Future Work)

## C1. Feature-by-feature final status

| Feature | Status | Detail and caveat |
|---|---|---|
| **Summarization** | **Working** | A live Gemini run completed (18.5 s) [V]. Output is Markdown, rendered safely and exportable (`SummarizeText.php`, `SummaryTab.tsx`). Caveats: (a) the prompt forces Arabic unless otherwise requested (`SummarizeText.php:23`) and nothing in the UI lets the user choose; (b) the "word reduction" badge never appears (`DocumentController.php:100-102`, always null); (c) a 503 at the final attempt fails the result, and there is no retry button. |
| **Translation** | **Working with caveats** | Translates to Arabic only. The `$targetLanguage` parameter is unused (`TranslateText.php:15`). In the one live run the translation **failed** after the retry because of a Gemini 503. Anthropic's `max_tokens=4096` (`AnthropicAiClient.php:94`) would likely truncate a long-document translation (not observed, since Anthropic never completed). |
| **Pronunciation** | **Working with significant caveat** | Client-side Web Speech works where the browser supports it. Term extraction targets capitalized English words in the summary and speaks `en-US` (`PronunciationTab.tsx:10,21`). Because summaries are generated in Arabic, the term list will often be empty. Not observed live. Not a server feature, so nothing is stored or tested. |
| **Quiz generation** | **Working** | A live Gemini run completed (39.9 s). Prompt requests exactly 5 Arabic MCQs with 4 options and explanations, in JSON (`GenerateQuiz.php:16,18-35`). Parsed and validated twice (`GenerateQuiz.php:58-82`, `QuizTab.tsx:19`). Malformed JSON fails the task with no retry. The quiz count is not configurable from the UI. |
| **PDF export** | **Working with caveats** | Automated tests including a real Chrome run pass. Summary and translation export separately; "all" adds the quiz **with answers and explanations** (`document-export.blade.php:90,97-101`). Depends on Chrome, Node and system fonts (see C2). Visual quality of Arabic output was not independently verified. |
| **Realtime updates** | **Working with a defect** | Status updates broadcast fine; the full-result push exceeds Reverb's 10 KB limit (A2.4). |
| **Admin AI routing** | **Working** | Single active-model selector, provider enable/disable, CRUD, tests for the invariant. |
| **Auth / roles / i18n** | **Working** | Breeze auth with admin/student roles and EN/AR UI. Email verification is not enforced (see C2). |

## C2. Known limitations as they currently stand

1. **No provider failover.** If the active model's provider errors (or is disabled), the tasks fail. After one retry, `resolveModelFor` simply throws or the client throws. Nothing falls back to another provider or model (`AiProviderFactory.php:44-67`). Disabling the active model's provider leaves no resolvable model (the `whereHas` active filter at `:50`).
2. **Arabic font dependency on system fonts.** The PDF font stack names Segoe UI, Tahoma, Noto Naskh/Sans Arabic and Arial (`document-export.blade.php:25`). None are bundled. On Windows (Segoe UI, Tahoma, Arial exist in `C:\Windows\Fonts`) this works. On a Linux server without Arabic-capable fonts, Arabic glyphs may render as boxes. The UI web fonts load from a CDN (`resources/views/app.blade.php:11-12`, fonts.bunny.net), but the PDF does not use them.
3. **Quiz answers are included in the "Export All" PDF** (`DocumentExportController.php:19,36`; view lines 90, 97-103). There is no option to omit them.
4. **Email verification is not enforced.** `MustVerifyEmail` is commented out on `User` (`User.php:5`). The `verified` middleware on the routes (`routes/web.php:18,28`) therefore passes for everyone.
5. **No local text extraction: whole files go to external AI providers.**
   - Privacy and cost implication: the complete document is uploaded to a third party and billed in tokens. The live run used about 6.2k prompt tokens per task for a 38 KB DOCX, and the file is re-sent **three times** (one request per task) (`ExtractDocumentTextJob.php:67-75`).
   - There is no size or page guard beyond the 20 MB upload limit.
   - There is no user consent notice in the code.
6. **`.doc` (legacy Word) is accepted but likely broken.**
   - `config/documents.php:6` allows `doc`.
   - The Anthropic client sends unknown types as a PDF block (`AnthropicAiClient.php:70-79`).
   - Google and OpenAI send `application/msword` as an inline file.
   - The legacy DOC format is not handled by the local zip reader, and none of this is tested.
7. **`system_settings` table and model are unused.** There is no UI and no consumer (A3).
8. **Nothing committed to git.** Only `README.md` is in the repository; the whole application is untracked.
9. **Retry is minimal:** one retry, fixed 15 s, per task. It does not apply to a malformed AI JSON response or to empty responses.
10. **Partial failure is reported as `completed`**, and there is no per-result retry UI (`ProcessDocumentAiJob.php:137-141`).
11. **Result push fails for large outputs** (A2.4). Users may need to reload to see results.
12. **Dead or vestigial code and data:** `extracted_text` column, `STATUS_EXTRACTING`, the misnamed `ExtractDocumentTextJob`, and the unused `$targetLanguage`. The `'summary'`/`'translation'`/`'quiz'` task prompts are hard-coded in Arabic.
13. **Operational dependencies:** MySQL, Redis and Reverb must all be running. `composer dev` omits Reverb (`composer.json:52`). There is no health check or sync fallback.
14. **Other:**
    - The document list is unpaginated (`DocumentController.php:18-22`).
    - Delete confirmations use `window.confirm` in the admin UI.
    - `README.md`, `Documentation.md` and `project_progress_report.md` are stale.
    - Seeded demo accounts use the password `password` (`DatabaseSeeder.php:19-31`): a deployment risk if seeded in production.
    - Hard-coded provider model codes in the seeder (`gemini-3.8-flash`, `gpt-6-luna`, `claude-haiku-4-5`) are reference rows only.
    - Only the Anthropic client is unit-tested.
    - Temperature is hard-coded to 0.4 in all clients.

## C3. Legitimate future-work directions (grounded in the current architecture)

1. **Provider failover / fallback chain.** The factory already isolates providers (`AiProviderFactory.php:22-26`). Add an ordered fallback list tried on retryable exhaustion (`isRetryable()` already classifies the cases).
2. **Retry of malformed AI JSON.** `GenerateQuiz::decode` throws with no retry (`GenerateQuiz.php:67-79`). Add a bounded re-ask, or use structured-output / schema features.
3. **Bundle Arabic web fonts** (for example Noto Naskh Arabic or Tajawal, already used in the UI) via `@font-face` in the PDF template, to remove the OS dependency (C2-2). Make the PDF font self-contained.
4. **Expand test coverage:** OpenAI and Google clients, the full queue pipeline (with `Queue::fake` / sync), `finalizeDocumentStatusIfComplete`, broadcast events and channel auth, admin provider CRUD, frontend component tests, and a CI job on MySQL/Redis.
5. **Multi-language support.** `TranslateText::handle` already takes `$targetLanguage` (`:15`) but ignores it, and prompts are Arabic-only. Parameterize the prompts and add a UI selector. Make pronunciation language-aware (the `lang` is fixed at `en-US`).
6. **Fix realtime payload size:** broadcast only `result_id`/`status` and let the client fetch content (or send a trimmed payload), or raise `REVERB_APP_MAX_MESSAGE_SIZE`.
7. **Usage analytics and cost tracking.** `prompt_tokens`, `completion_tokens` and `processing_time_ms` are already stored per result (`processing_results` migration `:18-20`) but never shown. A dashboard (or use of the unused `system_settings`) could surface cost per model, latency and failure rates. `system_settings` could hold feature flags and limits.
8. **Cost and privacy reduction:** send the file once and reuse it (provider file-upload APIs or prompt caching), optionally extract text locally for DOCX or very large inputs, add a privacy notice and optional redaction.
9. **Per-result retry button and honest document status** (for example `completed_with_errors`); generalize `$expectedTaskCount` (`ProcessDocumentAiJob.php:122`).
10. **Hardening and deployment:** enforce email verification, pagination, exponential backoff with jitter, a `DocumentPolicy`, queue and Reverb health checks, and a container or deployment recipe covering Chrome, Node and fonts.
11. **Configurable quiz** (question count, difficulty) and an export that can omit the answers.
12. **Systematic performance evaluation** against the 30 s NFR across file sizes and providers, to close the gap in B4.

---

## Appendix — Quick facts for the drafter

- Test result: **93 passed, 264 assertions** (68 project-specific, 25 scaffold).
- Live data in the dev DB: 1 document, 3 results; summary 18.5 s, quiz 39.9 s, translation failed (503).
- Active model in the dev DB: Gemini 3.8 Flash (`gemini-3.8-flash`, id 1, `all`, default). The OpenAI provider is inactive.
- Seeder default: Claude Haiku 4.5 (`AiProviderSeeder.php:41-49`). The dev DB differs because an admin switched the active model.
- Only commit: `3d3f246 first commit` (README only).
- Report-quality warning: A5 items 2, 5 and 6 and all of B2's "cannot verify" list depend on the user's account, not on repo evidence.
