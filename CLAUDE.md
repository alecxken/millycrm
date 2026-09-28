# CLAUDE.md: WanderLink CRM (BIS541 Assignment 2 prototype)

A working CRM prototype for **WanderLink Travel**, a fictional 8-person travel agency in Nairobi. It supports a university report, so every module maps to a CRM concept, and the screens are meant to be screenshotted. See `README.md` for the feature-to-assignment mapping and the screenshot checklist.

Priorities, in order:
1. Clarity and demonstrability
2. Excellent UX based on design thinking; the users are busy consultants, not IT staff
3. Clean, conventional Laravel code

Do not over-engineer: no microservices, no paid APIs.

## Stack and commands

Laravel 12, PHP 8.3+, Livewire 3 with Volt (class-based page components in `resources/views/livewire/pages`), Tailwind 4 (`@tailwindcss/vite`), Alpine (bundled with Livewire), Chart.js, spatie/laravel-permission, spatie/laravel-activitylog v4, SQLite by default, Pest.

```bash
composer install && npm install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && npm run build && php artisan serve
php artisan test                      # run after every change
php artisan migrate:fresh --seed      # reset demo data
```

Every demo account uses the password `password`: owner@, manager@, consultant@ (plus brian@, mercy@, kevin@), marketing@ and support@wanderlink.test.

## Conventions

- **Business logic goes in `app/Services`.** Volt components and controllers stay thin.
- **Enums** (`app/Enums`) cover every status and type. Each has `label()`, `color()` and `icon()`. Render statuses with `<x-badge :enum="...">`, which always shows colour, icon and text together, never colour alone.
- **RBAC** lives in one place, `app/Support/Permissions.php`, and is re-seeded by `RolesAndPermissionsSeeder`. Protect routes with `can:` middleware. Policies live in `app/Policies`. Row-level visibility for consultants goes through the `visibleTo($user)` scope on Customer, Enquiry, Booking and Task.
- **Navigation** is defined in `app/Support/Navigation.php`. Hide modules a role cannot use; never link to a 403.
- **UI building blocks** (`resources/views/components`):
  - `x-page-header`, `x-card`, `x-stat`, `x-button` (pass `loading="method"` to get a spinner)
  - `x-field` for label, control and inline error
  - `x-slide-over name="..."`, driven by a public `$panel` property; quick actions never change the page
  - `x-empty-state`, which should teach the user what to do next
  - `x-chart :config="ChartPalette::..."`
  - `x-hicon` for Heroicons (not `x-icon`, which blade-icons already registers)
- **Feedback:** use `$this->dispatch('toast', message: ..., undo: [...])` for toasts, `wire:confirm` for destructive actions, and offer undo where you can.
- **Formatting:** use `money($amount, $currency, compact)` for currency (KES by default) and `fdate($date)` for dates such as "26 Sep 2026".
- **N+1 queries:** `Model::preventLazyLoading()` is on outside production, so eager-load everything a view touches.
- **SQLite quirks:**
  - A float bound in raw SQL is sent as a string, so cast it to `int` before comparing against numbers.
  - Keep grouping and date logic in PHP (see `ReportService`) so MySQL stays compatible.
- **Livewire property names** must not collide with `$wire` methods (`call`, `set`, `get`, `watch`, `on`, `dispatch`, `refresh` and so on).
- **Screen-reader text:** `.sr-only` is absolutely positioned. Give its parent `relative` so it cannot widen the page on mobile.
- **Tests:** every screen for every role has a smoke test (`tests/Feature/PageSmokeTest.php`). Add a smoke test for each new screen, and a unit or feature test for each new service rule.
- **Commits:** use conventional commit messages, made in logical steps.

## Design

- Personas: Achieng (a consultant with 30 WhatsApp enquiries), David (the owner, who wants the numbers in 10 seconds) and Grace (support, handling an angry customer with a lost passport).
- Visual style:
  - Quicksand (self-hosted via @fontsource) on an 8px grid. Light weight only at 13px and above.
  - The page floats on a soft brand-tinted light wash. Panels (`.card`) are white with a hairline border and a faint brand-tinted shadow. The sidebar is a floating rail; the top bar is transparent.
  - **Theme is runtime-configurable** (`ThemeService`, Settings → Appearance). Never hard-code brand hex values: use `brand-*` / `sand-*` classes or `var(--brand)` / `var(--accent)`. Shades are derived with `color-mix()` in `resources/css/app.css`, and charts get theme colours through `ChartPalette` tokens (`var(--…)`), which the browser resolves.
  - Radii scale with `--radius-scale`: `rounded-xl` (13px) for controls, `rounded-2xl` (22px) for panels, and pills for chips and segmented controls.
  - Slate is overridden with cool, blue-leaning greys. Use `slate-500` or darker for readable text (AA); reserve `slate-400` for decoration.
  - Dark mode via the `.dark` class, following the system theme by default
  - WCAG AA contrast and visible focus rings
- Copy should sound like colleagues wrote it: warm, specific and plain. Avoid generic "AI" phrasing.
- Check each screen at 375px width and in dark mode.
