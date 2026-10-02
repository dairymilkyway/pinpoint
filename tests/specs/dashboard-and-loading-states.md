# Feature: Role dashboards and global loading states

## What it does

Adds a landing dashboard that every signed-in user gets, with panels that change
according to what their role can do, and introduces one reusable skeleton
shimmer so the ajax surfaces stop flashing blank while they wait.

## Correction to the request

The request assumed Manager and Viewer already had a dashboard. They do not, and
neither does Admin. `/home` is a closure that redirects every role straight to
`/addresses`. `HomeController` and `resources/views/home.blade.php` exist but
nothing routes to them; the view is the stock "You are logged in!" placeholder.
This is therefore new work for all three roles, not an extension.

## Scope

**Touches:**
- `app/Http/Controllers/DashboardController.php` (new)
- `routes/web.php` (`/home` stops redirecting and renders the dashboard)
- `resources/views/dashboard/index.blade.php` and its partials (new)
- `resources/views/layouts/app.blade.php` (nav gains Dashboard)
- `resources/views/addresses/index.blade.php` (skeleton hooks on table and map)
- `resources/views/addresses/partials/form.blade.php` (skeleton hook on the city select)
- `resources/js/app.js`, `resources/js/map.js` (skeleton wiring)
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
- [x] The city select shows a busy state while its fetch is in flight
- [x] Shimmer is suppressed under `prefers-reduced-motion`

The primitive is `resources/js/skeleton.js` plus the `.skeleton` rules in
`app.scss`. A surface opts in with `.skeleton-host`, one `.skeleton-overlay`
child, and a `show()`/`hide()` call - the table and the map both use it
unmodified, and neither is special-cased in the CSS.

The map's overlay needed two fixes found while wiring it: `.map` was not a
positioning context, and the overlay at `z-index: 1` would have rendered beneath
Leaflet's panes, which run up to 700. It is now `position: relative` with the
overlay at 800.

### 3. Regression
- [x] `php artisan test` passes
- [x] Viewer still gets 403 on create/edit/delete
- [x] Manager still cannot reach `/rbac`
- [x] Export still honours the active search filter

66 tests, 509 assertions, all passing.

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

## Known gaps left in place

**Registration grants no role.** `RegisterController::create()` makes a user
with no role at all, so a newly registered account holds zero permissions. The
landing page advertises "Create an account", so that path currently ends with an
account that can see nothing. This predates the dashboard - previously `/home`
redirected to `/addresses`, which also 403s a role-less user - but the dashboard
now makes it visible instead of silent.

The dashboard deliberately does not 403 in that case; it renders an explanation
instead, which is why it is gated on `auth` rather than `addresses.view`.
Assigning the Viewer role on registration would remove the gap, but that changes
what a new account can reach, so it is left for an explicit decision.

**`HomeController` and `resources/views/home.blade.php` are now unused** as well
as unrouted. Deleting files needs approval, so both are left in place.

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
- **`HomeController` and `home.blade.php` are dead code.** Left in place because
  deleting files needs explicit approval. Flagged for a follow-up decision.
- **No `AGENTS.md`**, so no council dispatch - see above.
