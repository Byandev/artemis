# Workspace Checklist Readme

Branch: feature/workspace-checklist

## Overview

Checklist is now workspace-scoped and database-backed.
Each checklist item belongs to a workspace and is only accessible to users who are members of that workspace.

The feature now includes:
1. Checklist list page (CRUD)
2. Checklist view page (progress-focused UI)
3. Automated completion checks based on workspace data (Pages/Shops)

## Functional Changes

1. Added backend persistence for checklist items.
2. Added workspace-restricted controller endpoints for checklist index/view/create/update/delete.
3. Replaced local frontend checklist mock data with backend paginated data.
4. Enabled View action from checklist table.
5. Added a dedicated checklist view page and replaced the header action with Go Back.

## Data Model

Table: workspace_checklists

Columns:
1. id
2. workspace_id (FK -> workspaces.id)
3. created_by (FK -> users.id, nullable)
4. title
5. target (enum: Shop, Page)
6. required (boolean)
7. created_at
8. updated_at

Indexes:
1. workspace_checklists_workspace_target_idx (workspace_id, target)
2. workspace_checklists_workspace_required_idx (workspace_id, required)

## Access Control

Checklist endpoints validate workspace membership:
1. User must be a member of the workspace.
2. Checklist records must belong to the current workspace.

If either check fails, request is denied with 403.

## Automated Completion Logic

Checklist view computes completion status automatically:
1. Target = Page -> completed if workspace has at least one Page
2. Target = Shop -> completed if workspace has at least one Shop

Progress summary is derived from computed item statuses:
1. completed
2. total
3. percent

## Workflow

### User Workflow

1. Open Checklist from sidebar.
2. Create/Edit/Delete checklist items from table actions.
3. Click View to open checklist progress page.
4. Review auto-computed status (Done/Pending).
5. Click Go Back to return to checklist list.

### Request/Data Workflow

1. Checklist index page requests /workspaces/{workspace}/checklist.
2. Controller returns paginated checklist data + query meta.
3. Frontend renders DataTable with shared pagination.
4. CRUD actions submit to checklist store/update/destroy routes.
5. View page requests /workspaces/{workspace}/checklist/view.
6. Controller computes completion status using workspace pages/shops and returns summary.

## Routes

1. GET /workspaces/{workspace}/checklist -> workspaces.checklist.index
2. GET /workspaces/{workspace}/checklist/view -> workspaces.checklist.view
3. POST /workspaces/{workspace}/checklist -> workspaces.checklist.store
4. PUT /workspaces/{workspace}/checklist/{checklist} -> workspaces.checklist.update
5. DELETE /workspaces/{workspace}/checklist/{checklist} -> workspaces.checklist.destroy

## File Directories Changed

### Backend

1. database/migrations
2. app/Models
3. app/Http/Controllers/Workspaces
4. routes

### Frontend

1. resources/js/pages/workspaces/checklist
2. resources/js/components/checklist

## File List Changed

1. database/migrations/2026_04_11_000000_create_workspace_checklists_table.php
2. app/Models/WorkspaceChecklist.php
3. app/Models/Workspace.php
4. app/Http/Controllers/Workspaces/ChecklistController.php
5. routes/workspaces.php
6. resources/js/pages/workspaces/checklist/index.tsx
7. resources/js/pages/workspaces/checklist/view.tsx
8. resources/js/components/checklist/checklist-columns.tsx
9. resources/js/components/checklist/types.ts

## Manual Test Checklist

1. Run migrations successfully.
2. Open checklist index in a workspace where user is a member.
3. Create an item with target Shop and required true.
4. Edit the same item and verify persisted update.
5. Delete item and verify removal.
6. Open checklist view and verify progress bar updates.
7. Verify Go Back returns to checklist index.
8. Verify non-member user cannot access checklist routes.