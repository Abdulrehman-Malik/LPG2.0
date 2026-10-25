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
| 2026-10-25 | BL-014 standard SaaS roles | Done | bl-014-standard-saas-roles | verify_security | Owner, Manager, Cashier, Driver, Accountant; docs/roles.md |
| 2026-10-25 | BL-015 audit every sensitive action | Done | bl-015-audit-sensitive-actions | verify_audit 22/22 | rates, users, roles, settings, cash, voids |
| 2026-10-25 | BL-016 audit log integrity | Done | bl-016-audit-integrity | full regression green; Playwright 26/26 | strict audit in transactions; refresh/restore guarded in production |
| 2026-10-25 | BL-017 receipt/supplier-payment/expense services | Done | bl-017-money-services | business_flow, cash, parties | PHPUnit smoke for the three services |
| 2026-10-25 | BL-018 report queries out of controllers; no DB in views | Done | bl-018-report-queries | pages, hq, flow, history; Playwright 26/26 | LayoutData for the shell |
| 2026-10-25 | BL-019 API conventions | Done | bl-019-api-conventions | E2E-031; migrations, security, isolation | meta uses has_more (no cursor); Idempotency-Key is a route filter; only 2xx/3xx stored |
| 2026-10-25 | BL-020 OpenAPI spec | Done | bl-020-openapi | openapi_check in CI and run_all | docs/api/openapi.yaml; fails on undocumented routes |
| 2026-10-25 | BL-021 API customers | Done | bl-021-api-customers | E2E-032; isolation now crawls PUT | partial PUT merges stored fields |
| 2026-10-25 | BL-022 API POS sales | Done | bl-022-api-sales | E2E-033 (API vs POS row comparison); Playwright 29/29 | Idempotency-Key required; web Sale History shares SaleQueryService |
| 2026-10-25 | BL-023 API cash sessions | Done | bl-023-api-cash | E2E-034; cash_check, verify_audit | CashService::manualMove (atomic audit) |
| 2026-10-25 | BL-024 API inventory and transfers | Done | bl-024-api-inventory | verify_transfers 31/31 | |
| 2026-10-25 | BL-025 API suppliers, purchases, supplier payments | Done | bl-025-api-purchasing | verify_business_flow 57/57 | SupplierService extracted (now audited) |
| 2026-10-25 | BL-026 API receipts and expenses | Done | bl-026-api-money | E2E-035 | |
| 2026-10-25 | BL-027 API dashboard and reports | Done | bl-027-api-reports | verify_hq 42/42 | DashboardService::range shared with the web dashboard |
| 2026-10-25 | BL-028 client-generated UUIDs | Done | bl-028-client-uuids | verify_migrations 31/31; E2E-033/035 | uuid nullable, unique; app builder stamps v4 (MySQL rejects UUID() defaults/triggers with binlog) |
| 2026-10-25 | BL-029 idempotent creates by UUID | Done | bl-029-idempotent-uuid | E2E-036 (10 concurrent POSTs: 1 sale, 1 stock deduction); Playwright 32/32 | body uuid is the idempotency key when no header |
| 2026-10-25 | BL-030 sync columns and soft delete on masters | Done | bl-030-sync-soft-delete | run_all green (network-flake reruns: shell_check 78/78, Playwright 32/32); verify_migrations 41/41; ledger_check 4/4 | docs/sync-policy.md; builder raises version; cylinder type delete is now soft |
