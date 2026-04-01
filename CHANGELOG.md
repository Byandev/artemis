# Changelog

## [v2.2.0] — 2026-04-01

### Parcel Journey — New Page & Analytics

- Added **Parcel Journey** entry under the RTS sidebar
- New page at `/rts/parcel-journeys` displaying parcel journey notification templates
- Analytics cards at the top of the page: **Tracked Orders**, **SMS Sent**, **Chat Sent**, **Total Sent**
- Analytics are computed by combining `parcel_journey_notification_logs` (batch) + `parcel_journey_notifications` (real-time) for accurate totals
- **Date range filter** on the analytics section; defaults to current month
- Template list uses paginated DataTable with the standard premium table design
- **Template form redesigned** — premium dialog matching the teams form style; variable chips styled as code tokens with violet accent and hover effect

### Pages — Status Management

- Replaced Archive/Restore with **Active / Inactive** status
- Added `status` column (`enum: active, inactive, default: active`) to `pages` table — run `php artisan migrate`
- Status toggle added to both **Create** and **Edit** page forms (Basic Info section)
- Status badge on the pages list shows **Active** (emerald) / **Inactive** (red)
- `PageController::archive` and `restore` now set `status` instead of soft-deleting

---

## [v2.1.0] — 2026-03-27

### SyncOrder — Full Refactor (SOLID)

- Broke `SyncOrder` job into 5 focused action classes: `UpsertOrderAction`, `SyncOrderItemsAction`, `SyncShippingAddressAction`, `SyncParcelTrackingAction`, `SyncPhoneNumberReportsAction`
- `SyncOrder` is now a thin orchestrator using Laravel method injection
- Added `JourneyUpdateNormalizer` — fixes `update_at` typo, resolves canonical status names, extracts rider name/mobile from `【...】` bracket notation
- Added `OrderTimestampResolver` and `MessageRenderer` support classes
- Parcel journey notification logic extracted into `ParcelJourneyNotifier` + Strategy pattern handlers: `DepartureHandler`, `ArrivalHandler`, `OnDeliveryHandler`

### Parcel Journey — Logic Improvements

- Save only journeys where status is `On Delivery` or `created_at` is today
- Stop saving journey entries that succeed a `Return Register` status
- `isNotifiable` only triggers if `created_at` is today
- Rider name and mobile are now parsed by the normalizer and saved directly on the `parcel_journeys` record
- `OnDeliveryHandler` reads `rider_name`/`rider_mobile` from the saved journey instead of re-parsing the note

### RTS Analytics — New Cards & Decoupled Architecture

- Each analytics card is now a self-contained component with its own fetch/state
- **New: By Product** — RTS breakdown by product/item name with sortable DataTable
- **New: By Rider** — RTS breakdown by rider name, sourced from the latest `On Delivery` parcel journey per order
- Price, Delivery Attempts, and Cx RTS cards default to chart view
- Product and Rider breakdowns use DataTable (same pattern as Location card)

### Pages List

- `is_sync_logic_updated` flag now shown inline in the Last Sync column as an **Updated** / **Legacy** badge

### RMO Management — Refactor & Design

- `ForDeliveryController` refactored: query building extracted to `ForDeliveryQuery`, stats to `ForDeliveryStatsService`
- Parcel status badge shows an animated pulsing dot when status is `out_for_delivery`
- Order status picker dropdown now shows color-coded pill badges for each option

---

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
