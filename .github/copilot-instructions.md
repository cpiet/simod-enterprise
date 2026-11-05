# GitHub Copilot & OpenAI Codex Instructions — SIMOD Enterprise

This document defines core coding standards, architecture constraints, and testing protocols for GitHub Copilot, OpenAI Codex, and Cursor AI.

---

## 1. Core Architecture
- **Framework**: Laravel 12 (PHP 8.2+) with MVC & Service Repository pattern.
- **Frontend**: Vue 3 (Composition API with `<script setup>`), Inertia.js v2.
- **CSS Engine**: Tailwind CSS v4 via `@tailwindcss/vite` without `tailwind.config.js`. Tokens are centralized in `resources/css/app.css`.
- **Database**: MySQL 8.x / MariaDB in production; SQLite in-memory for automated testing.
- **Icons**: `lucide-vue-next`.

---

## 2. Mandatory Rules for AI Agents
1. **Never Modify `phpunit.xml`**: Database testing must stay SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Never switch testing to MySQL.
2. **Always Run Tests**: Always run `php artisan test` and `npm.cmd run build` before completing tasks. All 142 tests must pass.
3. **Multi-Tenant Scoping**: All operational queries (`MediaArticle`, `Issue`, `Task`, `EarlyWarning`, `MediaTopic`) must be scoped by `polda_id`.
4. **Clean URLs (Zero `?polda=` query leaks)**: Never construct links with `?polda=...` query parameters. Use authenticated `POST /tenant/switch` with payload `code` (e.g. `SULBAR`, `ALL`) for secure session isolation.
5. **National Scope Tenant Target**: When in national scope (`$isNational = true` or `selectedCode === 'ALL'`), queries for executive briefings (`latestMediaReport`, `latestSocialReport`) must explicitly target `polda_id` of Divisi Humas Polri (Mabes).
6. **UI Data Parity**: Dropdown counts in Vue components must be computed reactively from loaded props (`props.sampleArticles`, etc.) to guarantee 100% match between dropdown count and displayed cards.
7. **Protected Master Accounts**: Accounts with `id: 1`, `kadivhumas`, `superadmin`, and `cpiet` must never be deleted or deactivated.

---

## 3. Windows / Laragon Tooling
- On Windows PowerShell, always invoke `npm.cmd` (e.g. `npm.cmd run build`, `npm.cmd run dev`).
- Always verify PHP syntax after editing with `php -l <file>`.

For comprehensive system documentation, refer to `AGENTS.md` and `CLAUDE.md`.

