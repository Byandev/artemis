# Workflow for all tasks

For every non-trivial change request in this repo, follow this flow:

1. **Plan first.** Before touching any files, produce a concise implementation plan: what files will change, what the approach is, and any tradeoffs or risks. Do not write or edit code yet.
2. **Wait for my explicit approval** of the plan. If I push back, revise and re-present. Do not proceed on assumed approval.
3. **Create a git worktree** for the work (e.g. `git worktree add ../ecomm-<short-slug> -b <branch-name>`) and perform all edits inside that worktree. Never commit directly on `main`.
4. **Implement** the approved plan in the worktree. Run type checks / tests where applicable.
5. **Commit and push** the branch to origin.
6. **Open a PR** with `gh pr create`, including a Summary and Test plan derived from the actual changes.
7. Report back the PR URL and the worktree path.

Skip this flow only for trivial one-liners (typo fixes, comment tweaks) or when I explicitly say "just do it".