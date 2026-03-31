# Profile Name Change Fix

- Fixed profile update redirect to include the workspace parameter, keeping users on the correct profile page after saving name/email. Changed file: [app/Http/Controllers/Settings/ProfileController.php](app/Http/Controllers/Settings/ProfileController.php).
- Test: change name/email in workspace profile settings; expect successful save and redirect back to the same workspace profile page.

## Changed File Locations

```
app/
└── Http/
	└── Controllers/
		└── Settings/
			└── ProfileController.php   # Redirect now includes workspace param after update
```
