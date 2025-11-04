Yardlii — Trust & Verification Providers
========================================

Purpose
-------
Providers decouple “form plugin events” (Elementor, WPUF, etc.) from the
core TV workflow. Each provider:
  1) Listens to its form plugin’s submit events.
  2) Determines the submitting user (requires logged-in users).
  3) Derives a stable form_id string that matches a TV "Per-Form Config" row.
  4) Calls Guards::maybeCreateRequest($user_id, $form_id, $context).

Interface
---------
interface ProviderInterface {
  public function getName(): string;     // e.g., 'elementor-pro', 'wpuf'
  public function registerHooks(): void; // add_action() / add_filter() here
}

Current Implementations
-----------------------
- Providers\WPUF          (WebForms / WP User Frontend)
- Providers\ElementorPro  (Elementor Pro Forms)

Enabling/Disabling Providers
----------------------------
Sites can toggle providers via the filter:

add_filter('yardlii_tv_enabled_providers', function ($enabled) {
  $enabled['wpuf']      = true;
  $enabled['elementor'] = true;
  // $enabled['gf'] = false; $enabled['cf7'] = false; ...
  return $enabled;
});

Form ID Strategy (match Per-Form Config)
----------------------------------------
Option A (recommended): computed slug with a prefix unique to the provider.
  - Elementor: 'elementor:' . sanitize_title(form_name or widget_id)
  - WPUF:      'wpuf:' . {form_id or form_name_slug}
Then create a Per-Form Config row with that exact ID.

Option B (override via hidden field):
  Add a hidden input named "tv_form_id" and set any string you like.
  If present, providers use this value instead of computing a slug.

Important Rules
---------------
- Only handle logged-in users. Anonymous submits are ignored by design.
- Never create duplicate Pending requests for the same (user_id + form_id).
  Guards::maybeCreateRequest handles de-duplication and “retarget/reopen”.
- Use the provider name in $context['provider'] for logs/troubleshooting.
- Keep the slug stable over time (don’t base it on translatable labels).

Making a New Provider
---------------------
1) Create a class in Providers\YourProvider.php that implements ProviderInterface.
2) In registerHooks(), hook the vendor’s “after submission” events.
3) Resolve $user_id (must be logged in) and $form_id (prefix + stable slug).
4) Call:
   Guards::maybeCreateRequest($user_id, $form_id, [
     'provider' => 'yourprovider',
     'event'    => 'the_event_you_hooked',
   ]);
5) Add boot logic (if needed) in Module::bootProviders() or via an init action.

Examples (pseudo-code)
----------------------

Contact Form 7:
---------------
namespace Yardlii\Core\Features\TrustVerification\Providers;

use Yardlii\Core\Features\TrustVerification\Requests\Guards;

final class CF7 implements ProviderInterface {
  public function getName(): string { return 'cf7'; }
  public function registerHooks(): void {
    add_action('wpcf7_mail_sent', [$this,'onSent'], 10, 1);
  }
  public function onSent($contact_form): void {
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    $title   = method_exists($contact_form,'title') ? (string) $contact_form->title() : '';
    $form_id = 'cf7:' . sanitize_title($title ?: ('id-' . $contact_form->id()));
    Guards::maybeCreateRequest($user_id, $form_id, [
      'provider' => 'cf7',
      'event'    => 'mail_sent',
    ]);
  }
}

Gravity Forms:
--------------
add_action('gform_after_submission', function($entry, $form) {
  if (!is_user_logged_in()) return;
  $user_id = get_current_user_id();
  $name    = isset($form['title']) ? (string) $form['title'] : '';
  $form_id = 'gf:' . sanitize_title($name ?: ('id-' . (string) $form['id']));
  \Yardlii\Core\Features\TrustVerification\Requests\Guards::maybeCreateRequest(
    $user_id, $form_id, ['provider'=>'gf','event'=>'after_submission']
  );
}, 10, 2);

MetForm (example hook name may vary by version):
------------------------------------------------
add_action('metform_after_email_sent', function($form_id, $data) {
  if (!is_user_logged_in()) return;
  $user_id = get_current_user_id();
  $slug    = 'metform:' . sanitize_title((string) $form_id);
  \Yardlii\Core\Features\TrustVerification\Requests\Guards::maybeCreateRequest(
    $user_id, $slug, ['provider'=>'metform','event'=>'after_email_sent']
  );
}, 10, 2);

Troubleshooting
---------------
- Turn on Debug mode in YARDLII → Advanced → Debug Mode.
- Watch debug.log for lines like:
  [TV] Elementor submit event=... user=123 form_id=elementor:partner
- If no row appears, verify:
  * You are logged in.
  * The Per-Form Config Form ID exactly matches your computed/hidden ID.
  * The provider slug prefix matches the implementation (e.g., 'elementor:').
  * No JS errors prevent the vendor’s submit action from firing.

Email System Customization
--------------------------
The Mailer centralizes headers and placeholder rendering. You can customize
recipients, headers, and the placeholder context via these filters:

- yardlii_tv_email_recipients(array $recipients, array $context)
- yardlii_tv_email_headers(array $headers, array $context)
- yardlii_tv_placeholder_context(array $context)
- yardlii_tv_from_name(string $name, array $context)
- yardlii_tv_from_email(string $email, array $context)
- yardlii_tv_from(array $pair, array $context) // ['name'=>'','email'=>'']

Quick examples (drop into a small mu-plugin or your theme’s functions.php):

// Add an X-Mailer header
add_filter('yardlii_tv_email_headers', function(array $headers, array $ctx){
    $headers[] = 'X-Mailer: Yardlii TV';
    return $headers;
}, 10, 2);

// Force a specific From address
add_filter('yardlii_tv_from_email', function($email, $ctx){
    return 'no-reply@yourdomain.com';
}, 10, 2);

// Add a custom placeholder value available as {{request.id}}
add_filter('yardlii_tv_placeholder_context', function(array $ctx){
    if (!isset($ctx['request_id']) && isset($ctx['request'])) {
        $ctx['request_id'] = (int) $ctx['request']->ID;
    }
    return $ctx;
}, 10, 1);


Placeholder tokens (both forms supported):
- {{site.name}}      / {site_title}
- {{site.url}}       / {site_url}
- {{form.id}}        / {form_id}
- {{request.id}}     / {request_id}
- {{user.display_name}} / {display_name}
- {{user.email}}     / {user_email}
- {{user.login}}     / {user_login}

