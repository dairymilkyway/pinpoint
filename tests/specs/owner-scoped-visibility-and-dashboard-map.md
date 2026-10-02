# Feature: Owner-scoped visibility and a dashboard map

## What it does

Until now every role saw every address. Admin keeps that; Manager and Viewer are
scoped to the addresses they own, everywhere - the table, the map, the export and
the dashboard counts. The dashboard also gains a map of the signed-in user's own
pins, which is narrower still: it is personal even for an Admin.

## Correction to the assumption behind the request

The request asked whether users only see their own addresses. They did not, and
nothing in the code said they should:

- `AddressPolicy` was a flat permission check with no ownership clause on `view`,
  `update` or `delete`.
- `AddressDataTable::query()` was `Address::newQuery()->with('user')`, unfiltered.
- `AddressController::map()` returned every row with coordinates.

The seeded data (8 owners, 64 addresses) and the table's Owner column were built
on the shared-directory reading. Both decisions below are the user's, taken after
that was pointed out.

## Decisions taken

1. **Admin sees all; Manager and Viewer see only their own.** Not a new
   permission - a role check. Adding one would put a row in the RBAC matrix that
   cannot express "all", so it would be a checkbox that means nothing.
2. **The dashboard map pins only the signed-in user's addresses**, including for
   Admin. It is a personal panel, not a second directory view.

## Scope

**Touches:**
- `app/Models/User.php` (one predicate)
- `app/Models/Address.php` (one query scope)
- `app/Policies/AddressPolicy.php` (ownership on view/update/delete)
- `app/DataTables/AddressDataTable.php` (scoped query, which the export inherits)
- `app/Http/Controllers/AddressController.php` (scoped map)
- `app/Http/Controllers/DashboardController.php` (scoped figures, new own-pins map)
- `routes/web.php` (the dashboard map endpoint)
- `resources/views/dashboard/index.blade.php` (map panel, card set per role)
- `resources/js/map.js` (owner line becomes optional)
- tests

**Does NOT touch:**
- Role definitions or the permission list
- The form, the geo lookup, or the create/update path
- The addresses page layout

## Design

**One definition of "visible".** The rule lives in exactly two places - a
predicate on `User` and a scope on `Address` - and every read path goes through
the scope. Repeating a `when(! $user->isAdmin(), ...)` clause in four call sites
is how the table and the export drift apart.

**The policy and the query agree.** The scope decides what is listed; the policy
decides what can be opened directly by id. Both consult the same predicate, so a
row hidden from the table cannot be reached by editing the URL.

**Admin-only card.** "Owners" is meaningless once a user is scoped to themselves
(it would always read 1), so Manager and Viewer get three cards instead of four.
`Owners` is dropped rather than replaced with an invented metric.

## Acceptance criteria

### 1. Visibility
- [x] Admin's table, map and export still cover every address
- [x] A Manager or Viewer only ever sees rows they own in the table
- [x] The same scoping applies to the map endpoint and to the Excel export
- [x] Editing another owner's address by URL is forbidden, not just hidden
- [x] Viewer keeps 403 on create; Manager keeps 403 on delete

### 2. Dashboard
- [x] Counts, coverage and the recent list are scoped to what the user can see
- [x] Admin still sees the Owners card; Manager and Viewer do not
- [x] The dashboard map pins only the signed-in user's addresses, Admin included
- [x] A user with no pinned addresses sees no map panel
- [x] The Access panel stays Admin-only

### 3. Regression
- [x] `php artisan test` passes
- [x] The seeded demo accounts still land on a working dashboard

Confirmed over HTTP against the seeded database. Directory map and table rows:
Admin 64, Manager 8, Viewer 8. Dashboard map: 8 for all three, Admin included -
which is the point of it being personal. Owners card present for Admin only.
Manager and Viewer both get 403 on an Admin-owned address by URL, and the Admin
gets 200 on the same one.

The seeder already spreads 64 addresses evenly across the 8 accounts at 8 each,
so every demo login still has a populated directory and a populated map. No
seeder change was needed.

## Behaviour changes that break existing tests

Two existing tests assert the old shared-directory behaviour and are updated
rather than deleted. Both move because the rule changed, not to make a failure go
away:

- `AddressAuthorizationTest::test_manager_can_create_and_edit_but_cannot_delete`
  edits an address the manager does not own. Under scoping that must now be
  forbidden, so the test is split into "edits their own" and "cannot edit
  someone else's".
- `AddressDataTableTest`'s Viewer and Manager row-action tests read `data.0` from
  a user holding no addresses, which is now an empty page. The address must be
  owned by the user under test.

## Implementation layers

Built directly by dev. There is no `AGENTS.md` in this repo and no
`council.config.json`, so the workspace protocol blocks any agent dispatch - the
same constraint the two previous specs in this directory recorded.

1. `User::seesEveryAddress()` + `Address::scopeVisibleTo()`
2. Policy ownership
3. Scope the table, map and export
4. Scope the dashboard figures, drop the Owners card for non-Admin
5. Dashboard map endpoint, panel and the optional owner line
6. Tests and verification

## Constraints from memory/codebase

- **No `AGENTS.md`**, so no council dispatch - see above.
- **`store()` already assigns ownership** to the creator, so a scoped user's own
  new addresses are visible to them immediately.
- **No user-facing role management change.** The RBAC page is untouched.
