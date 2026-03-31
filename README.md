# Inventory PO Management Readme

Branch: `feature/inventory-po-management`

## Overview

This branch delivers the Inventory Purchased Orders experience in the web app. It includes filtering, pagination, add/edit/delete flows, and UI polish for PO management.

## Recent Frontend Changes

- Extracted shared PO helpers in [resources/js/utils/purchased-orders.ts](resources/js/utils/purchased-orders.ts) for totals math, pagination windowing, empty-state copy, item options, and status labels.
- Centralized HTTP handling in [resources/js/utils/http.ts](resources/js/utils/http.ts) so CSRF headers are always sent (fixes DELETE/GET CSRF mismatch).
- Refactored the main page [resources/js/pages/workspaces/inventory/purchased-orders/index.tsx](resources/js/pages/workspaces/inventory/purchased-orders/index.tsx) to use the shared helpers and slimmer in-page logic.

## Feature Summary

- PO listing with status/date/text filters, pagination, and formatted money/date display.
- Add/Edit modal with validation, auto-total computation, and status selection.
- Delete flow with confirmation dialog.
- Empty states that adapt copy when filters are applied.

## Dev Notes

- API base comes from `VITE_API_BASE_URL`; falls back to localhost during Vite dev.
- CSRF token is read from `<meta name="csrf-token">` or `XSRF-TOKEN` cookie; requests include `X-CSRF-TOKEN` and `credentials: 'include'`.
- Pagination window shows up to five pages, centered around the current page when possible.
- Totals: `cog_amount + delivery_fee`, shown when either field has a value.

## Quick Manual Test Plan

- Load PO list; verify pagination and totals rows update with filters.
- Apply status/text/date filters; confirm empty-state copy switches when filtered.
- Add a PO (ensure total auto-updates); edit an existing PO; delete a PO (CSRF should succeed).
- Toggle status inline; ensure badge updates and persists on refresh.