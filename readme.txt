=== Trade Dispatch ===
Contributors: brelandr
Tags: booking, scheduling, jobs, dispatch, field-service
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.37
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

= 0.3.37 =
* Add: {time_line} on job emails (empty unless an add-on fills it) so complete/confirm mail can mention time on site without changing free job records.

= 0.3.36 =
* Add: {invoice_line} on job emails (empty unless an add-on fills it) so complete/confirm mail can mention a portal invoice without changing free job records.

= 0.3.35 =
* Add: Filter on each job in the crew tomorrow digest so add-ons can append checklist progress without changing free job records.

= 0.3.34 =
* Add: {photos_line} on job emails (empty unless an add-on fills it). Filter trdsp_mail_job_vars so add-ons can mention portal photos without changing free job records.

= 0.3.33 =
* Add: Past portal visits open the same details as upcoming ones. Hook after visit media so add-ons can show field photos without changing free job records.

= 0.3.32 =
* Add: Hook after work-order notes so add-ons can print photos, time, and parts without changing free job records.

= 0.3.31 =
* Add: Hook after each dashboard job line so add-ons can show on the way / on site without changing free job records.

= 0.3.30 =
* Add: Portal hooks after visit status and details so add-ons can show a technician on-site note.

= 0.3.29 =
* Add: Hook after work-order details so add-ons can print a tech check-in without changing free job records.

= 0.3.28 =
* Add: Hooks after Jobs list headers/status cells and after the dashboard’s today’s jobs so add-ons can show check-in without changing free job records.

= 0.3.27 =
* Add: Latest note column on the Jobs list so office notes from the field show without opening each job.

= 0.3.26 =
* Add: Requests inbox shows the customer’s portal or booking note. Estimate schedule requests can include a note for the office.

= 0.3.25 =
* Add: Send test to me and Restore default on each Emails template. The test goes to your WordPress account address.

= 0.3.24 =
* Add: Print estimate from the list or edit screen (browser print, same as a work order). Not a PDF.

= 0.3.23 =
* Add: Requests inbox with a red count on the Trade Dispatch menu. Approve or decline booking, time-change, and estimate-schedule requests and email the customer.
* Add: Emails screen to edit every Trade Dispatch email (plain text, placeholders). Leave a field blank to use the default.

= 0.3.22 =
* Add: Customer portal page on Settings. Customer-only logins go there. Booking, confirm, and estimate emails include the link when a page is chosen.

= 0.3.21 =
* Fix: Trade Dispatch role checkboxes on the user profile now stick after Update User. They were saved before WordPress applied the Role dropdown, which cleared them.

= 0.3.20 =
* Add: Send reminder on a sent estimate (re-emails the customer and office). Does not fire the first-send hook.

= 0.3.19 =
* Add: Customer, Employee, and Dispatcher roles. They can be the only role or added next to Administrator. Employees see assigned jobs only. Dispatchers run the office without editing pages, themes, or plugins.

= 0.3.18 =
* Add: Portal and printed work orders show a pending requested time.
* Add: Assigned crew get a tomorrow job list by email (same daily cron as recurring jobs).

= 0.3.17 =
* Add: Time requests shortcut on Jobs and the dashboard. Apply this time from the list.

= 0.3.16 =
* Add: Email the customer (and office) when the office applies a portal-requested time.

= 0.3.15 =
* Add: Suggested days (date-only chips) on booking and the portal reschedule form. Still a request.
* Add: Apply requested time on job edit after a portal reschedule.

= 0.3.14 =
* Add: Suggested visit times on the customer portal reschedule form (same chips as booking; still a request).

= 0.3.13 =
* Add: Suggested visit times on the public booking form (open hours, skip busy times). Still a request, not a locked slot engine.

= 0.3.12 =
* Add: Optional booking open/close hours and weekdays on Settings. The public form warns (does not block) if the preferred time is outside those hours or already has a scheduled visit.

= 0.3.11 =
* Add: Confirm a booking request from the job edit screen.
* Add: Optional booking hours note on Settings, shown under the preferred time on the public form.
* Add: Service on a job (from booking or office) and typical visit minutes for overlap warnings.

= 0.3.10 =
* Add: Confirm a booking request from the jobs list (sends a visit-confirmed email).
* Add: Warn (do not block) when a crew member already has a job at the same time.
* Add: Accepted estimates shortcut, Create job on the list, and a dashboard count for estimates that still need a job.

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
