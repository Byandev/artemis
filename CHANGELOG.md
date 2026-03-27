# Changelog

## [v2.0.1] — 2026-03-26

### New Features

- **RMO Management Module**
  - Tabular view of return and delivery orders
  - Status management via dropdown with multiple workflow states (e.g., Pending, Rider OTW, Returning, In Transit)
  - Search functionality for quick order lookup
  - Pagination and sortable columns for efficient data handling

- **Role Management Module**
  - Dedicated module for managing user roles
  - Tabular view displaying roles and their respective descriptions
  - Search capability for locating roles quickly
  - "Add New Role" feature to support creation of custom roles
  - Options for modifying and archiving roles for flexible management

- **Theme Toggle** — Dark mode / Light mode support for improved user experience

### Improvements

- **Sidebar Navigation** — Added RMO Management and Roles module links to the sidebar

---

### Added
- **Employees page** — New page listing Pancake users as Employees with search, sort, and pagination. Uses Spatie Query Builder.
- **Employees sidebar link** — "Employees" added to the main workspace navigation.

### Changed
- **App header** — Removed notification bell. Updated authenticated user display to a premium pill button showing initials and name.
- **Data table empty state** — Replaced plain "No results." text with a centered icon box, heading, and hint text.
- **Analytics caching** — All `AnalyticsController` methods (`index`, `breakdown`, `perPage`, `perShop`, `perUser`) now cache results for 5 minutes with workspace-scoped cache keys to prevent cross-workspace collisions.

### Fixed
- **Sidebar mobile dark mode** — Fixed almost-transparent sidebar background on mobile dark mode by registering missing `--color-sidebar-*` CSS variable mappings in the Tailwind theme.
- **Sidebar mobile border** — Fixed overly bright border on the mobile sidebar sheet in dark mode by applying `border-sidebar-border`.
- **Workspace setup route** — Fixed `MethodNotAllowedHttpException` on workspace setup form by correcting route method from `PUT` to `POST`.

### Database
- **`pancake_customers`** — Removed unique index on `fb_id` column (`2026_03_26_..._drop_unique_fb_id_from_pancake_customers`).
