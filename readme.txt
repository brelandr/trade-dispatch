=== Trade Dispatch ===
Contributors: brelandr
Tags: booking, scheduling, jobs, dispatch, field-service
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Field service management for small trades: customers, jobs, and scheduling on your own WordPress site.

== Description ==

Trade Dispatch turns a WordPress site into an office dispatch hub. Create customers, schedule jobs, and assign work to crew members (WordPress users) without sending business data to a third-party SaaS.

The free plugin is a complete web/office tool. There are no crew caps and no license checks.

Shortcodes and Gutenberg blocks:

* `[trdsp_booking]` or the **Trade Dispatch Booking** block — public booking request form
* `[trdsp_portal]` or the **Trade Dispatch Portal** block — customer portal for logged-in visitors (matched by email)

A public booking creates a WordPress subscriber (if that email is new) so the customer can log in to the portal. Estimates are office records only and do not process payments.

== Installation ==

1. Upload the `trade-dispatch` folder to `/wp-content/plugins/`.
2. Activate Trade Dispatch through the Plugins menu.
3. Open **Trade Dispatch** in the admin menu to manage jobs and customers.

== Frequently Asked Questions ==

= Does this plugin limit how many crew members I can assign? =

No. Assign jobs to any WordPress user.

= Does the free plugin phone home? =

No.

== Changelog ==

= 0.3.9 =
* Add: Services list (Trade Dispatch → Services) and optional service dropdown on the booking form. Still no charges.
* Add: Customers can Accept a sent estimate from the portal (status accepted — not a payment).

= 0.3.8 =
* Add: Portal hook after estimates so add-ons can show invoices without changing free quote records.

= 0.3.7 =
* Fix: Escape portal calendar download output and document Tested up to WordPress 7.0.

= 0.3.6 =
* Add: Open requests shortcut on the jobs screen and dashboard widget.
* Add: Portal customers can ask the office to schedule a sent estimate (no payment).
* Add: Upcoming portal visits show gate and hazard notes plus a calendar file.

= 0.3.5 =
* Add: Hook after job notes so add-ons can show field photos on job edit.

= 0.3.4 =
* Add: Customer portal splits upcoming and past visits and can request a new time (adds a job note and emails the office).
* Add: Estimates on the portal show the related job title when linked.
* Add: Month calendar on the jobs screen.
* Add: Optional business name for emails and printed work orders.

= 0.3.3 =
* Add: Email an estimate to the customer (quote only; draft estimates are marked sent).
* Add: Recurring jobs view on the jobs screen.
* Add: Printable work order from job edit.
* Add: Add a job from an empty (or filled) calendar day.

= 0.3.2 =
* Add: Filter jobs by crew member and search by title or address.
* Add: Duplicate a job from the jobs list (notes are not copied).
* Add: Customer edit screen lists that customer’s jobs and can open a new job pre-filled for them.
* Add: WordPress dashboard widget for today’s jobs.
* Add: Email the assigned crew member (and the office) when a job is assigned or reassigned.

= 0.3.1 =
* Add: Create a scheduled job from an estimate (links the records and copies the customer address when present).

= 0.3.0 =
* Add: Estimates admin CRUD, job notes, week calendar (uses the site start-of-week), Gutenberg blocks for booking and portal, portal estimates, and a subscriber account on first booking so the portal works without a manual user.

= 0.2.0 =
* Add: Customer and job admin CRUD, crew assignment, recurring visits, booking and portal shortcodes, WordPress mail notifications, GDPR export/erase, and portal REST.

= 0.1.0 =
* Add: Initial customers, jobs, estimates, and notes tables plus admin screens.

== Upgrade Notice ==

= 0.1.0 =
Initial release.

== Privacy Policy ==

Trade Dispatch stores customer names, contact details, job addresses, service notes, estimates, and (on first booking) a WordPress subscriber account in this site’s database so the business can schedule field work and the customer can open the portal.
