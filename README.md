# Employees Table Column Width Fix

- Locked Name/Email/Phone column sizes and set table to table-fixed to stop width shifting. Changed files: [resources/js/components/ui/data-table.tsx](resources/js/components/ui/data-table.tsx), [resources/js/pages/workspaces/employees/index.tsx](resources/js/pages/workspaces/employees/index.tsx).
- Test: open Employees page, sort and paginate; column widths should stay consistent while data loads.

## Changed File Locations

```
resources/
└── js/
	├── components/
	│   └── ui/
	│       └── data-table.tsx      # Applies table-fixed and column size styles
	└── pages/
		└── workspaces/
			└── employees/
				└── index.tsx       # Sets explicit sizes for Name/Email/Phone columns
```