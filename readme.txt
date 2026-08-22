=== Trade Dispatch ===
Contributors: brelandr
Tags: booking, scheduling, jobs, dispatch, field-service
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.53
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Field service management for small trades: customers, jobs, and scheduling on your own WordPress site.

== Description ==

Trade Dispatch turns a WordPress site into an office dispatch hub. Create customers, schedule jobs, and assign work to crew members (WordPress users) without sending business data to a third-party SaaS.

The free plugin is a complete web/office tool. There are no crew caps and no license checks.

Roles (in addition to Administrator):

* **Customer** — created when the office confirms a booking (if that email is new), so the visitor can log in to the portal. An existing WordPress user with the same email is reused (including a Subscriber). The public form does not create an account.
* **Employee** — sees assigned jobs only.
* **Dispatcher** — runs the office (jobs, customers, estimates) without editing pages, themes, or plugins.

Shortcodes and Gutenberg blocks:

* `[trdsp_booking]` or the **Trade Dispatch Booking** block — public booking request form
* `[trdsp_portal]` or the **Trade Dispatch Portal** block — customer portal for logged-in visitors (matched by email)

A public booking stores a customer record and a requested job. A Customer-role WordPress account is created only after the office confirms the visit (if that email is new), so the public form cannot create users or send new-account emails. Estimates are office records only and do not process payments.

Assigned crew members may receive a next-day job list by email (title, time, and address) at their WordPress user email. Trade Dispatch → Emails lets you edit every plugin message (plain text, placeholders). Leave a field blank to use the default.

Plugin home: https://tradedispatch.app
Unminified source: https://github.com/brelandr/trade-dispatch

== Try It Live - Preview This Plugin Instantly ==

Open WordPress Playground with Trade Dispatch installed from WordPress.org. The homepage is a full-width shop pack (hero, services, and a full-bleed `[trdsp_booking]` form). Sample customers and jobs are already in the office. Sign in as **admin** / **password**, then use **Office** in the header — or open **Trade Dispatch** in wp-admin — for Jobs, Customers, Estimates, and Settings.

[Preview on WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/brelandr/trade-dispatch/main/blueprint.json)

The same blueprint ships in the plugin package as `blueprint.json` (repository root) and `assets/blueprints/blueprint.json`. After this listing is live, WordPress.org can also run the copy under the plugin’s directory assets for Live Preview.

== Screenshots ==

1. Jobs list — scheduled, in-progress, and requested work with customer and time.
2. Job edit — address, gate notes, hazards, assignee, and office brief.
3. Customers — names, phones, and property addresses on your WordPress site.
4. Public booking form (`[trdsp_booking]`) — visitors request a visit without creating an account.
5. Settings — business name, booking hours, and the customer portal page.

== Installation ==

1. Upload the `trade-dispatch` folder to `/wp-content/plugins/`.
2. Activate Trade Dispatch through the Plugins menu.
3. Open **Trade Dispatch** in the admin menu to manage jobs and customers.

== Frequently Asked Questions ==

= Does this plugin limit how many crew members I can assign? =

No. Assign jobs to any WordPress user.

= Does the public booking form create a WordPress user? =

No. A public request stores a customer record and a requested job only. After the office confirms the visit, Trade Dispatch creates a Customer-role account (if that email is new) so the customer can open the portal. Existing WordPress users with the same email are reused.

= Does the free plugin phone home? =

No.

= The booking form or portal says my request could not be sent. What is wrong? =

The public forms use a WordPress security token that can expire when the page is served from a full-page cache. If visitors see a "please try again" message, exclude the page that holds `[trdsp_booking]` and the page that holds `[trdsp_portal]` from your caching plugin (or your host's page cache). The request is never lost — the visitor just re-submits.

== Changelog ==

= 0.3.53 =
* Fix: Job and estimate list queries splat prepare() replacements. Latest-note lookup uses a two-placeholder join so Plugin Check can count replacements.

= 0.3.52 =
* Fix: Settings portal page dropdown is escaped at output. List queries pass prepare() without an intermediate SQL variable so Plugin Check stops flagging them.

= 0.3.51 =
* Docs: Collapse 0.3.42 through 0.1.0 into an Earlier releases note so the WordPress.org changelog stays under the size guideline.

= 0.3.50 =
* Docs: Portal REST class and permission callback document that these routes are web-only (not field-tech).

= 0.3.49 =
* Fix: Test-email sample name, address, and crew digest line are translatable.
* Fix: Portal loads the same localized booking script as the public form.
* Fix: Requests menu badge caches the inbox count for one minute and clears it when jobs, preferred times, or estimate requests change.

= 0.3.48 =
* Fix: Delete, decline, and restore-template confirms use an enqueued admin script and data attributes instead of inline onclick.

= 0.3.47 =
* Fix: Custom-table SELECT, COUNT, and uninstall DROP queries pass the table name through $wpdb->prepare() %i (WordPress 6.2+). dbDelta schema statements are unchanged.

= 0.3.46 =
* Fix: Portal and admin-post handlers verify the nonce before reading record IDs and require current_user_can on portal writes.
* Fix: Service default amount is sanitized before cast. Customer count and service list queries use $wpdb->prepare().

= 0.3.45 =
* Add: Create booking and portal pages from Settings (separate from the options form).
* Add: Jobs list customer filter and an Add estimate link on job edit.
* Fix: Deleting a customer deletes that customer’s jobs (and notes) first.
* Fix: GDPR export and erase paginate jobs so large accounts finish.
* Fix: Portal REST requires a logged-in user with portal or office access.

= 0.3.44 =
* Add: TRDSP_Requests::decide() so the companion office app can approve or decline booking, time, and estimate requests with the same customer emails as wp-admin.

= 0.3.43 =
* Fix: Public booking no longer creates a WordPress user or sends a new-account email. The Customer-role portal account is created when the office confirms the visit. Booking submissions are rate-limited by IP and email.
* Add: .distignore and create-plugin-zip.sh so Cursor files, deploy-to-hub.sh, and README.md stay out of the WordPress.org package.
* Fix: Services Default minutes uses a number input. Uninstall checkbox text matches the opt-in (tables, templates, settings, and roles).

= Earlier releases =

0.3.42 through 0.1.0 added customers, jobs, estimates, notes, booking and portal shortcodes/blocks, Customer/Employee/Dispatcher roles, the Requests inbox, the Emails screen, services, suggested visit times, recurring jobs, printed work orders, crew tomorrow emails, GDPR export/erase, and extension hooks (including email placeholders) so add-ons can show invoices, photos, time, and checklists without changing free records.

== Upgrade Notice ==

= 0.3.47 =
Custom-table queries use the WordPress 6.2 identifier placeholder.

= 0.3.46 =
Nonce-first inbound handlers and tighter portal capability checks.

= 0.3.45 =
Office setup pages, customer-job cleanup, and paginated privacy tools.

= 0.1.0 =
Initial release.

== Privacy Policy ==

Trade Dispatch stores customer names, contact details, job addresses, service notes, estimates, and a WordPress account (Customer role when the office confirms a booking, or an existing Subscriber) on this site so the business can schedule work and the customer can open the portal. The public booking form does not create a WordPress user. Optional Employee and Dispatcher roles let crew work assigned jobs or run the office without editing pages or plugins. Booking form submissions are emailed to the site owner with WordPress mail. Assigned crew members may receive a tomorrow job list (title, time, and address) at their WordPress user email. Site owners can edit those messages (and other Trade Dispatch emails) under Trade Dispatch → Emails; templates stay in this site’s options. Data stays on this WordPress installation unless the site owner connects a separately installed premium add-on.
