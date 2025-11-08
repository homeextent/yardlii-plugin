# 🧩 YARDLII Core Functions

[![WordPress Tested](https://img.shields.io/badge/tested_up_to-6.8-blue.svg)](https://wordpress.org/plugins/)
[![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![YARDLII](https://img.shields.io/badge/YARDLII-Core%20Platform-blueviolet.svg)](https://yardlii.com)

> The **YARDLII Core Functions** plugin provides the modular engine for advanced real estate listing systems, admin automation, and dynamic feature control — built with ACF, FacetWP, and WPUF integrations.

---

## 🧠 Description

**YARDLII Core Functions** is the backbone of the YARDLII platform — a modular, expandable framework designed for enterprise-grade WordPress automation.

It powers all major YARDLII components through a clean, extensible architecture with centralized controls, admin branding, and modular settings management.

### 🔧 Core Features

- 🗺️ **Google Maps API** key management + diagnostics  
- 🖼️ **Automated featured image** assignment (from WPUF or ACF gallery)  
- 🔍 **Homepage search form** with FacetWP binding  
- 👤 **ACF → User field synchronization** (toggleable)  
- 🧩 **Feature Flag system** — enable/disable modules individually  
- 🧭 **Scoped admin tabs** (Main / Sub / Inner)  
- ⚙️ **Built-in debug**, logging, and diagnostics  
- 🎨 **YARDLII-branded admin interface** with consistent UX styling  

---

## 📦 Changelog
## 3.7.3
* **Chore**: Removed 'ACF User Sync' feature from the UI and admin panel [cite: 2773-2776, 2824-2825, 2828-2829].
* **Note**: The feature is now considered deprecated and is only accessible if the `YARDLII_ENABLE_ACF_USER_SYNC` constant is defined [cite: 817-823].

= 3.7.2 =
* Fix: Corrected JavaScript typo that prevented 'Advanced' tab sections from opening.

## 3.7.1
* **Feat**: Restructured 'Advanced' tab with sub-tab navigation (Flags & Diagnostics).
* **Feat**: Created new 'Diagnostics' panel and migrated Trust & Verification diagnostics to it.
* **Refactor**: Removed diagnostics panel from the 'Trust & Verification' tab.

## 3.6.0 - 2025-11-07
* **Feature**: Refactored the "Resync All Users" badge assignment to use Action Scheduler for reliable background processing. This prevents server timeouts on large sites by queuing individual user sync jobs.
* **Dev**: Added `woocommerce/action-scheduler` as a Composer dependency.

## 3.5.0 - 2025-11-06
* **New**: Added an `uninstall.php` routine to remove all plugin data on deletion (CPTs, options, meta).
* **New**: Added a "Remove all data on deletion" safety toggle in the Advanced tab to control the uninstall routine.
* **Test**: Added integration smoke test for the feature Loader to ensure flags are respected.
* **Fix**: Corrected logic bug in `Caps::userCanManage` method.
* **Dev**: Completed full unit test coverage for `Mailer`, `Caps`, and `Templates`.
* **Dev**: Integrated PHPStan (Level 6) into the CI pipeline for static analysis.

## 3.4.0 — 2025-11-05
- CI: add PHPStan with baseline and WP ruleset
- Tests: unit coverage for placeholder rendering (legacy `{token}` and `{{dot.notation}}`)
- Templates: safer context building when WP isn’t loaded (guards + fallbacks)
- Actions: unified php-tests workflow (PHP 8.1/8.2)


### 3.3.0 — 2025-11-02
- Email system polish:
  - Centralized mail send via `Emails\Mailer` (standardized From / Reply-To / HTML headers)
  - New filters: `yardlii_tv_email_recipients`, `yardlii_tv_email_headers`, `yardlii_tv_placeholder_context` (+ `yardlii_tv_from*`)
  - “Send me a copy” toggle (BCC the acting admin on bulk resend)
- Tools: “Send test for all forms” (approve / reject / both), fully placeholder-aware
- Requests UI: History modal with copy-to-clipboard; small accessibility/UX tweaks
- Security: aligned nonces + strict capability checks for AJAX routes


## 🚀 New in 3.1.0

- **Feature Flags Architecture** — enable/disable modules from the Advanced tab UI  
- **Read-only Constants** — lock features via `wp-config.php` or plugin constants  
- **Nested Tab System** — three-layer tab architecture (Main, General Subtabs, Inner Map Tabs)  
- **Scoped JS Isolation** — prevents tab conflicts between different levels  
- **Enhanced Accessibility** — keyboard navigation + ARIA roles for all tabs  
- **Refined CSS System** — unified visual style for all YARDLII components  

---

## 🧩 Installation

1. Upload the `yardlii-core-functions` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress  
3. Go to **Settings → YARDLII Core** to configure your modules  

---

## 💡 Frequently Asked Questions

### ❓ Can I add my own modules?
Yes. Add your feature under `/includes/Features/` and register it in `Loader.php`.  
Each feature can have its own UI partial in `/includes/Admin/views/partials/`, and a corresponding Feature Flag for easy control.

### ❓ Can I disable the ACF User Sync feature?
Yes. Toggle it off from  
**Settings → YARDLII Core → Advanced → Feature Flags**,  
or hard-disable it in code:

```php
define('YARDLII_ENABLE_ACF_USER_SYNC', false);