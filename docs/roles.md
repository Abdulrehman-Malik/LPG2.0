# Roles and permissions

Fixed platform role set (owner decision Q-11, 2026-10-24). Every role's permissions can still be adjusted by a System Administrator on **Users / Roles → role permissions**, but a user manager can only grant a role whose permissions they hold themselves (BL-002).

| Role (code) | Who | Purpose |
|---|---|---|
| **System Administrator** (`users.is_system_admin = 1`, any role) | SaaS operator | All businesses; backups, application settings, role permissions, tenant onboarding and support login |
| **Owner** (`ADMIN`) | Distributor owner | Everything in their own business |
| **Manager** (`MANAGER`) | Branch manager | Operations of assigned branches; no user, backup or application settings |
| **Cashier** (`CASHIER`) | Counter staff | POS, sale history, customers, receipts |
| **Driver** (`DRIVER`) | Delivery driver | Own deliveries only: list, load, record delivery, cash collected |
| **Accountant** (`ACCOUNTANT`) | Bookkeeper | Ledgers, receipts, supplier payments, expenses, cash book, reports, audit; no stock changes, no POS |

## Permission matrix

✔ = granted by default. System-admin-only codes (marked †) are ineffective unless the user is also a System Administrator.

| Permission | Owner | Manager | Cashier | Driver | Accountant |
|---|:-:|:-:|:-:|:-:|:-:|
| DASHBOARD_VIEW | ✔ | ✔ | ✔ | | ✔ |
| POS_SALE | ✔ | ✔ | ✔ | | |
| POS_HISTORY | ✔ | ✔ | ✔ | | ✔ |
| POS_VOID | ✔ | ✔ | | | |
| CUSTOMER_MANAGE | ✔ | ✔ | ✔ | | ✔ |
| SUPPLIER_MANAGE | ✔ | ✔ | | | ✔ |
| RATE_MANAGE | ✔ | ✔ | | | |
| INVENTORY_MANAGE | ✔ | ✔ | | | |
| INVENTORY_CONTROLS_MANAGE | ✔ | ✔ | | | |
| GAS_WASTAGE_MANAGE | ✔ | ✔ | | | |
| STOCK_TRANSFER_MANAGE | ✔ | ✔ | | | |
| PURCHASE_MANAGE | ✔ | ✔ | | | ✔ |
| CASH_MANAGE | ✔ | ✔ | | | ✔ |
| EXPENSE_MANAGE | ✔ | ✔ | | | ✔ |
| REPORT_VIEW | ✔ | ✔ | ✔ | | ✔ |
| AUDIT_VIEW | ✔ | ✔ | | | ✔ |
| USER_MANAGE | ✔ | | | | |
| SHOP_ACCESS_MANAGE | ✔ | ✔ | | | |
| BUSINESS_SETTINGS_VIEW / _MANAGE | ✔ | ✔ | | | |
| SHOP_SETTINGS_VIEW / _MANAGE | ✔ | ✔ | | | |
| BUSINESS_CATALOG_VIEW / _MANAGE | ✔ | ✔ | | | |
| ORDER_VIEW / ORDER_MANAGE | ✔ | ✔ | | | |
| CUSTOMER_ACCOUNT_MANAGE | ✔ | ✔ | | | |
| DELIVERY_MANAGE (assign, vehicles, settlement) | ✔ | ✔ | | | |
| DELIVERY_VIEW_OWN | ✔ | ✔ | | ✔ | |
| DELIVERY_RECORD | ✔ | ✔ | | ✔ | |
| BACKUP_MANAGE † | ✔ | | | | |
| ROLE_PERMISSION_MANAGE † | ✔ | | | | |
| APPLICATION_SETTINGS_VIEW / _MANAGE † | ✔ | | | | |

After sign-in a user lands on the first page their permissions allow (Dashboard for most roles, My Deliveries for drivers).
