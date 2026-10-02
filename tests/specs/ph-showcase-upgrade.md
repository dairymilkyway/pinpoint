# Feature: Philippine showcase upgrade

## What it does

Turns the address book from a working scaffold into something presentable. Four
changes, in build order: seed data that reads like real Philippine addresses, a
public landing page at `/` for logged-out visitors, a premium redesign across
every screen, and a Leaflet map plus cascading PSGC address dropdowns.

## Scope

**Touches:**
- `database/factories/AddressFactory.php`, `database/factories/UserFactory.php`
- `database/seeders/AddressSeeder.php`, `database/seeders/UserSeeder.php`
- `resources/views/**` (layout, landing, addresses, rbac, auth)
- `resources/sass/app.scss`, `resources/js/app.js`
- `routes/web.php` (landing route only)
- `app/Http/Controllers/AddressController.php` (map data, if a detail view is added)
- `app/Models/Address.php` (geo casts and fillable)
- `database/migrations/` (one new migration for the geo columns)
- `package.json` (Leaflet, fonts)
- `resources/data/` (bundled PSGC dataset)

**Does NOT touch:**
- `.env`, `vendor/`, `node_modules/`, anything outside the project root
- The auth flow, the permission model, or the RBAC rules - these keep working
  exactly as they do now
- The Excel export behaviour
- The existing 37 tests - they must stay green, unchanged

## Acceptance criteria

### 1. Philippine seed data
- [x] Seeded owners have Filipino names
- [x] Every seeded address uses a real city or municipality from the PSGC list
- [x] Postal codes come from a sourced mapping and are marked non-authoritative
- [x] Province matches the city, and the city is genuinely in that province
- [x] `php artisan migrate --seed` still runs clean on an empty database

All five are covered by `tests/Feature/SeederTest.php`, which seeds a migrated-empty
database through `DatabaseSeeder` and then asserts the city code resolves in
`PhLocations::cities()`, the stored name matches the dataset, coordinates are
present, and the recorded region/province/state agree with the city. The
"runs clean on an empty database" criterion is verified on that same code path
(migrations then `DatabaseSeeder`), not by a literal `artisan migrate --seed`
against the development database.

**Correction to the original criterion.** This spec first said "postal codes are the
correct 4-digit code for the paired city". That is not achievable, and the criterion
was wrong rather than merely hard. PSGC carries no postal code at all, and Philippine
postal codes are delivery-area identifiers, not administrative ones: one city commonly
has several, and one code can span localities. There is no authoritative 1:1
city-to-postal mapping. PHLPost's locator is the only authority and it is a manual web
UI with no API. So the seeded postal code will be a sourced-but-approximate value and
must be labelled as such, not presented as correct.

### 2. Landing page
- [x] Logged-out visitors to `/` see a landing page, not a redirect
- [x] Logged-in visitors to `/` still land on the address list
- [x] The page has a sign-in call to action

### 3. Premium redesign
- [x] One consistent visual system across layout, addresses, RBAC and auth screens
- [x] Custom typography self-hosted (no stock Bootstrap font stack)
- [x] Single accent colour used sparingly; no gradients, no glassmorphism
- [x] Hairline borders rather than soft shadows
- [x] Motion limited to fast hover and focus transitions
- [x] Readable and usable at 375px width

Typography is Space Grotesk and JetBrains Mono, self-hosted as variable woff2
through `@fontsource-variable/*`, so there is no runtime request to Google.
The 375px criterion rests on the single-column default of `.landing__grid`,
`.app-shell` collapsing to a stacked layout, and the table switching to the
stacked card presentation below `md`; verified by reading the breakpoints, not
by a device-emulated screenshot.

### 4. Map and PSGC dropdowns
- [x] Address form uses cascading Region -> Province -> City/Municipality dropdowns
- [x] Child dropdowns repopulate from the parent selection and reset correctly
- [x] Server-side validation restricts city/province to values from the dataset
- [x] An address with coordinates renders a Leaflet pin on OpenStreetMap
- [x] No API key, no live external geocoding call at request time

The pin criterion is only observable on rows that have coordinates. The
developer database still holds pre-geo rows with null coordinates, so the map
renders empty until it is reseeded - see the open item at the foot of this file.

### 5. Regression
- [x] `php artisan test` passes, including the original 37 tests
- [x] Viewer still gets 403 on create/edit/delete and sees no action buttons
- [x] Manager still cannot delete or reach `/rbac`
- [x] Export still honours the active search filter

58 tests, 478 assertions, all passing.

## Deviation: `city_code` is not unconditionally required

The dataset is authoritative **when a city code is supplied**, not mandatory on
every write. The request rules keep `city`, `state`, `postal_code` and `country`
required - as the original form and tests expect - and add nullable `Rule::in`
rules for the three geographic codes. When `city_code` is present,
`StoreAddressRequest::prepareForValidation()` overwrites city, state, country,
region code, province code, latitude and longitude from the dataset, so a
client cannot contradict it. That override is proven by
`GeoLookupTest::test_storing_an_address_with_a_city_code_derives_the_rest`,
which posts deliberately wrong values and asserts the dataset wins.

Making `city_code` unconditionally required would break `AddressCrudTest` and
`AddressAuthorizationTest`, which post addresses without it. Those tests were
scoped as "must stay green, unchanged", so the requirement was narrowed rather
than the tests rewritten.

## Open item: the development database has not been reseeded

The database at `127.0.0.1` still holds the output of the earlier non-idempotent
seeder, run twice: nine users (including eight role-less faker accounts) and
fifty addresses with NULL coordinates, since the geo columns were added
afterwards. The map therefore shows no pins and `/addresses/map` returns `[]`.

Clearing this needs `php artisan migrate:fresh --seed`, which the exam brief
lists as a destructive command requiring approval before it runs. It has not
been run. The seeders themselves are idempotent and correct - `SeederTest`
proves that against a clean database - so this is a stale-data problem, not a
seeder problem.

## Implementation layers

Built directly by dev (see note below), in this order:

1. **Database** - PH seeder and factory, plus the new geo migration
2. **Frontend/landing** - public landing page and its route
3. **Frontend/design** - the visual system across all screens
4. **Full-stack/geo** - PSGC dataset, cascading dropdowns, Leaflet map

## Constraints from memory/codebase

- **No `AGENTS.md` exists in this repo.** The council specialist chain
  (john/jigs/ogie/semantic-reviewer/andrei) is therefore not runnable here:
  the workspace protocol forbids dispatching without a `PROJECT CONTEXT` block
  resolved from that file. Work is done directly instead, and this is the
  reason. If the chain is wanted later, create the project's `AGENTS.md` first.
- **`council.config.json` resolves specs to `E:\VSC\council-of-au\tests\specs`**,
  a different project. Following it would write outside this project root,
  which the exam brief forbids. This spec therefore lives in the project's own
  `tests/specs/`. Flagging as a known harness mismatch, not fixing it here.
- **Stack is locked:** Laravel 12, Bootstrap 5, Vite, Spatie, Yajra, Maatwebsite.
  Leaflet is additive, not a replacement. No paid APIs. No Bouncer.
- **Authorization** uses Laravel's `can:` middleware and `$this->authorize()`,
  because Spatie v8 does not auto-register its aliases and registering them
  needs `bootstrap/app.php`, which is outside the writable scope.
- **PSGC is a static snapshot.** Correct as of its publication date; barangays
  are created and renamed over time. Accepted for a showcase.
- **No live geocoding.** Coordinates are resolved at seed time, so there is
  nothing to rate-limit and nothing to bill.
