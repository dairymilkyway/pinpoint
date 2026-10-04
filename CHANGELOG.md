# Changelog

2026-10-04 feature:premium-chrome-and-alignment - the top bar is now pinned to the top of the window and frosted, over a sidebar that sticks and scrolls on its own; the public landing page gets the same surface plus the light/dark switch it had been missing. Two alignment fixes ride along: the sign-in page's map panel lines up with the card again, and the dashboard map now fills its panel instead of leaving a gap under it.

2026-10-04 feature:login-fit-and-toasts - the sign-in page fits one screen: the archipelago panel is height-capped instead of stretched to the card, and the demo account picker is a compact row of three instead of three tall cards. Every flash message now arrives as a toast in the bottom-right corner, so a message no longer pushes the page down.

2026-10-04 feature:pinpoint-rename-and-plain-copy - the product is renamed Pinpoint, with a crosshair mark in all eight places the brand appears, the "address book" wording removed everywhere a reader could see it, and the user-facing copy on every screen rewritten in plain English (the coverage hint now says "are on the map" instead of "have coordinates").

2026-10-04 feature:directory-table-and-requests - the addresses directory drops its Map and Default columns (the default marker now rides inline beside the name), its toolbar buttons are full size and aligned, and each request row shows the requester's reason instead of keeping it behind the Review button.

2026-10-04 feature:user-deactivation - a Superadmin can deactivate an account from the Roles & permissions screen and reactivate it from the same row. Deactivating blocks sign-in only: the account's addresses stay in the book, keep their owner's name, and stay reachable from the directory. The import page fills the page width, and Export to Excel now carries the same box as New address and Import Excel.

2026-10-04 feature:rbac-audit-log - the four Roles & permissions actions now write to the audit log: a role change, an account deactivation, an account reactivation, and a permission-matrix save (one entry per role whose set actually moved). A refused action and a save that changes nothing record nothing, so the log holds acts rather than attempts.

2026-10-04 feature:customer-import-proposals - a Customer's own addresses page now carries New address, Import Excel and Export to Excel; the first two file a request instead of writing, so a single new address or a whole imported spreadsheet waits as one entry in the reader's queue until it is approved or rejected, and a Customer can export the rows they can already see.

2026-10-04 feature:dashboard-focus-and-chrome - the setup, create, edit and request panels now sit centred instead of hard against the left edge, destructive confirm buttons (Deactivate, Delete, Reject) read as buttons before you hover them, the lead dashboard figure carries a twelve-week sparkline of addresses on file, a Customer gets the By region chart over their own rows without losing their request list, and clicking a region bar narrows the map to just that region's pins with a caption and a Show all way back.
