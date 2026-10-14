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

This is the initial Dart source workspace: API/auth plumbing, login and first home/order-list screens are being wired to the existing API. Android/iOS runner projects, catalog/cart/checkout, the staff physical-cylinder/payment fulfillment UI, localization generation, and device builds remain subsequent work. Do not treat these sources as store-ready applications yet.
