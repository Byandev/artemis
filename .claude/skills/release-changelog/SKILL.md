---
name: release-changelog
description: Write a new release entry in resources/js/pages/workspaces/changelog.tsx from the commits merged since the last release. Use when asked to "update the changelog", "add vX.Y.Z to the changelog", or to write release notes for a merge/release commit.
---

# Release changelog

`resources/js/pages/workspaces/changelog.tsx` is the user-facing "What's new" page.
It is the only place a version number lives in this repo — there is no
`package.json` version or config constant to bump alongside it.

The job is to turn a range of commits into a handful of sections of plain-English
prose that a CSR, a finance person, or an inventory manager would understand
without knowing what a controller is.

## 1. Find the range

The user gives a commit (usually the `develop` → `staging` merge) and a version.
The previous release is the last commit that touched the changelog file:

```bash
git log --oneline -3 -- resources/js/pages/workspaces/changelog.tsx
git log --oneline <last-release>..<target-commit> --no-merges
git diff --stat <last-release>..<target-commit>
```

Use the target commit's own date for the entry's `date` (`YYYY-MM-DD`).

## 2. Read the actual diffs — never the commit subjects

Commit subjects here are mostly `fix`, `Fix`, `bugfix`, `fix: <thing>`. They are
useless as changelog copy. For anything non-trivial, read the diff and work out
what changed *on screen*:

```bash
git diff <last-release>..<target-commit> -- <path>
git show <commit>            # for a commit with a descriptive body
git show <target>:<file>     # to read a new file whole
```

Prioritise, in this order: new routes in `routes/workspaces.php` and
`routes/console.php`, new sidebar entries in `app-sidebar.tsx`, new pages under
`resources/js/pages/`, new migrations, new module flags on `Workspace`, changed
validation rules and form components, then everything else. Read the code
comments — this codebase explains its "why" in comments, and that reasoning is
usually the best raw material for a changelog line.

Ignore entirely: refactors with no visible effect, test-only commits,
`Auto stash before checking out`, formatting, and code deleted as part of a
rewrite that shipped in the same range (describe the thing that shipped, not the
scaffolding that was thrown away on the way there).

## 3. Shape

```tsx
{
    version: 'v3.22.0',
    date: '2026-08-05',
    sections: [
        { title: 'Inventory — Dashboard (New)', items: ['…', '…'] },
        …
    ],
},
```

The new entry goes at the **top** of the `changelog` array.

- 3–6 sections. Group by the area a user would look under, not by module or by
  commit. `Area — What changed` is the title format, em dash included; append
  `(New)` when the whole thing is new.
- 2–5 items per section, longest/most important first.
- A `Smaller Improvements & Fixes` section last, for the leftovers that don't
  earn their own heading.
- No trailing full stops on items. Curly apostrophes (`’`), em dashes (`—`),
  and en/em dashes used the way the existing entries use them.

## 4. Voice

Match the existing entries — this is the part worth getting right. Read the two
entries above whatever you're adding before you write.

- Write to the person who uses the feature, in the second person: "pin the list
  to a past date", "you get told how much is still unallocated".
- Say what changed *and* what it means for them, usually joined by an em dash.
  "The window ends yesterday **so a half-written day doesn't read as a slump**."
  The consequence clause is what makes these entries worth reading.
- For a bug fix, say what happens now and what used to happen: "clearing every
  shift in a week now saves; previously the cleared shifts came back on reload".
- Use the label the user sees in the UI — "PO QTY column", "Charge To",
  "viewing as team switcher" — capitalised the way the screen capitalises it.
- Never name a file, class, table, route, migration, permission constant, or
  commit. "Ad Spend Goals is now its own admin-toggled module", not
  "added `ad_spend_goals_module_enabled` to workspaces".
- Long sentences are fine and normal here. Don't clip them into release-note
  telegraphese.

Anti-pattern to avoid:

> - Added InventoryDashboardStatsController with per-KPI endpoints.
> - Fixed FundRequestController::nextReferenceNo to use max instead of count.

Written properly:

> - Four headline tiles each load and refresh on their own, so a slow figure
>   never holds up the rest of the row
> - Fund request reference numbers no longer collide — the next number carries
>   on from the highest one issued rather than the row count, so deleting an
>   older request can't hand out one that's still in use

## 5. Finish

```bash
npx prettier --write resources/js/pages/workspaces/changelog.tsx
```

The "unused default export" diagnostic on this file is expected — it's an Inertia
page resolved at runtime by `app.tsx`, not imported anywhere.

Then summarise the sections you added for the user, so they can spot anything
you misread or left out.
