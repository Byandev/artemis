---
name: release-changelog
description: Write a new release entry in resources/js/pages/workspaces/changelog.tsx from the commits merged since the last release, using the bump or version you name (major/minor/patch, or vX.Y.Z) and otherwise picking the semver bump from what actually changed. Use when asked to "update the changelog", "cut a release", "add vX.Y.Z to the changelog", or to write release notes for a merge/release commit.
---

# Release changelog

`resources/js/pages/workspaces/changelog.tsx` is the user-facing "What's new" page.
It is the only place a version number lives in this repo — there is no
`package.json` version or config constant to bump alongside it.

The job is to turn a range of commits into a handful of sections of plain-English
prose that a CSR, a finance person, or an inventory manager would understand
without knowing what a controller is.

## 1. Find the range

The target commit is usually the `develop` → `staging` merge; if the user doesn't
name one, use `HEAD`. The previous release is the last commit that touched the
changelog file:

```bash
git log --oneline -3 -- resources/js/pages/workspaces/changelog.tsx
git log --oneline <last-release>..<target-commit> --no-merges
git diff --stat <last-release>..<target-commit>
```

Use the target commit's own date for the entry's `date` (`YYYY-MM-DD`).

If the range is empty, say so and stop — there's nothing to write.

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

## 3. Pick the version

Versions are `vX.Y.Z` — X major, Y minor, Z patch. Take the version at the top of
the file and move exactly one part, zeroing every part to its right:

| Bump      | Moves               | v3.37.1 becomes |
| --------- | ------------------- | --------------- |
| **major** | X + 1, Y and Z to 0 | v4.0.0          |
| **minor** | Y + 1, Z to 0       | v3.38.0         |
| **patch** | Z + 1               | v3.37.2         |

**If the user names it, use it** — a bump ("major", "minor", "patch", "minor
only") or an exact version ("use v3.37.1"). Don't re-derive it from the diffs or
argue the table at them; they know things you don't, such as whether the version
above already went out to users. Write the entry and say which version you used.

Otherwise decide it yourself from what the diffs actually changed. Semantic
versioning, read for an internal web app with no public API: "breaking" means the
people using Artemis have to change what they do, not that a function signature
moved.

| Bump      | When                                                                                                                                                                                              |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **major** | A workflow people rely on is removed or reworked so they must relearn it; a module is retired; something needs action from admins (re-granting permissions, re-entering data) before it works again |
| **minor** | Anything new and user-visible — a page, module, table, column, filter, export, setting, scheduled job, or a meaningful new ability inside an existing screen                                        |
| **patch** | Only changes to things that already exist — bug fixes, corrected figures, sorting and layout, wording, performance, validation messages                                                             |

Rules of thumb:

- The highest-ranking change in the range wins. One new feature alongside eight
  fixes is still a **minor**.
- If any section title you're about to write ends in `(New)`, it's a **minor**.
- A fix that changes a number people have been reading (a total that was wrong,
  a figure that was double-counted) is still a **patch** — the capability
  already existed, it was just wrong.
- **major** is rare and consequential. Never pick it on your own judgment alone;
  say why you think it qualifies and let the user confirm.

Don't infer the convention from the file's own history — earlier entries are
inconsistent (v3.15.4 and v3.19.1 both shipped whole new features as patch
releases). Follow the table, not the precedent.

Say which bump you chose and why when you report back, so a wrong call is easy
to spot and correct.

### Same-day continuation of an unreleased feature

If the range is more work on a feature the entry directly above introduced —
same day, extending rather than fixing — ask before writing whether to fold it
into that entry or start a new one. Both are defensible: folding in reads as one
coherent release, a new entry is right if the previous version already went out
to users. This comes up often enough to be worth the one question.

## 4. Shape

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

## 5. Voice

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

## 6. Finish

```bash
npx prettier --write resources/js/pages/workspaces/changelog.tsx
```

The "unused default export" diagnostic on this file is expected — it's an Inertia
page resolved at runtime by `app.tsx`, not imported anywhere.

Then report back with the version you chose and the one-line reason for the bump,
followed by a summary of the sections you added — so a wrong version or a
misread commit is easy to spot. Call out anything you deliberately left out.
