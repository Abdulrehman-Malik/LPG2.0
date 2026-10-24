| Date | Item | Status | Branch | Tests | Notes |
|---|---|---|---|---|---|
| 2026-10-24 | Phase 1 ORIENT: docs/CODEBASE_MAP.md | Done | feature/backoffice-completion | php -l 213/213; PHPUnit 7/19 (12 need ext-sqlite3 locally) | Read-only; no source changes |
| 2026-10-24 | Phase 2 AUDIT: docs/AUDIT.md, docs/OPEN_QUESTIONS.md | Gate A: owner answered Q-01/02/03/07 | feature/backoffice-completion | Playwright 26/26; run_all.sh 441/441 on disposable lpg_qa | 84 requirements: 8 DONE, 44 PARTIAL, 2 DIVERGENT, 30 MISSING |
| 2026-10-24 | Phase 3 BACKLOG: docs/BACKLOG.md (99 items: 75 P0, 24 P1; P2 parked) | Awaiting Gate B | feature/backoffice-completion | — | Coverage verified by script; new questions Q-15…Q-19 |
| 2026-10-24 | Gate B approved; owner delegated open decisions (OPEN_QUESTIONS §Decisions) | Approved | — | — | Work continues without per-item stops (owner instruction) |
| 2026-10-24 | BL-001 reversible migrations | Done | bl-001-reversible-migrations | verify_migrations 19/19 | CI4 migrations run after SQL history, one per batch |
| 2026-10-24 | BL-002 role-grant restriction | Done | bl-002-role-grant-restriction | verify_security | S-01 fixed |
| 2026-10-24 | BL-003 no default credentials | Done | bl-003-no-default-credentials | verify_security | must_change_password |
| 2026-10-24 | BL-004 API auth throttling, password length | Done | bl-004-api-auth-throttle | verify_security | S-04, S-05 fixed |
| 2026-10-24 | BL-005 secure headers, HTTPS cookies by config | Done | bl-005-secure-transport-headers | verify_security | S-06, S-11 |
| 2026-10-24 | BL-006 safe errors, JSON logs, request IDs | Done | bl-006-safe-errors-structured-logs | verify_security 39/39; full regression green | S-07 |
| 2026-10-24 | BL-007 tenant isolation harness | Done | bl-007-tenant-isolation-harness | verify_isolation | 71 GET + 67 POST routes with tenant-B IDs |
| 2026-10-24 | BL-008 TenantContext | Done | bl-008-tenant-context | full regression green | no session reads in models/services |
| 2026-10-25 | BL-013 default tenant for unassigned branches | Done | bl-013-default-tenant-branches | verify_tenancy_migrations | done before BL-009 (dependency) |
| 2026-10-25 | BL-009 business_id on branch tables | Done | bl-009-business-id-branch-tables | full regression green | composite FKs; app driver stamps tenant (triggers need SUPER) |
| 2026-10-25 | BL-010 business_id on child tables | Done | bl-010-business-id-child-tables | targeted + E2E subset | |
| 2026-10-25 | BL-011 tenant-scoped base model | Done | bl-011-tenant-scoped-models | full regression green | |
| 2026-10-25 | BL-012 no cross-tenant fallbacks | Done | bl-012-no-cross-tenant-fallbacks | isolation 23/23, tenancy 33/33 | found + fixed Shop Settings cross-tenant user leak |
