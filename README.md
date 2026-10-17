<div align="center">

# 🚀 CollabSpace Enterprise API

**Next-Generation Collaborative Workspace & Project Management Platform**

[![Tests Passing](https://img.shields.io/badge/Tests-100%2F100%20Passing-brightgreen?style=for-the-badge&logo=githubactions&logoColor=white)](file:///c:/laragon/www/CollabSpace-round-13/API_DOCUMENTATION.md)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![JWT Auth](https://img.shields.io/badge/JWT-Protected-black?style=for-the-badge&logo=jsonwebtokens&logoColor=white)](https://jwt.io/)
[![Google Gemini](https://img.shields.io/badge/Gemini%20AI-1.5%20Flash-4285F4?style=for-the-badge&logo=google&logoColor=white)](https://ai.google.dev/)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

<br/>

[API Documentation](file:///c:/laragon/www/CollabSpace-round-13/API_DOCUMENTATION.md) • [Postman Collection](file:///c:/laragon/www/CollabSpace-round-13/CollabSpace_Postman_Collection.json) • [Getting Started](#-getting-started--installation) • [Architecture](#-architecture--design-patterns)

</div>

---

## 📖 About The Project

**CollabSpace** is an enterprise-grade collaborative workspace and project intelligence platform engineered with **Laravel 12** and **PHP 8.4**. Designed from day one to combine agile task workflows, real-time team communication, and multimodal AI automation, CollabSpace gives engineering and product teams complete control over their projects.

### 🌟 Key Highlights
- **Strict Layered Service Pattern:** Clean separation of concerns with thin controllers, dedicated Form Requests, domain-driven Services wrapped in atomic database transactions, and unified API Resources.
- **Multimodal AI with Google Gemini (1.5 Flash):** Automated timestamped meeting transcript summarization (extracting key points, decisions, action items, risks, and next steps) alongside an interactive context-aware personal assistant chatbot.
- **Real-Time Communication:** Live WebSockets broadcasting for channel chatter, threaded replies, @mentions, direct messaging (Inbox), and live read receipts.
- **Zero-Patch Consolidated Database Architecture:** 22 clean, unified migrations establishing relational integrity and high-performance indexing across all platform capabilities.
- **100% Test Coverage:** Comprehensive test suite of **100 Feature Tests** (380 assertions) validating all authentication flows, Kanban rules, aggregations, AI fallbacks, and security boundaries.

---

## ⚡ Core Feature Modules

```
CollabSpace Platform
├── 01. Authentication & Security (JWT, OAuth 2.0, RBAC)
├── 02. Executive Dashboard (KPIs, Trends, Recent Files)
├── 03. Teams & Multi-Project Governance
├── 04. Interactive Kanban & Nested Discussions
├── 05. Real-Time Chat & Direct Messaging (Inbox)
├── 06. Intelligent Meetings & Gemini AI Summaries
├── 07. Analytics Engine & Custom Reports
└── 08. User Profile, Task Checklist & AI Chatbot
```

### 1. 🛡️ Authentication & Access Control
- **JWT Stateless Authentication:** Fast token generation, auto-refresh mechanisms, and blacklist logout.
- **Role-Based Access Control (RBAC):** Dedicated permissions for `Admin`, `ProjectManager`, and `TeamMember`.
- **Social Login:** Google OAuth 2.0 web redirect callback and mobile ID-token verification.
- **Password Recovery:** Asynchronous signed reset link emails via queued background jobs.

### 2. 📊 Executive Dashboard & KPIs
- **Consolidated Overview:** Aggregated project statuses, task breakdowns (pending, in-progress, in-review, completed), and team velocity.
- **Dynamic Timeseries Charts:** Configurable periods (`weekly`, `monthly`, `quarterly`) for task completion trends.
- **Real-Time Widgets:** Active project completion chips, cross-functional workload bars, and recent asset feeds.

### 3. 👥 Teams & Projects Management
- **Roster Management:** Create functional teams (UI/UX, Frontend, Backend, QA, DevOps) and assign members.
- **Project Workspaces:** Allocate multiple cross-functional teams to projects with deadlines and milestones.

### 4. 📋 Kanban Board & Nested Comments
- **Kanban Flow:** Drag-and-drop column transitions (`pending` ➔ `in_progress` ➔ `in_review` ➔ `completed`).
- **Reviewer Workflow:** Assign peer reviewers, track completion percentage, and attach task specifications.
- **Hierarchical Discussions:** Multi-level nested comment threads supporting infinite replies.

### 5. 💬 Real-Time Chat & Direct Messaging
- **Project Channels:** Public and team-scoped rooms with @mentions and file attachments.
- **1-on-1 Direct Messaging (Inbox):** Private threads with unread counters, typing status, and message history.
- **WebSockets Broadcasting:** Dispatches `MessageSent` events across private Pusher/Reverb channels.

### 6. 📅 Meetings & AI Summaries (Google Gemini)
- **Calendar & Invites:** Schedule meetings with meeting URLs and track participant RSVPs (`accepted`, `declined`, `pending`).
- **Timestamped Transcripts:** Store chronological speaker lines (`00:15 - Alex Morgan: ...`).
- **AI Synthesis Pipeline:** One-click automated summary powered by **Gemini 1.5 Flash**, extracting:
  - 📌 *Key Discussion Points*
  - ⚖️ *Decisions Made*
  - ✅ *Action Items & Owners*
  - ⚠️ *Identified Risks*
  - ⏭️ *Next Steps*

### 7. 📈 Reports & Deep Analytics
- **Performance Aggregations:** Task deadline comparisons (on-time vs. overdue vs. early).
- **Workload Distribution:** Activity rates per team member to balance team velocity.
- **AI Report Insights:** Automatic generation of operational recommendations for custom reports.

### 8. 🤖 User Profile, Task Checklist & AI Assistant
- **Profile Customization:** Personal bio, avatar uploads, and availability status (`Available`, `Busy`, `Away`).
- **Task Checklist View:** Clean separation of active **To-Do** vs **Done** tasks with quick toggles.
- **Interactive AI Assistant:** Context-aware chatbot with access to the user's active projects, pending tasks, and scheduled meetings.

---

## 🏛️ Architecture & Design Patterns

CollabSpace strictly enforces the **Layered Service Pattern** to maintain decoupled, testable, and maintainable code:

```
[ HTTP Request ]
       │
       ▼
[ Form Request ] ──────> Validation & Input Sanitation
       │
       ▼
[ Thin Controller ] ───> Coordinates HTTP Flow & Returns ApiResponseTrait Envelope
       │
       ▼
[ Domain Service ] ────> Encapsulates Business Logic & DB Transactions (DB::transaction)
       │
       ▼
[ Eloquent Models ] ───> Type-Hinted Relations, Scopes, JSON Casts, Soft Deletes
       │
       ▼
[ API Resource ] ──────> Standardized, Versioned JSON Transformation
       │
       ▼
[ HTTP Response ]
```

### Design Standards Enforced
- **Single Responsibility Principle (SRP):** Controllers contain no database queries or complex conditionals.
- **Atomic Database Transactions:** Multi-step writes (creating threads + messages, attaching teams) are safely wrapped in `DB::transaction()`.
- **Consistent API Envelope:** Standard JSON response shape across all endpoints:
  ```json
  {
    "success": true,
    "message": "Operation executed successfully",
    "data": { ... }
  }
  ```

---

## 🛠️ Tech Stack & Dependencies

| Layer | Technologies & Tools |
|---|---|
| **Framework** | [Laravel 12](https://laravel.com/) (PHP 8.4) |
| **Authentication** | [tymon/jwt-auth](https://jwt-auth.readthedocs.io/), Laravel Socialite (OAuth 2.0) |
| **AI Integration** | [Google Gemini 1.5 Flash API](https://ai.google.dev/) via HTTP Client |
| **Real-Time** | WebSockets via [Laravel Reverb](https://reverb.laravel.com/) / Pusher Protocol |
| **Database** | MySQL / PostgreSQL / SQLite |
| **Code Style** | [Laravel Pint](https://laravel.com/docs/pint) (PSR-12 / Agent standards) |
| **Testing** | PHPUnit 11 with custom Feature & Unit test suites |

---

## 🚀 Getting Started / Installation

### Prerequisites
- **PHP:** `^8.4` (with `pdo`, `mbstring`, `openssl`, `curl` extensions enabled)
- **Composer:** `^2.x`
- **Database:** MySQL 8+, PostgreSQL 14+, or SQLite 3
- **Google Gemini API Key:** Free key from [Google AI Studio](https://aistudio.google.com/)

---

### Step-by-Step Setup

#### 1. Clone Repository & Install Dependencies
```bash
git clone https://github.com/your-username/CollabSpace.git
cd CollabSpace
composer install
```

#### 2. Environment Setup
Copy the template configuration and generate application security keys:
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

#### 3. Configure Database & AI Service
Edit your `.env` file to configure your database connection and provide your Gemini API key:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=collabspace_db
DB_USERNAME=root
DB_PASSWORD=secret

# Google Gemini API
GEMINI_API_KEY=AIzaSy_your_gemini_api_key_here
GEMINI_MODEL=gemini-1.5-flash
```

#### 4. Run Consolidated Migrations & Seeders
Drop old schema and run the unified migration suite from scratch:
```bash
php artisan migrate:fresh --seed
```

#### 5. Storage Symlink
Link the public storage directory for user avatars and task file attachments:
```bash
php artisan storage:link
```

#### 6. Start the Local Development Server
```bash
php artisan serve
```
The API is now live at: **`http://localhost:8000/api`**

---

## 🧪 Automated Testing

CollabSpace includes a rigorous, end-to-end Feature Test suite covering all 7 development phases:

```bash
php artisan test --compact
```

### Test Suite Output
```json
{"tool":"phpunit","result":"passed","tests":100,"passed":100,"assertions":380,"duration_ms":3417}
```
> **100 / 100 Tests Passing (380 Assertions, 0 Failures, 0 Errors)**

To run code formatting with Laravel Pint:
```bash
vendor/bin/pint --format agent
```

---

## 📚 API Documentation & Postman

- **Full Markdown API Reference:** Comprehensive endpoint documentation with request parameters, schemas, and response examples is available in [API_DOCUMENTATION.md](file:///c:/laragon/www/CollabSpace-round-13/API_DOCUMENTATION.md).
- **Postman Collection (v2.1):** Import [CollabSpace_Postman_Collection.json](file:///c:/laragon/www/CollabSpace-round-13/CollabSpace_Postman_Collection.json) into Postman. It includes pre-configured Bearer token scripts, query parameters, and realistic payloads for all 78 registered routes.

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).

<div align="center">
Made with ❤️ for collaborative engineering teams.
</div>
