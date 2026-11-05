# Copilot / Codex / Cursor Instructions — SIMOD Enterprise

The full, authoritative rules live in [`AGENTS.md`](../AGENTS.md) (in Indonesian). Code map and verified numbers: [`docs/22-peta-kode-dan-sumber-kebenaran.md`](../docs/22-peta-kode-dan-sumber-kebenaran.md). If this file disagrees with `AGENTS.md`, `AGENTS.md` wins; if docs disagree with code, the code wins.

Project language is **Indonesian** (UI text, errors, comments, docs, commit messages).

## Stack
Laravel 12 (PHP 8.2), Inertia v3 adapters, Vue 3 `<script setup>`, Vite 6, Tailwind CSS v4 via `@tailwindcss/vite` (no `tailwind.config.js`; tokens in `resources/css/app.css`), `lucide-vue-next`. MySQL/MariaDB locally (Laragon), SQLite in-memory for tests.

## Mandatory rules
1. **Never modify `phpunit.xml`.** Tests must stay on SQLite `:memory:`.
2. Before finishing: `php artisan test` (all green) and `npm.cmd run build` (use `npm.cmd` in PowerShell). Add feature tests in `tests/Feature`; never call real Gemini/Apify/Google APIs in tests (`Http::fake()`).
3. **Tenant scoping:** every operational query is filtered by `polda_id`. Get the tenant only via `[$code, $polda, $poldaId] = $this->resolveActiveTenant($request)`. Never read `polda`/`polda_id`/`polda_code` from client input; overrides only via `requestedPoldaOverride()` / `scopedPoldaId()` (Super Admin only).
4. **No `?polda=` links.** Switch tenant only with `POST /tenant/switch` and payload `{ "code": "SULBAR" }` (`ALL` = national/Divhumas).
5. On mutations call `authorizeTenantRecord($request, $record)` and force `polda_id` to the user's own Polda for regional users.
6. Every module route needs `->middleware('module:<key>')` (keys from `RolePermission::defaultModules()`, `|` = any-of). All web routes sit under `auth` + `idle`.
7. Protected accounts (in code): `id = 1`, `kadivhumas`, `superadmin`; users cannot delete or deactivate themselves.
8. Frontend: support both `.dark` and `.light` themes; compute dropdown counts with `computed()` from loaded data; never show fake/default numbers or stock images; use `<Link>` for internal and `target="_blank" rel="noopener noreferrer"` for external links.
9. AI calls triggered by users go through `AiQuota`; reuse cached analysis when source data is unchanged.
10. RSS: per-source fair quota (no global `array_slice` after sorting); bookmarked articles are never pruned.
11. Never commit secrets, passwords or `.env` content. Update `docs/22` and the related module doc in the same commit as behavior changes. Use Conventional Commits.
