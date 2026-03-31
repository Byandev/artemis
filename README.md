# Logout Fix Note

- Updated sidebar footer to submit logout as POST via Inertia. Changed files: [resources/js/components/nav-footer.tsx](resources/js/components/nav-footer.tsx), [resources/js/types/index.d.ts](resources/js/types/index.d.ts).
- Test: click Logout in sidebar footer; expect POST /logout and redirect to home/login.
