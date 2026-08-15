# Backend modules

Each module owns one business capability and only the layers its implemented behavior needs.

## Current ownership

| Module                                | Owns                                                                                                                 | Does not own                                            |
| ------------------------------------- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| `Identity`                            | User identity, sessions, administrator boundary                                                                      | Customer profiles or order state                        |
| `Products`                            | Categories, products, tags, availability, base prices, cooking surcharges, public catalog                            | Cart grouping or historical sale lines                  |
| `Customers`                           | Profile CRUD, optional phone/address/private notes, phone normalization, search, order history                       | Public identity verification or order notes             |
| `Orders`                              | Pricing, POS/public creation, safe admin editing, snapshots/revisions, delivery, states, summaries, WhatsApp handoff | Product CRUD or customer private-note changes           |
| `Settings`                            | Admin editing of order settings, cooking surcharges, and existing toppings                                           | Pricing calculations                                    |
| `PublicSite`                          | Public page routes and menu/cart entry                                                                               | Authoritative checkout logic                            |
| `Dashboard`, `Analytics`, `Inventory` | Current route/page boundaries; `/admin` is reserved for the accepted quick-order Home slice                          | Functional analytics, quick-order UI, or stock behavior |

## Customers

```text
Customers/
  Actions/       create, update, soft delete, restore and structured events
  Data/          validated customer command data
  Http/          admin controller, requests, explicit resource
  Models/        Customer and Orders relationship
  Policies/      admin authorization
  Support/       PhoneNormalizer
  routes.php
```

Names are not unique. Phone is optional for administrative records. When present, it is normalized centrally and must be unique among active customers. Private notes are only returned by the authenticated admin resource and are never copied to orders or public responses.

Customer history reads persisted orders. Effective historical spend excludes soft-deleted orders through Eloquent and excludes `cancelled` orders explicitly.

Deletion is recoverable: the module exposes an administrator-only trash and `RestoreCustomer`. It never force-deletes and never cascades into Orders. Because active-phone uniqueness ignores deleted rows, restore checks for a new active owner of the same `phone_normalized`; on conflict it rejects the restore without changing or merging either customer.

## Orders

```text
Orders/
  Actions/       create/update, quote persistence, edit hydration, public selection resolution, status/payment updates
  Data/          immutable validated checkout input
  Enums/         states, source, condition, delivery, pricing, setting keys
  Http/          admin/public controllers, requests, resource
  Models/        order, item/composition, topping, addon, setting, redacted revision
  Policies/      admin authorization
  Pricing/       one deterministic backend pricing engine and DTOs
  Support/       summary formatter and WhatsApp link builder
  routes.php
```

`CreateOrder` is the shared application boundary for POS and public checkout. In one transaction it resolves/creates the customer, calculates the quote, resolves payment semantics, creates the order, persists item/composition/topping snapshots, and stores totals. Public source fixes status to `pending_confirmation`; admin source starts at `waiting`. `ResolveOrderPayment` canonicalizes every backend-calculated zero total as `not_required` without a method.

`UpdateOrder` is the administrative correction boundary. Create and edit share `OrderCheckoutData`, `ResolveOrderCustomer`, `OrderPricingEngine`, and `PersistOrderQuote`; the update action does not contain a second pricing implementation. `BuildAdminOrderEditData` reconstructs real quantities from normal items plus combined-dozen compositions and builds the original persisted quote. `BuildAdminOrderFormOptions` includes current sellable choices plus unavailable historical selections needed to render the draft.

Every update locks the order, compares `expected_version` with `orders.edit_version`, and commits the order, any replacement item/composition/topping snapshots, and one `order_revisions` row in a single transaction. Standalone status and payment updates also increment the version so an older edit tab cannot overwrite them.

Delivered and cancelled orders require a short reason. The grant is stored in the authenticated admin session, bound to the order and its current version, consumed after one successful update, and does not transition the order's status.

Public requests use product/topping slugs. `ResolvePublicCheckoutSelections` converts them to internal IDs only after checking current sellability. The pricing engine then calls `ProductPriceProvider`; callers never calculate authoritative totals.

Admin create/edit/Quick Order may apply a percentage or fixed-amount discount and choose a payment method. Discount calculation remains inside `OrderPricingEngine`; paid requires one of the `PaymentMethod` enum values, unpaid clears the method, and a zero total becomes `not_required`. Public checkout fixes both features off. `OrderBusinessDate` provides the Argentina calendar day used by admin form options and public date validation without changing UTC timestamp storage.

Order deletion is also recoverable. The administrator-only trash restores the same order row, public ID, revisions, and snapshots; no force-delete route or UI exists.

## Settings

`GET/PUT /admin/settings/general` edits the four typed order settings, three existing cooking surcharges, and existing addon rows in one transaction. The UI intentionally does not redesign Settings or create a separate toppings module.

| Setting                       |      Initial value |
| ----------------------------- | -----------------: |
| `combined_dozen_raw_price`    |              16000 |
| `combined_dozen_cooked_price` |              17500 |
| `delivery_fee`                |                  0 |
| `whatsapp_order_phone`        | empty/unconfigured |

Initial toppings are Lactonesa (500) and Yasgua (200). Their names, prices, availability, and order are configurable. An empty WhatsApp phone prevents link creation with an explicit validation error; it is not replaced with a real or hardcoded number.

## Adding behavior

1. Put the use case in its owning module.
2. Reuse a narrow contract when another module owns required data.
3. Keep HTTP input/output explicit through Form Requests and resources.
4. Use a transaction for multi-record invariants.
5. Add only folders that contain real code.
