# Workspace Checklist Readme

Branch: feature/workspace-checklist

<!-- Stash marker: checklist docs touched for local stash grouping. -->

## Overview

This branch adds a new Checklist page under the workspace menu and follows the existing app design system for layout, table styling, and pagination.

## What Was Added

- Sidebar menu item for Checklist in [resources/js/components/app-sidebar.tsx](resources/js/components/app-sidebar.tsx).
- Workspace route for Checklist page in [routes/workspaces.php](routes/workspaces.php).
- New Checklist page implementation in [resources/js/pages/workspaces/checklist/index.tsx](resources/js/pages/workspaces/checklist/index.tsx).

## Checklist Page Behavior

- Page title and header follow existing workspace page style.
- Table uses shared DataTable component with built-in footer and pagination.
- Columns:
	- Title
	- Target
	- Required
	- Actions
- Required badge styling:
	- Yes: highlighted green
	- No: neutral gray
- Actions dropdown contains:
	- View (icon)
	- Edit (icon)
	- Delete (icon)
- Column widths are fixed to avoid layout shift when content is long.
- Title and Target cells truncate long values.
- Required and Actions columns are centered.
- Sorting is enabled for Title and Target using shared sortable header pattern.

## Route

- URL: /workspaces/{workspace}/checklist
- Route name: workspaces.checklist.index

## Notes

- Top loading line is scoped to the Checklist page only.
- No global spinner/progress CSS changes are required.

## Quick Manual Test Plan

- Open Checklist from the sidebar and confirm route loads correctly.
- Verify table footer and pagination render consistently.
- Confirm sorting works on Title and Target.
- Confirm Required and Actions columns remain centered.
- Open the actions menu and verify View/Edit/Delete items and icons.
- Add tasks and confirm fixed-width columns do not shift with long text.