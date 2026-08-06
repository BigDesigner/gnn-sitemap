# System Coherence Protocol

## Overview
This document specifies operational standards, verification gates, state locking expectations, and context drift prevention procedures for AI agents interacting with the GNN Sitemap codebase.

---

## Operational Lifecycle

### 1. Session Initialization & Resume Protocol
- Check for `.memory-bank/active-session.json`.
- Verify `.memory-bank/.session.lock`. If a stale lock (>10 min) exists, record an environment warning in `.memory-bank/bugs/bug-list.md` and remove the lock.
- Acquire lock by creating `.memory-bank/.session.lock`.
- Read active session state and active sprint tasks from `.tasks/pipeline.md`.

### 2. Operating Mode Enforcement
- **Interactive Mode**: Discovery is read-only. Dirty worktree requires confirmation before proceeding. Requires explicit approval before making non-memory-bank code changes or committing.
- **CI Mode**: Triggered when `CI=true` is set. Non-interactive, approval gates are skipped, dirty worktrees log warnings, and output summary is recorded in `.memory-bank/changelog/ci-run-summary.md`.

### 3. Pre-Change Verification Checklist
- Verify current git branch and confirm clean working tree.
- Ensure target paths are permitted by `.agents/runtime-manifest.json`.
- Cross-reference `.specs/boundary-conditions.md` to prevent security regression or API contract breakage.
- Never modify application source code (`gnn-sitemap/*.php`) during memory bank setup/migration phases.

### 4. Post-Change & Handoff Protocol
- Update `.memory-bank/changelog/verified-worklog.md` with verified actions.
- Update `.tasks/pipeline.md` with current task status.
- Generate reusable handoff notes in `.tasks/handoff.md`.
- Release lock by deleting `.memory-bank/.session.lock`.

---

## State Locking & Concurrency
- Concurrent agents must respect `.memory-bank/.session.lock`.
- Updates to `active-session.json` must be atomic (write to `active-session.tmp.json` first, then overwrite `active-session.json`).

---

## Unconfirmed Decision Protocol
- If a critical architectural, security, or API fact cannot be verified directly from repository files, mark it as `Unconfirmed`.
- In Interactive mode, ask the user before making decisions based on `Unconfirmed` facts.
- In CI mode, record proposed ADRs marked `Status: Proposed` and `Confidence: Unconfirmed`.
