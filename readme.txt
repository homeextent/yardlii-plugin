Here is the `readme.txt` file content with all citations removed.

```
=== YARDLII Core Functions ===
Contributors: yardlii, theinnovativegroup
Tags: acf, facetwp, wpuf, listings, maps, admin-tools, feature-flags, automation, roles, access-control, badges
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 3.7.1
License: GPLv2 or later.
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The YARDLII Core Functions plugin provides the modular engine for advanced real estate listing systems, admin automation, and dynamic feature control — built with ACF, FacetWP, and WPUF integrations.

== Description ==

YARDLII Core Functions is the backbone of the YARDLII platform — a modular, expandable framework designed for enterprise-grade WordPress automation.
It powers all major YARDLII components through a clean, extensible architecture with centralized controls, admin branding, and modular settings management.

== Core Features ==

* Google Maps API key management + diagnostics
* Automated featured image assignment (from WPUF or ACF gallery)
* Homepage search form with FacetWP binding
* Optional ACF → User field synchronization (toggleable)
* Role Control area with:
    * Submit Access Control (front-end page gating by role)
    * Custom User Roles (define roles by cloning base caps + adding extra caps)
    * Badge Assignment (map roles → ACF Options image fields; store image ID in user meta)
* Dynamic Feature Flag system (enable/disable modules individually)
* Scoped admin tab system (Main tabs / Subtabs / Inner tabs)
* Built-in debug, logging, and diagnostics mode
* YARDLII-branded admin interface with consistent UX styling

== Role Control ==

Location: Settings → YARDLII Core → Role Control

The Role Control area contains independent features gated by the Role Control master flag (Advanced → Feature Flags).
In “Show-but-Locked” mode the tab remains visible, but inputs and handlers are disabled when the master is OFF.

=== 1) Submit Access Control ===

Limit access to a front-end page by role.

* **Settings (group: yardlii_role_control_group):**
* `yardlii_enable_role_control_submit` (bool)
* `yardlii_role_control_target_page` (slug, default submit-a-post)
* `yardlii_role_control_allowed_roles[]` (multi-select of editable roles)
* `yardlii_role_control_denied_action` (message | redirect_login)
* `yardlii_role_control_denied_message` (text; used when action = “message”)

* **Behavior:**
* If no roles are selected, any logged-in user may access the target page.
* Otherwise, only users with a selected role may access; others see the message or are redirected to the login page.

=== 2) Custom User Roles ===

Create/update/remove custom roles.

* **Settings (group: yardlii_role_control_group):**
* `yardlii_enable_custom_roles` (bool)
* `yardlii_custom_roles` (array of roles)
* Each role entry: slug (key), label (display name), base_role (to clone caps from), extra_caps (comma or space separated)

* **Behavior:**
* On save, roles are created (add_role) or updated (diff caps, update display name).
* Removing a row and saving removes the WP role.
* Users with only that role are reassigned to Subscriber.
* Reserved roles (administrator, etc.) cannot be created/removed.

=== 3) Badge Assignment ===

Map roles to ACF Options image fields and sync to user meta.

* **Settings (group: yardlii_role_control_badges_group, isolated):**
* `yardlii_enable_badge_assignment` (bool)
* `yardlii_rc_badges` (array)
* `meta_key` (string; default user_badge)
* `fallback_field` (string; optional)
* `rows[][role]` + `rows[][field]` (repeater UI)

* **Behavior:**
* Syncs on role change, profile update, login, add/remove role.
* Picks the first mapped role from the user’s roles; otherwise uses fallback (if set).
* Reads the ACF Options field (recommended return format ID).
* Saves the attachment ID to the configured user meta key.
* "Resync All Users" action (admin-post; chunked). Refactored in 3.6.0 to use Action Scheduler for reliable background processing.
* Profile preview (thumbnail on user profile screens).
* Optional Users list “Badge” column (thumbnail; sortable by attachment ID).

== Feature Flags (Advanced) ==

Location: Settings → YARDLII Core → Advanced → Feature Flags

* `yardlii_enable_role_control` → master switch for Role Control
    * Mode: Show-but-Locked
    * OFF: Role Control tab stays visible but is read-only; no runtime effects.
    * ON: Role Control fully interactive; runtime effects gated by each feature’s toggle.
* `yardlii_enable_acf_user_sync` → ACF User Sync

* **Constant overrides (optional):**
* `define('YARDLII_ENABLE_ROLE_CONTROL', true);` // hard-enable Role Control
* `define('YARDLII_ENABLE_ACF_USER_SYNC', false);` // hard-disable ACF user sync

== Installation ==

1.  Upload the `yardlii-core-functions` folder to `/wp-content/plugins/`
2.  Activate the plugin through the Plugins menu in WordPress
3.  Go to Settings → YARDLII Core to configure your modules

== Frequently Asked Questions ==

* **Can I add my own modules?**
    Yes. Add a feature class under `/includes/Features/` and wire it in the Loader. Add a settings partial under `/includes/Admin/views/partials/`. If your feature shares a screen, consider an isolated settings group.

* **How do I add a new top-level tab with subtabs?**
    Add a main button to the nav (`<button class="yardlii-tab" data-tab="my-feature">...`) and a panel (`<section ... data-panel="my-feature">...`). Inside, add subtabs and `<details>` sections with a new scope (e.g., `data-msection`). Hook the logic in `assets/js/admin.js`.

* **Can I disable Badge Assignment or ACF User Sync?**
    Yes. Toggle them off in Advanced → Feature Flags, or hard-disable via constants in wp-config.php.

* **What happens if I disable Role Control (master flag OFF)?**
    The Role Control tab remains visible but is locked (read-only). No Submit Access gating, Custom Role syncing, or Badge syncing occurs until you turn it back ON.

== Screenshots ==

1.  Role Control tab (Submit Access, Custom User Roles, Badge Assignment)
2.  Badge Assignment mapping UI (repeater rows)
3.  Users list with Badge column & profile badge preview

== Changelog ==

= 3.7.1 =
* Feat: Restructured 'Advanced' tab with sub-tab navigation (Flags & Diagnostics).
* Feat: Created new 'Diagnostics' panel and migrated Trust & Verification diagnostics to it.
* Refactor: Removed diagnostics panel from the 'Trust & Verification' tab.

= 3.7.0 =
* Feat: Refactored Trust & Verification decision logic into a dedicated `TvDecisionService`.
* Feat: Added `yardlii_tv_decision_made` action hook to allow other code to react after a verification decision is applied.
* Refactor: Created a `TvProviderRegistry` to cleanly manage and load form providers (e.g., WPUF, Elementor).

= 3.6.0 - 2025-11-07 =
* Feature: Refactored the "Resync All Users" badge assignment to use Action Scheduler for reliable background processing.
* Feature: This prevents server timeouts on large sites by queuing individual user sync jobs.
* Dev: Added `woocommerce/action-scheduler` as a Composer dependency.

= 3.5.0 - 2025-11-06 =
* New: Added an `uninstall.php` routine to remove all plugin data on deletion (CPTs, options, meta).
* New: Added a "Remove all data on deletion" safety toggle in the Advanced tab to control the uninstall routine.
* Test: Added integration smoke test for the feature Loader to ensure flags are respected.
* Fix: Corrected logic bug in `Caps::userCanManage` method.
* Dev: Completed full unit test coverage for `Mailer`, `Caps`, and `Templates`.
* Dev: Integrated PHPStan (Level 6) into the CI pipeline for static analysis.

= 3.4.0 — 2025-11-04 =
* CI: PHPUnit + PHPStan gates in place (green baseline).
* Tests: Mailer/Placeholders/Caps + AJAX happy path.
* New: Diagnostics Panel (MVP) with “Send self-test email”.
* Email polish: final filters + placeholders; stable headers.
* Security/UX: strict nonces/caps on AJAX + clearer notices.

= 3.2.0 =
* New: Role Control → Badge Assignment (role → ACF field mapping; stores image ID in user meta; sync on role/profile/login).
* New: Badge panel repeater UI (role dropdown + field name; add/remove rows; duplicate collapse on save).
* New: Resync All Users action (admin-post; chunked).
* New: Profile badge preview on user profile screens.
* New (optional): Users list “Badge” column (thumbnail; sortable by attachment ID).
* Change: Badge settings moved to an isolated settings group to prevent form collisions.
* Change: Consolidated admin tab logic in admin.js (removed legacy duplication).
* A11y/UX: ARIA on tabs/sections; arrow-key navigation; locked panel banner.

= 3.1.0 =
* Introduced full Feature Flags System (UI + constant override)
* Added Advanced tab controls for per-feature toggling
* Rebuilt JavaScript tab architecture with scoped handlers
* Added General tab subtabs (Google Map, Featured Image, Homepage Search, WPUF)
* Restored inner horizontal tabs for Google Map Settings
* Improved CSS consistency and accessibility across admin UI
* Deprecated direct ACFUserSync loading (now behind feature flag)

= 3.0.0 =
* Introduced modular admin settings tabs
* Added AJAX test + reset tools for Featured Image Automation
* Improved Homepage Search with FacetWP binding
* Added YARDLII-branded UI styling

== Upgrade Notice ==

= 3.2.0 =
3.2.0 introduces the Badge Assignment feature under Role Control, plus settings isolation improvements.
If you previously customized tabs.js, remove it and rely on assets/js/admin.js.
After upgrading, visit Role Control → Badge Assignment to configure mappings, then use Resync All Users to backfill badges.

== Developer Notes ==

* **Feature registration (Loader):**
    * Guard features by master AND per-feature toggle:
    * `if ($rc_master && (bool) get_option('yardlii_enable_role_control_submit', false)) { ... }`
    * `if ($rc_master && (bool) get_option('yardlii_enable_custom_roles', true)) { ... }`
    * `if ($rc_master && (bool) get_option('yardlii_enable_badge_assignment', true)) { ... }`

* **Settings registration:**
    * Use `register_setting()` with sanitize callbacks; for booleans use (bool) $v plus the hidden 0 input.
    * When two forms live on the same page, use separate settings groups to avoid collisions.

* **Admin UI patterns:**
    * Top-level tabs: `.yardlii-tab[data-tab]` ↔ `.yardlii-tabpanel[data-panel]`
    * Subtabs per panel: `.yardlii-tabs.yardlii-<scope>-subtabs` + buttons with `data-<scope>section`
    * Sections use `<details class="yardlii-section" data-<scope>section="...">`
    * A11y: update aria-selected, aria-hidden, tabIndex; arrow-key support.

* **JS consolidation:**
    * All tab handlers live in `assets/js/admin.js`.
    * Do not reintroduce `tabs.js` — it caused duplicate bindings and state conflicts.

== License ==

GPLv2 or later
Copyright © 2025
The Innovative Group / YARDLII
```