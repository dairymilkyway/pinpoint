# Feature: Role dashboards and global loading states

## What it does

Adds a landing dashboard that every signed-in user gets, with panels that change
according to what their role can do, and introduces one reusable skeleton
shimmer so the ajax surfaces stop flashing blank while they wait.

## Correction to the request

The request assumed Manager and Viewer already had a dashboard. They do not, and
neither does Admin. `/home` is a closure that redirects every role straight to
`/addresses`. `HomeController` and `resources/views/home.blade.php` existed but
nothing routed to them; the view was the stock "You are logged in!" placeholder.
This is therefore new work for all three roles, not an extension. Both of those
dead files have since been deleted - see Follow-ups below.

## Scope

**Touches:**
- `app/Http/Controllers/DashboardController.php` (new)
- `routes/web.php` (`/home` stops redirecting and renders the dashboard)
- `resources/views/dashboard/index.blade.php` and its partials (new)
- `resources/views/layouts/app.blade.php` (nav gains Dashboard)
- `resources/views/addresses/index.blade.php` (skeleton hooks on table and map)
- `resources/views/addresses/partials/form.blade.php` (skeleton hook on the city select)
- `resources/js/app.js`, `resources/js/map.js`, `resources/js/geo.js` (skeleton wiring)
- `app/DataTables/AddressDataTable.php` (processing indicator off)
- `resources/views/layouts/app.blade.php` (the page-progress bar)
- `resources/sass/app.scss` (the skeleton primitive)
- `tests/Feature/DashboardTest.php` (new)

**Does NOT touch:**
- The permission matrix, the roles, or any policy
- The Excel export behaviour or the DataTable's columns
- The auth flow

## Behaviour changes that break existing tests

`/home` currently redirects to `addresses.index`, and
`DemoLoginTest::test_a_listed_demo_account_can_actually_sign_in` asserts exactly
that. The dashboard becomes the landing page, so that assertion is updated to
expect the dashboard instead. This is a deliberate behaviour change requested by
the user, not a silently relaxed test, and it is the only existing assertion that
moves.

## Acceptance criteria

### 1. Dashboard
- [x] `/home` renders a dashboard for every signed-in user rather than redirecting
- [x] Admin sees an Access panel with user, role and permission counts
- [x] Admin and Manager see quick actions; Viewer does not
- [x] Every role sees the same four count cards, coverage figure and recent list
- [x] Public visitors still cannot reach it - `/home` stays behind `auth`
- [x] A user with no addresses sees zeroed cards, not an error

Confirmed over HTTP against the seeded database: all three roles get 200, the
Access panel appears for Admin only, and "New address" appears for Admin and
Manager but not Viewer. Figures render as 64 addresses, 8 owners, 60 cities,
16 regions, 100% coverage. Admin's Access panel reads 8 users, 3 roles.

### 2. Loading states
- [x] One shimmer primitive defined once and reused, not per-page copies
- [x] The addresses table shows skeleton rows while its ajax request is in flight
- [x] The map panel shimmers until Leaflet has drawn
- [x] The city select shimmers while its fetch is in flight
- [x] No page shows any other kind of loading indicator
- [x] Shimmer is suppressed under `prefers-reduced-motion`

The primitive is `resources/js/skeleton.js` plus the `.skeleton` rules in
`app.scss`. A surface opts in with `.skeleton-host`, one `.skeleton-overlay`
child, and a `show()`/`hide()` call - the table and the map both use it
unmodified, and neither is special-cased in the CSS.

The map's overlay needed two fixes found while wiring it: `.map` was not a
positioning context, and the overlay at `z-index: 1` would have rendered beneath
Leaflet's panes, which run up to 700. It is now `position: relative` with the
overlay at 800.

### 2a. Follow-up - the DataTables dots and full page loads

Two gaps found after the first pass:

**DataTables drew its own indicator.** `processing` was on, and DataTables 2 no
longer honours the `dom` string for that element - it appends a four-dot div
before the table whenever `processing` is true, so dropping `p` from the `dom`
string would not have removed it. Switched off at the source with
`->processing(false)` in `AddressDataTable::html()` rather than hidden with CSS.
The shimmer was already covering the same wait.

**The city select was the last non-shimmer surface.** It used a `select.is-busy`
colour change and a "Loading..." option. The picker's JavaScript moved out of an
inline `@push('scripts')` block in the form partial and into
`resources/js/geo.js`, because an inline script cannot import the shared module.
The select is now wrapped in a `.skeleton-host` with a `.skeleton-overlay--field`
child, and `select.is-busy` is gone. `geo.js` is bundled rather than lazily
imported - it is small, and it guards itself on the form's presence.

**Full page loads had nothing at all.** Signing in, saving, deleting and logging
out all leave the browser waiting with no feedback. One `div.page-progress` in
`layouts/app.blade.php` plus a delegated submit handler in `app.js` now shows a
sweep using the same `skeleton-sweep` keyframes. It is deliberately a submit
handler and not a link interceptor, so middle-click, back and modifier-clicks are
untouched. It waits 120ms before appearing so a fast response does not flash it.

Every page shares `layouts/app.blade.php`, so the bar is present on all of them,
and every page's asynchronous wait now uses the one shimmer primitive.

### 3. Regression
- [x] `php artisan test` passes
- [x] Viewer still gets 403 on create/edit/delete
- [x] Manager still cannot reach `/rbac`
- [x] Export still honours the active search filter

70 tests, 526 assertions, all passing.

## Tests updated by the behaviour change

Two existing assertions asserted the old redirect and are updated rather than
deleted:

- `DemoLoginTest::test_a_listed_demo_account_can_actually_sign_in` expected
  `/home` to redirect to `addresses.index`. It now expects the dashboard to
  render.
- `ExampleTest::test_an_authenticated_user_is_forwarded_to_the_address_book`
  expected `/` to redirect to `/addresses`. Renamed to
  `..._to_the_dashboard` and now expects `route('home')`.

These are the only two, and both move because the landing destination moved.

## Follow-ups, both now resolved

**Registration granted no role.** `RegisterController::create()` made a user
with no role at all, so a newly registered account held zero permissions while
the landing page advertised "Create an account". Fixed: `create()` now assigns
`Rbac::VIEWER_ROLE`, the least privileged of the three roles, which is the
smallest grant that makes a new account usable. Covered by
`tests/Feature/RegistrationTest.php` - the account is signed in, holds Viewer
and only Viewer, reaches `/home` and `/addresses`, and still gets 403 on
`addresses.create` and `/rbac`.

The dashboard's role-less state is kept rather than deleted: it is still the
honest render for an account whose role was revoked, and it is why `/home` is
gated on `auth` rather than `addresses.view`.

**`HomeController` and `resources/views/home.blade.php` were dead.** Both were
unrouted and unreferenced, so both are deleted.

## Implementation layers

Built directly by dev. The council chain is not runnable in this repo because
there is no `AGENTS.md` to resolve a PROJECT CONTEXT block from, which the
workspace protocol requires before any dispatch. Same constraint as the previous
spec in this directory.

1. **Skeleton primitive** - CSS plus the small JS helper
2. **Wire the three ajax surfaces** - table, map, city select
3. **Dashboard** - controller, route, views, nav
4. **Tests and verification**

## Constraints from memory/codebase

- **Visibility is not ownership-scoped.** `viewAny` is `can('addresses.view')`
  and the DataTable never filters by `user_id`, so Viewer and Manager both see
  all 64 addresses across all 8 owners. Dashboard totals therefore expose
  nothing a Viewer cannot already page through.
- **`HomeController` and `home.blade.php` were dead code**, and are now deleted.
- **Registration grants Viewer.** A self-registered account is read-only, which
  is the smallest grant that makes the landing page's sign-up path usable.
- **No `AGENTS.md`**, so no council dispatch - see above.
