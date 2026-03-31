# Branch Changelog

Branch: `feature/inventory-po-management`

This document summarizes the work in this branch using recent commit messages.

## Commits (Newest to Oldest)

- `7e7806b9` removed the max date range for calendar
- `42c9c7a5` cleaned backend codes and removed max date range for calendar
- `bb2dc9ef` added validation for clearing selection in calendar
- `c0a2d92c` replace refresh label to refresh icon in the filter
- `f6e50548` changed "status" into "All Status" by default
- `2c3cd076` added clear date range selection
- `542a8ae5` added empty data filtering
- `a6b2359d` added delete po management data controller
- `a72f490d` added api for deleting product orders
- `9c9e220d` added "add item" modal design refinement
- `36d45b5a` added add item modal
- `54ba5df5` remove excess tsx
- `027cfcee` added logic for adding item in the po management
- `b6bf6a70` added po management add item
- `21359242` added po management "add item" feature
- `646e909f` fix button labels for action
- `2ace9b86` Final PO Management Main
- `c2e83ba3` final working data filtering with design
- `b014126c` fix start date and end date logic (work as set, start date and end date vice versa)
- `3d869f7c` working date filtering (range)
- `058cc50c` Added condition in the select date input to choose only on the current and past dates
- `63ab618a` Added refinement on "Issue Date" column to show only the formatted date "MM/DD/YYYY"
- `395a7490` Added divider in action button and removed redundant pagination button
- `ca4a9fa1` Design adjustment on dropdown and refinement on filter for dates
- `ab5eeec6` Revised main frontend design w/o modal

## Functional Summary

- Implemented PO Management UI and filtering workflow.
- Added date range filtering refinements and clear-selection behavior.
- Added Add Item modal flow and delete API/controller support.
- Improved empty-state handling and filter controls.
- Refined loading/filter action controls and backend cleanup.