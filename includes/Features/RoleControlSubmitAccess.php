<?php
namespace Yardlii\Core\Features;

if (!defined('ABSPATH')) exit;

/**
 * Restrict access to a specific front-end page (default: submit-a-post) by role.
 */
class RoleControlSubmitAccess
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybe_block_submit_page']);
    }

    private function is_enabled(): bool
    {
        // Option wins, constant (if defined) overrides
        $enabled = (bool) get_option('yardlii_enable_role_control_submit', false);
        if (defined('YARDLII_ENABLE_ROLE_CONTROL_SUBMIT')) {
            $enabled = (bool) YARDLII_ENABLE_ROLE_CONTROL_SUBMIT;
        }
        return $enabled;
    }

    public function maybe_block_submit_page(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $slug          = (string) get_option('yardlii_role_control_target_page', 'submit-a-post');
        if (empty($slug) || !is_page($slug)) {
            return; // only act on the target page
        }

        // If not logged in
        if (!is_user_logged_in()) {
            $this->deny(true);
            return;
        }

        $user    = wp_get_current_user();
        $allowed = (array) get_option('yardlii_role_control_allowed_roles', []);

        // If no roles configured, allow any logged-in user
        if (empty($allowed)) {
            return;
        }

        // If current user has any allowed role, allow
        if (array_intersect($allowed, (array) $user->roles)) {
            return;
        }

        // Otherwise deny
        $this->deny(false);
    }

    private function deny(bool $guest): void
    {
        $action  = (string) get_option('yardlii_role_control_denied_action', 'message');
        $message = (string) get_option('yardlii_role_control_denied_message', 'You do not have permission to access this page.');

        if ($action === 'redirect_login') {
            // Redirect to login and bounce back to current page after auth
            $redirect = wp_login_url(esc_url(home_url(add_query_arg([], $GLOBALS['wp']->request))));
            wp_safe_redirect($redirect);
            exit;
        }

        // Friendly blocking message
        wp_die(
            wp_kses_post($message),
            esc_html__('Access Restricted', 'yardlii-core'),
            ['response' => 403, 'back_link' => true]
        );
    }
}
