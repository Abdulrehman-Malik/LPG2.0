# LPG Mobile Workspace

This workspace contains two separate Flutter clients:

- `apps/customer_app` — customer identity, catalog and own-order experience.
- `apps/business_staff_app` — staff identity, authorized shop context and order queue.
- `packages/lpg_mobile_core` — shared API client, rotating-token storage and common sign-in UI.

The apps have separate application packages and authentication audiences. Shared code does not share sessions across audiences. All business rules and persistent data remain on the CodeIgniter API; mobile clients never connect to MySQL.

## API configuration

Supply the API base URL at build/run time. Do not hard-code environment URLs:

```sh
flutter run --dart-define=LPG_API_BASE_URL=https://your-host.example/api/v1
```

The configured URL must end in `/api/v1`. For local development, use the reachable host address for the selected emulator/device.

## Current stage

The Dart source now includes:
- Separate customer/staff login audiences and secure token storage.
- Customer catalog browsing, cart quantities, delivery/collection checkout, idempotent order submission, order history, price reconfirmation and eligible cancellation.
- Staff shop selection, order status actions, physical-cylinder/source selection, payment entry and the guarded POS fulfillment endpoint.
- Shared authenticated API client with refresh-token rotation and consistent API error handling.

Remaining release work includes Android/iOS runner projects and application IDs/signing, signup/approval UX, richer profile/catalog administration, integration/device testing, offline/network resilience, localization generation, and store builds. These source clients are **not yet store-ready**; successful static analysis is not a substitute for device-level end-to-end QA.
