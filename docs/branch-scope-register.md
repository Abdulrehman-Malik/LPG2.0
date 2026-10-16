# Branch / Business Scope Register

**Purpose:** record how every back-office data path is limited to the user's authorized business and branch (Phase 9.1). Update this register whenever a controller, service or report reads or writes branch- or business-owned data.

**Last review:** 2026-10-16, `feature/backoffice-completion`. Method: a scripted method-level scan of all 45 controllers plus a statement-level scan of controllers, services and models for queries on branch/business-owned tables without a scope condition, then a manual review of every endpoint that takes a record ID (route parameter or posted ID). Runtime checks are recorded in `SQA.md` (P8, P9 sections).

## Ownership model

| Level | Data | Rule |
|---|---|---|
| Platform | users, roles, permissions, application settings | System Administrator only for platform-wide settings and role permissions |
| Business | cylinder types, expense categories, suppliers (business-wide), business settings, catalog | Visible to every branch of the business; never to another business (`BusinessContext`) |
| Branch | customers, sales, receipts, purchases, supplier payments, expenses, cash registers/sessions, cylinder units, inventory movements, deposits/custody, rates, shop settings, branch-only suppliers | Visible only in that branch's context (`session location_id`, validated by `ShopScopeService` on every request) |
| Cross-branch | stock transfers | Visible to the sending and receiving branch only |
| Head Office reports | Branch Overview | Only branches returned by `ShopScopeService::accessibleShopIds()` within one business |

## How scope is enforced

1. `AuthFilter` re-validates the session branch on every request (`canAccessCurrentShop`), repairs a stale branch only to an authorized one, and signs out disabled users.
2. Branch switching (`ShopContext::select`) accepts only branches returned by `ShopScopeService`.
3. Posting services receive the branch explicitly (`SalesService`, `PurchaseService`, `StockTransferService`, `InventoryService`, `CashService`) and lock/validate rows with `location_id`.
4. Business-owned master data is loaded through `BusinessContext` (`cylinderType`, `expenseCategory`, `supplierVisibleTo`, `scopeSuppliers`), which returns nothing for another business's IDs.

## Review results

| Area / endpoint | Scope check | Result |
|---|---|---|
| POS save, void, receipt, details, custody, customer balance | `location_id` in service/queries; customer via `findForLocation` | OK |
| Purchases save/void | Supplier via `supplierVisibleTo`; purchase row locked with `location_id` | OK |
| Supplier payments | Supplier via `supplierVisibleTo`; balances per branch | OK |
| Suppliers list/ledger/save | `SupplierModel::visibleTo`, `supplierVisibleTo` | OK (Phase 9.5.3) |
| Customers list/ledger/save | `findForLocation`, `withLedgerTotals($id,$location)` | OK |
| Customer receipts | Customer row locked with `location_id` | OK |
| Expenses / expense categories | Category via `BusinessContext::expenseCategory`; list by business | OK (Phase 9.5.3 / 9.3) |
| Cylinder types, rates, inventory controls, opening inventory | Types via `BusinessContext`; rates/policies by `location_id` | OK (Phase 9.5.3) |
| Inventory overview, cylinder detail, type history, adjustments, wastage | `location_id` filters; unit locked with `location_id` | OK |
| Stock transfers list/detail/receive/cancel | Sender/receiver checks in `StockTransferService` | OK (Phase 9.5.4) |
| Reports index, custody, inventory detail | `location_id` filters | OK |
| Reports ledger (`reports/ledger/{id}`) | Customer loaded **without** branch check | **Fixed** 2026-10-16: customer must belong to the branch |
| Dashboard receivables list | Loaded **all** active customers of every branch/business and asked the POS balance for each; crashed (HTTP 500) in any branch other than the customers' own | **Fixed** 2026-10-16: customers limited to the current branch |
| Counter cash close (`cash/close`) | Session closed by posted ID **without** branch check | **Fixed** 2026-10-16: session must belong to the branch's register |
| Branch Overview (`reports/branches`) | Intersects requested branches with accessible branches of the selected business | OK (Phase 9.2) |
| Users list/edit, branch access | `scopedUser`; branch access limited to branches the actor can access | OK (Phase 9.3.2) |
| Businesses & Shops, staff access | System Administrator or `canAccessBusiness`/`canAccessShop` | OK |
| Customer app accounts, mobile catalog, customer orders, configuration | Shop/business context checks in controllers and services | OK |
| Mobile API (`/api/v1`) | Token principal + `ShopScopeService` per request | OK (CI E2E-026/027) |

## Known limits

- Platform tables (users, roles) are listed per branch in the Users screen but a System Administrator can see all; this is intentional.
- The scripted scan is a heuristic. New endpoints that take an ID must be added to this register and covered by a cross-branch runtime check.
