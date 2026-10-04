# Changelog

2026-10-04 feature:directory-table-and-requests - the addresses directory drops its Map and Default columns (the default marker now rides inline beside the name), its toolbar buttons are full size and aligned, and each request row shows the requester's reason instead of keeping it behind the Review button.

2026-10-04 feature:user-deactivation - a Superadmin can deactivate an account from the Roles & permissions screen and reactivate it from the same row. Deactivating blocks sign-in only: the account's addresses stay in the book, keep their owner's name, and stay reachable from the directory. The import page fills the page width, and Export to Excel now carries the same box as New address and Import Excel.

2026-10-04 feature:rbac-audit-log - the four Roles & permissions actions now write to the audit log: a role change, an account deactivation, an account reactivation, and a permission-matrix save (one entry per role whose set actually moved). A refused action and a save that changes nothing record nothing, so the log holds acts rather than attempts.

2026-10-04 feature:customer-import-proposals - a Customer's own addresses page now carries New address, Import Excel and Export to Excel; the first two file a request instead of writing, so a single new address or a whole imported spreadsheet waits as one entry in the reader's queue until it is approved or rejected, and a Customer can export the rows they can already see.
