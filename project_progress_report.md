# Project Progress & Status Report
**Project:** Chapter Summary Tool  
**Report Date:** 2026-10-01  
**Prepared By:** Lead Software Engineer  
**Audience:** External System Architect  

---

## Table of Contents
1. [Infrastructure & Stack Status](#1-infrastructure--stack-status)
2. [Database Schema](#2-database-schema)
3. [Backend & AI Integrations](#3-backend--ai-integrations)
4. [Frontend & UI/UX](#4-frontend--uiux)
5. [Pending Tasks & Blockers](#5-pending-tasks--blockers)

---

## 1. Infrastructure & Stack Status

### 1.1 Core Framework

| Component | Version / Config | Status |
|---|---|---|
| **Laravel** | `^12.0` | ✅ Active |
| **PHP** | `^8.2` | ✅ Active |
| **Inertia.js (Laravel adapter)** | `^2.0` | ✅ Configured |
| **Inertia.js (React adapter)** | `^2.0` | ✅ Configured |
| **React** | `^18.2.0` | ✅ Active |
| **TypeScript** | `^5.0.2` | ✅ Strict compilation via `tsc && vite build` |
| **Vite** | `^7.0.7` | ✅ Configured via `laravel-vite-plugin ^2.0.0` |
| **Tailwind CSS** | `^3.2.1` | ✅ Configured with custom `brand` color palette and dark mode |

### 1.2 Database

- **Configured Driver:** MySQL (`DB_CONNECTION=mysql`)  
- **Host/Port:** `127.0.0.1:3306`, database `chapter_summary_tool`  
- **Current Dev State:** A `database.sqlite` file is present in `database/`, indicating SQLite was used during initial development. The `.env` now points to MySQL for the target environment.  
- **Session Driver:** `database`  
- **Cache Store:** `database`  

> [!WARNING]
> There is a **configuration mismatch**: the `.env` specifies `DB_CONNECTION=mysql`, but a `database.sqlite` file exists, which is typically left over from SQLite-first development. The System Architect should confirm the target DB engine and ensure the MySQL server is provisioned and seeded before a deployment handoff.

### 1.3 Redis

- **Client:** `predis ^3.6` (PHP client)
- **Host/Port:** `127.0.0.1:6379`, no password set
- **Queue Connection:** `QUEUE_CONNECTION=redis` — all background jobs run through Redis queues
- **Cache Store:** Currently set to `database` (Redis is *not* used as the cache store yet, only as the queue backend)

> [!NOTE]
> Redis is exclusively the **queue backend**. Cache reads/writes go to the `database` driver. This is an intentional dev-environment choice. For production, `CACHE_STORE=redis` should be evaluated.

### 1.4 Laravel Reverb (WebSockets)

- **Package:** `laravel/reverb ^1.12`
- **Broadcast Connection:** `BROADCAST_CONNECTION=reverb`
- **App ID / Key / Secret:** Fully populated in `.env` (local dev values)
- **Host:** `localhost`, Port `6001`, Scheme `http`
- **Vite vars exposed:** `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`
- **Frontend Echo client:** Configured in `resources/js/lib/echo.ts` using `laravel-echo ^1.16.1` + `pusher-js ^8.4.0-rc2` (Pusher-compatible)
- **Status:** ✅ Fully wired; private channel auth is handled by standard Laravel auth

### 1.5 Laravel Sanctum

- **Package:** `laravel/sanctum ^4.0`
- **Usage:** Installed as part of the Breeze scaffold for session-based auth; not used for token-based SPA auth in this project.

### 1.6 Dev Tooling

The `composer.json` `dev` script runs four concurrently managed processes:
```
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0
npm run dev
```
A `run-dev.bat` convenience script also exists at the project root.

---

## 2. Database Schema

### 2.1 Migrations — Inventory

| # | Migration File | Table Created | Status |
|---|---|---|---|
| 1 | `0001_01_01_000000_create_users_table` | `users` | ✅ Run |
| 2 | `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` | ✅ Run |
| 3 | `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` | ✅ Run |
| 4 | `2026_01_01_000001_add_role_to_users_table` | Alters `users` | ✅ Run |
| 5 | `2026_01_01_000002_create_ai_providers_table` | `ai_providers` | ✅ Run |
| 6 | `2026_01_01_000003_create_ai_models_table` | `ai_models` | ✅ Run |
| 7 | `2026_01_01_000004_create_documents_table` | `documents` | ✅ Run |
| 8 | `2026_01_01_000005_create_processing_results_table` | `processing_results` | ✅ Run |
| 9 | `2026_01_01_000006_create_system_settings_table` | `system_settings` | ✅ Run |

### 2.2 Detailed Schema Definitions

#### `ai_providers`
| Column | Type | Notes |
|---|---|---|
| `id` | `bigint PK` | Auto-increment |
| `name` | `string` | Display name (e.g. "Google") |
| `slug` | `string UNIQUE` | Machine slug (e.g. "google", "openai") |
| `base_url` | `string NULL` | Optional override; defaults hardcoded in client |
| `api_key_encrypted` | `text NULL` | Laravel `encrypt()`-wrapped API key |
| `is_active` | `boolean` | Default `true` |
| `timestamps` | — | `created_at`, `updated_at` |

#### `ai_models`
| Column | Type | Notes |
|---|---|---|
| `id` | `bigint PK` | Auto-increment |
| `provider_id` | `FK → ai_providers.id` | `CASCADE ON DELETE` |
| `display_name` | `string` | Human-readable name |
| `api_model_code` | `string` | Exact code passed to provider API (e.g. `gemini-1.5-flash`) |
| `context_window` | `unsigned int` | Default 8192 tokens |
| `task_specialty` | `string` | Enum: `summary`, `translation`, `quiz` |
| `is_default` | `boolean` | Default `false`; one per task specialty |
| `timestamps` | — | — |
| **Index** | `(task_specialty, is_default)` | Optimizes factory lookup |

#### `documents`
| Column | Type | Notes |
|---|---|---|
| `id` | `uuid PK` | `HasUuids` trait |
| `user_id` | `FK → users.id` | `CASCADE ON DELETE` |
| `original_name` | `string` | Client-provided filename |
| `file_url` | `string` | Relative path on `local` disk |
| `mime_type` | `string NULL` | e.g. `application/pdf` |
| `file_size` | `unsigned bigint` | Bytes |
| `extracted_text` | `longText NULL` | Raw text after extraction job |
| `status` | `string` | `uploaded` → `extracting` → `queued` → `processing` → `completed`/`failed` |
| `failure_reason` | `text NULL` | Human-readable error message |
| `timestamps` | — | — |
| **Index** | `(status)` | — |

#### `processing_results`
| Column | Type | Notes |
|---|---|---|
| `id` | `uuid PK` | `HasUuids` trait |
| `document_id` | `FK (uuid) → documents.id` | `CASCADE ON DELETE` |
| `model_id` | `FK NULL → ai_models.id` | `NULL ON DELETE` (model may be deleted later) |
| `result_type` | `string` | `summary`, `translation`, or `quiz` |
| `status` | `string` | `pending`, `processing`, `completed`, `failed` |
| `content_json` | `json NULL` | Structured output; cast to `array` in model |
| `prompt_tokens` | `unsigned int NULL` | Token usage tracking |
| `completion_tokens` | `unsigned int NULL` | Token usage tracking |
| `processing_time_ms` | `unsigned int NULL` | Wall-clock duration |
| `error_message` | `text NULL` | Failure message from AI provider |
| `timestamps` | — | — |
| **Index** | `(document_id, result_type)` | — |

#### `system_settings`
| Column | Type | Notes |
|---|---|---|
| `key` | `string` | Setting identifier |
| `value` | `string` | Raw stored value |
| `type` | `string` | `string`, `integer`, `boolean`, `json` |
| `description` | `text NULL` | — |

### 2.3 Models & Relationships

| Model | Key Traits | Relationships |
|---|---|---|
| `User` | — | `hasMany(Document)` |
| `AiProvider` | — | `hasMany(AiModel)`, encrypted `api_key` accessor/mutator |
| `AiModel` | — | `belongsTo(AiProvider)` |
| `Document` | `HasUuids`, `HasFactory` | `belongsTo(User)`, `hasMany(ProcessingResult)`, `resultFor(type)` helper |
| `ProcessingResult` | `HasUuids`, `HasFactory` | `belongsTo(Document)`, `belongsTo(AiModel)` |
| `SystemSetting` | — | Static `get()` / `set()` with `Cache::rememberForever` |

### 2.4 Seeders

- **`AiProviderSeeder`** — Seeds Google and OpenAI providers with optional API keys from `GOOGLE_AI_API_KEY` / `OPENAI_API_KEY` env vars. Also seeds default models per task specialty. The env comment explicitly notes these are local dev fallbacks only; production keys should be set via the Admin panel.
- **`DatabaseSeeder`** — Calls `AiProviderSeeder` and creates a default admin user.

---

## 3. Backend & AI Integrations

### 3.1 AI Provider Factory (`AiProviderFactory`)

**File:** `app/Services/Ai/AiProviderFactory.php`

The factory is the central dispatch point for all AI calls. It has three responsibilities:

1. **`clientFor(AiProvider $provider)`** — Resolves the correct client class from an internal registry (`slug → class-string`). Throws `AiProviderException` for unknown slugs. New providers are added by inserting into the `$clients` array.

2. **`resolveModelFor(string $taskSpecialty)`** — Queries `ai_models` for the first record matching `task_specialty`, `is_default = true`, and whose provider has `is_active = true`. Throws `AiProviderException` if unconfigured.

3. **`chat(string $taskSpecialty, ...)`** — Orchestrates the full pipeline: resolve model → check API key → instantiate client → call `chat()` → return `['model' => AiModel, 'response' => AiChatResponse]`.

**Status:** ✅ Fully implemented and extensible.

### 3.2 AI Client Contract & Implementations

**Contract:** `app/Services/Ai/Contracts/AiProviderClient.php`  
```php
interface AiProviderClient {
    public function chat(string $systemPrompt, string $userPrompt, AiModel $model, string $apiKey, bool $jsonMode = false): AiChatResponse;
}
```

#### Google AI Client (`GoogleAiClient`)
- **Endpoint:** `https://generativelanguage.googleapis.com/v1beta/models/{model_code}:generateContent`
- **Auth:** API key passed as query parameter `?key=`
- **JSON Mode:** Sets `generationConfig.responseMimeType = 'application/json'`
- **Temperature:** Hardcoded `0.4`
- **Timeout:** `120s`
- **Token tracking:** Reads `usageMetadata.promptTokenCount` and `candidatesTokenCount`
- **Status:** ✅ Fully implemented

#### OpenAI Client (`OpenAiClient`)
- **Endpoint:** `https://api.openai.com/v1/chat/completions`
- **Auth:** Bearer token via `withToken($apiKey)`
- **JSON Mode:** Sets `response_format = ['type' => 'json_object']`
- **Temperature:** Hardcoded `0.4`
- **Timeout:** `120s`
- **Token tracking:** Reads `usage.prompt_tokens` and `usage.completion_tokens`
- **Status:** ✅ Fully implemented

**`AiChatResponse` DTO:** `readonly` class with `content`, `promptTokens`, `completionTokens`.

### 3.3 AI Task Services

All three task services use constructor injection of `AiProviderFactory` and are resolved by Laravel's DI container.

| Service | Specialty | JSON Mode | Prompt Strategy | Status |
|---|---|---|---|---|
| `SummarizeText` | `summary` | No | System: academic assistant; User: "Summarize for exam review"; language-aware | ✅ Done |
| `TranslateText` | `translation` | No | System: professional academic translator to Arabic; User: raw chapter text | ✅ Done |
| `GenerateQuiz` | `quiz` | **Yes** | System: MCQ schema (JSON) with validation; configurable `$questionCount = 5` | ✅ Done |

`GenerateQuiz` includes a defensive `decode()` method that strips markdown fences, `json_decode`s, and validates the shape of each question before returning.

### 3.4 Text Extraction (`TextExtractorService`)

**File:** `app/Services/TextExtraction/TextExtractorService.php`

| File Type | Library | Method |
|---|---|---|
| `application/pdf` | `smalot/pdfparser ^2.12` | `PdfParser::parseFile()` → `getText()` |
| `application/vnd.openxmlformats...` (DOCX) | `phpoffice/phpword ^1.4` | `IOFactory::load()` → recursive `getElements()` traversal |
| `application/msword` (legacy DOC) | `phpoffice/phpword ^1.4` | Same as DOCX |
| Other MIME | — | Throws `RuntimeException` |

A `normalize()` post-processor collapses whitespace and excess blank lines. Empty text after extraction throws a `RuntimeException`, preventing useless AI calls.

**Status:** ✅ Implemented; covers all advertised file types.

> [!NOTE]
> The DOCX text extractor uses a recursive element traversal that calls `getText()` on leaf nodes. Complex DOCX structures (nested tables, text boxes, SDT fields) may not extract fully. This is a known limitation of PhpWord's IO layer.

### 3.5 Background Jobs & Queue Pipeline

The system uses a **two-stage async pipeline** dispatched to Redis queues.

```
[User uploads file]
        │
        ▼
 DocumentController::store()
  ─ Creates Document (status: uploaded)
  ─ dispatch(ExtractDocumentTextJob)
        │
        ▼
 ExtractDocumentTextJob
  ─ status → extracting  [broadcasts DocumentStatusUpdated]
  ─ TextExtractorService::extract()
  ─ status → queued       [broadcasts DocumentStatusUpdated]
  ─ Creates 3× ProcessingResult (pending)
  ─ dispatch(ProcessDocumentAiJob) × 3
  ─ status → processing   [broadcasts DocumentStatusUpdated]
        │
        ├─── ProcessDocumentAiJob (summary)
        ├─── ProcessDocumentAiJob (translation)
        └─── ProcessDocumentAiJob (quiz)
               │
               ▼
         Each job:
          ─ result.status → processing
          ─ Calls AI task service
          ─ result.status → completed/failed  [broadcasts ProcessingResultReady]
          ─ Checks if all 3 results done → updates Document.status → completed/failed
                                         [broadcasts DocumentStatusUpdated]
```

**Job configuration:**

| Job | `$tries` | `$timeout` | `$backoff` |
|---|---|---|---|
| `ExtractDocumentTextJob` | 2 | 300s | — |
| `ProcessDocumentAiJob` | 2 | 180s | 15s |

**Status:** ✅ Fully implemented end-to-end with error isolation per result.

> [!NOTE]
> `$expectedTaskCount = 3` is **hardcoded** in `ProcessDocumentAiJob::finalizeDocumentStatusIfComplete()`. If a new task type is added, this constant must be updated manually.

### 3.6 Broadcast Events (Reverb / WebSockets)

| Event Class | Channel | Event Name (wire) | Payload |
|---|---|---|---|
| `DocumentStatusUpdated` | `private documents.{document_id}` | `.document.status.updated` | `document_id`, `status`, `failure_reason` |
| `ProcessingResultReady` | `private documents.{document_id}` | `.document.result.ready` | `result_id`, `document_id`, `result_type`, `status`, `content`, `error_message` |

Both implement `ShouldBroadcast` (synchronous broadcast during the job). **Status:** ✅ Fully implemented.

### 3.7 HTTP Controllers — Admin Panel

| Controller | Route(s) | Actions |
|---|---|---|
| `AiProviderController` | `GET admin/ai-routing`, `POST /ai-providers`, `PUT /ai-providers/{id}`, `DELETE /ai-providers/{id}` | `index`, `store`, `update`, `destroy` |
| `AiModelController` | `POST /ai-models`, `PUT /ai-models/{id}`, `DELETE /ai-models/{id}` | `store`, `update`, `destroy` |

The admin routes are guarded by `['auth', 'verified', 'admin']` middleware.  
`EnsureUserIsAdmin` middleware is the `admin` gate — checks `User::isAdmin()` (role-based).

**Status:** ✅ Full CRUD for both providers and models is implemented.

### 3.8 Middleware Stack

| Middleware | Purpose |
|---|---|
| `HandleInertiaRequests` | Shares `auth.user`, `locale`, `flash` as Inertia shared props |
| `SetLocale` | Sets `App::setLocale()` from user's stored `locale` column |
| `EnsureUserIsAdmin` | Aborts 403 if `user->role !== 'admin'` |

---

## 4. Frontend & UI/UX

### 4.1 Design System

**Tailwind configuration** (`tailwind.config.js`):
- **Dark mode:** `class` strategy — toggled via `ThemeToggle` component
- **Brand color:** Custom emerald-family palette (`brand-50` → `brand-900`) mapped to Tailwind's `colors.brand.*`
- **Custom animations:** `float` (vertical bob, 4s ease-in-out) and `spin-slow` (12s spin) — used in landing page hero
- **Font:** `Figtree` as primary sans-serif, with `var(--font-sans)` CSS variable override capability
- **Plugin:** `@tailwindcss/forms` for styled form resets

### 4.2 Landing Page (`Pages/Welcome.tsx`)

**Size:** 14,621 bytes — a self-contained, feature-rich page.

**Component breakdown (all in `Components/landing/`):**

| Component | Purpose |
|---|---|
| `NeuralBackground.tsx` | Animated SVG/CSS neural network particle background |
| `HeroMockup.tsx` | Floating animated UI mockup in hero section |
| `AiBotBadge.tsx` | "Powered by AI" badge with animation |
| `FeatureCard.tsx` | Feature highlight card with icon |
| `StepCard.tsx` | Numbered step card for "how it works" |

**Sections present in Welcome.tsx:**
- Animated hero with CTA buttons (Login / Get Started)
- Features grid (summary, translation, quiz, pronunciation)
- "How It Works" 3-step flow
- Tech stack/trust badges
- Footer with i18n language switcher

**Status:** ✅ Fully implemented.

### 4.3 Authentication Pages

Scaffolded via **Laravel Breeze**. Located in `Pages/Auth/`. All standard pages present:
- `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`, `ConfirmPassword`

**Status:** ✅ Complete (Breeze scaffold, unmodified).

### 4.4 Document Workspace

#### `Pages/Documents/Index.tsx` — Dashboard / Upload
- Stat cards: Total, Completed, Processing document counts
- **Drag-and-drop upload zone** with:
  - Click-to-select fallback
  - Real-time Zod validation (file type + size) on drop/select
  - Upload progress bar (driven by Inertia `onProgress`)
  - i18n error message keys resolved via `t()`
- Documents list with: filename, file size, date, `StatusBadge`, view/delete actions
- **Status:** ✅ Fully implemented.

#### `Pages/Documents/Show.tsx` — Document Workspace (Tabs)
- Real-time state management via `useDocumentChannel` hook (WebSocket)
- Status badge in header, failure reason banner
- **4-tab navigation:**

| Tab | Component | Backend Result Type | Notes |
|---|---|---|---|
| Summary | `SummaryTab` | `summary` | Displays AI-generated summary text |
| Translation | `TranslationTab` | `translation` | Forced `dir="rtl"` + `text-end` layout |
| Quiz | `QuizTab` | `quiz` | Full interactive MCQ with score + retry |
| Pronunciation | `PronunciationTab` | *(client-side only)* | Extracts capitalized terms from summary text; uses Web Speech API |

- **Status:** ✅ Fully implemented.

#### Tab Component Details

**`SummaryTab`** — Wraps in `<ResultState>`; displays `content.text` field.  
**`TranslationTab`** — Minimal; forces RTL layout on content area. Hard-codes Arabic direction (translation is always to Arabic per `TranslateText.php`). Wraps in `<ResultState>`.  
**`QuizTab`** — Full interactive quiz engine:
  - Parses `result.content` through `quizContentSchema` (Zod) for runtime type safety
  - Per-question selection state via `Record<number, number>` map
  - Submit reveals correct/incorrect highlighting + explanation text
  - Score banner + "Try Again" button
  - `OPTION_LETTERS` supports up to 6 options (A–F)
  
**`PronunciationTab`** — **Fully client-side, no AI call:**
  - Regex extracts capitalized terms ≥ 4 chars from summary text
  - Filters against a stopword list
  - Caps at 30 terms
  - Each term is a button that calls `window.speechSynthesis.speak()` with `lang='en-US'`
  - Graceful `!supported` message for browsers without Web Speech API

### 4.5 Admin Panel (`Pages/Admin/AiRouting.tsx`)

**Size:** 20,886 bytes — the largest frontend file.

- Stat cards: Total providers, active providers, total models
- Provider list with per-provider model sub-list
- **Provider actions:** Inline `Switch` to toggle `is_active` (fires `router.put` immediately), Edit modal, Delete with `window.confirm`
- **Model actions:** Edit modal, Delete with `window.confirm`
- Task specialty pills with color-coded Tailwind classes: blue (summary), purple (translation), amber (quiz)
- Context window formatted as `K` / `M` suffix
- `is_default` badge with star icon
- **Status:** ✅ Fully implemented.

### 4.6 Shared UI Component Library (`Components/ui/`)

A custom component library built in this project:

| Component | Purpose |
|---|---|
| `Button` | Variants: `primary`, `secondary`, `ghost`, `danger`; sizes `sm`, `md` |
| `Card` | Surface wrapper with optional `padded` prop |
| `IconCircle` | Circular icon container with `brand`/`gray` tones and `sm`/`md`/`lg` sizes |
| `Modal` | Headless UI `<Dialog>` wrapper with backdrop |
| `StatCard` | Metric card with icon + value + label |
| `StatusBadge` | Pill badge for all 6 document statuses with appropriate colors |
| `Switch` | Headless UI toggle switch |

**Status:** ✅ All components implemented.

### 4.7 Zod Validation Schemas (`lib/schemas.ts`)

All form validation uses Zod with i18n-compatible error message keys:

| Schema | Validates | Key Rules |
|---|---|---|
| `documentUploadSchema` | File upload | `instanceof File`, MIME type whitelist (PDF/DOCX/DOC), max 20MB |
| `aiProviderSchema` | Provider form | name, slug (regex `/^[a-z0-9_-]+$/i`), optional URL + key, is_active |
| `aiModelSchema` | Model form | provider_id, display_name, api_model_code, context_window (1024–5M), task_specialty enum, is_default |
| `quizContentSchema` | Quiz result parsing | Array of questions with `question`, `options[]`, `correct_index`, optional `explanation` |
| `summaryContentSchema` | Summary result parsing | `{ text: string }` |

**Status:** ✅ Fully implemented; used on both the upload form (client validation) and inside `QuizTab` (runtime content parsing).

### 4.8 i18n — Arabic / English with RTL/LTR Support

**Library:** `i18next ^23.15.1` + `react-i18next ^15.0.2` + `i18next-browser-languagedetector ^8.0.0`

**Configuration (`i18n/index.ts`):**
- Supported locales: `en`, `ar` (typed `SUPPORTED_LOCALES` const)
- Detection order: `localStorage` → `navigator` (browser language)
- Cache: `localStorage` key `locale`
- Fallback: `en`
- On `languageChanged`: calls `applyDocumentDirection()` → sets `<html lang="">` and `<html dir="rtl/ltr">`

**Translation files:**
| File | Size | Coverage |
|---|---|---|
| `i18n/locales/en.json` | 10,068 bytes | Full English |
| `i18n/locales/ar.json` | 13,965 bytes | Full Arabic (larger due to Arabic script) |

**Locale persistence:** `LocaleController` (`POST /locale`) persists the user's choice to the `users.locale` column. The `HandleInertiaRequests` middleware shares `locale` as an Inertia prop. The `SetLocale` middleware applies it server-side on subsequent requests.

**RTL note:** `TranslationTab` hardcodes `dir="rtl"` on the content div (translation output is always Arabic). The document-level direction is driven by the i18n language change listener.

**Status:** ✅ Fully implemented, both locale files present, direction switching functional.

### 4.9 Authenticated Layout (`AuthenticatedLayout.tsx`)

**Size:** 12,290 bytes. Features:
- Top navigation bar with app logo, nav links, user dropdown (profile, logout)
- Responsive mobile hamburger menu
- Dark mode toggle (`ThemeToggle` component using `useTheme` hook with `localStorage` persistence)
- Language switcher (`LanguageSwitcher` component)
- Admin nav link conditionally shown if `auth.user.role === 'admin'`
- Flash message banner (success/error)

**Status:** ✅ Fully implemented.

---

## 5. Pending Tasks & Blockers

### 5.1 Critical Blockers

| # | Issue | Severity | Details |
|---|---|---|---|
| B1 | **No real API keys configured** | 🔴 Critical | `GOOGLE_AI_API_KEY` and `OPENAI_API_KEY` are blank in `.env`. The entire AI pipeline will throw `AiProviderException("Provider [...] has no API key configured")` until keys are set in the Admin panel or seeded. |
| B2 | **MySQL server not confirmed running** | 🔴 Critical | `.env` targets MySQL but the SQLite file present suggests the dev environment may not have MySQL configured. All migrations must be re-run against MySQL before QA. |
| B3 | **Redis server dependency** | 🟠 High | `QUEUE_CONNECTION=redis` means the job pipeline is non-functional without a running Redis instance. No fallback queue driver is configured. |
| B4 | **Reverb server must be started manually** | 🟠 High | WebSocket real-time updates on `Documents/Show` require `php artisan reverb:start`. It is not part of the `composer dev` script (only queue:listen is). |

> [!CAUTION]
> Items B1 and B3 will cause **silent failures** — documents will be uploaded and jobs dispatched, but AI results will never populate, and the UI will remain indefinitely in "processing" state.

### 5.2 Incomplete Features

| # | Feature | Status | Notes |
|---|---|---|---|
| F1 | **`TranslationTab` is a stub** | 🟠 Incomplete | The component renders `dir="rtl"` text but has zero loading/empty states, no i18n labels inside the tab itself, and no Zod parsing of `content`. The `SummaryTab` and `QuizTab` both use the `<ResultState>` wrapper for pending/failed/processing states; `TranslationTab` uses it but the content itself has no null/empty guard beyond `?? ''`. |
| F2 | **`SummaryTab` has no i18n keys for its own content** | 🟡 Minor | The Summary tab is minimal (17 bytes of rendered logic). While it uses `<ResultState>`, it doesn't display the model name attribution that is available in the result data. |
| F3 | **Pronunciation tab is English-only** | 🟡 Minor | `PronunciationTab` regex `/\b[A-Z][a-zA-Z-]{3,}\b/g` and `lang='en-US'` speech are hardcoded for English. Arabic terms from the translation tab are inaccessible to it. |
| F4 | **`system_settings` table has no UI** | 🟡 Minor | `SystemSetting` model is fully built with cache, but no Admin panel page exposes it. The table is effectively unused at runtime. |
| F5 | **No token usage / cost visibility** | 🟡 Minor | `prompt_tokens`, `completion_tokens`, and `processing_time_ms` are stored in `processing_results` but never surfaced in any frontend view. |
| F6 | **`$expectedTaskCount = 3` is hardcoded** | 🟡 Minor | `ProcessDocumentAiJob::finalizeDocumentStatusIfComplete()` will never correctly finalize a document if the task count changes (e.g., adding a `pronunciation` AI task). |
| F7 | **No re-processing / retry UI** | 🟡 Minor | If an individual `ProcessingResult` fails, there is no UI affordance to retry just that task. The user would need to re-upload the document. |
| F8 | **No pagination on document list** | 🟡 Minor | `DocumentController::index()` loads all documents for the user with `->get()` (no pagination). This will degrade with large document sets. |

### 5.3 Architectural Debt / Design Notes

| # | Item | Priority |
|---|---|---|
| A1 | **`is_default` uniqueness not enforced at DB level** | Medium — Multiple `is_default = true` rows for the same `task_specialty` can exist; the factory only takes `first()`. A unique partial index on `(task_specialty) WHERE is_default = true` (supported in PostgreSQL/SQLite but not MySQL) would enforce this. A `before/afterSave` model event or `updating` observer is a viable MySQL-compatible alternative. |
| A2 | **API key stored with Laravel `encrypt()` (symmetric)** | Medium — Uses `APP_KEY` for encryption. Rotating the app key will invalidate all stored API keys. For production, consider a key-management strategy. |
| A3 | **No authorization policy (Policy class)** | Low — `DocumentController` uses inline `abort_unless()` for ownership checks. This will need to be formalized into a `DocumentPolicy` if ACL complexity grows. |
| A4 | **No feature flag for queue driver** | Low — If Redis is unavailable, the whole upload flow silently enqueues to Redis and fails. A `sync` queue fallback or health check on boot would improve resilience. |
| A5 | **Hard dependency on `window.confirm()`** | Low — Delete confirmations in `AiRouting.tsx` and `Documents/Index.tsx` use `window.confirm()`, which is blocked in many iframe contexts and cannot be styled/localized. A custom confirmation modal would improve UX and consistency. |

### 5.4 Missing Tests

There are no application-specific tests in the `tests/` directory beyond the default Laravel skeleton (`Feature/Auth/*`, `Unit/ExampleTest`). The following areas should have test coverage before production:

- `TextExtractorService` — Unit tests with fixture PDF/DOCX files
- `AiProviderFactory::chat()` — With mocked HTTP responses (success + API error paths)
- `GenerateQuiz::decode()` — Tests for malformed JSON, missing keys, stripped markdown fences
- `ExtractDocumentTextJob` + `ProcessDocumentAiJob` — Queue fake integration tests
- `DocumentController` — Feature tests for ownership enforcement (403 on cross-user access)

---

*Report generated by automated codebase audit — 2026-10-01*
