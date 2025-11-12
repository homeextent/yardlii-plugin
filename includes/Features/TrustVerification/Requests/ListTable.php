<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Caps;
use WP_User;

if ( ! class_exists('\WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ListTable extends \WP_List_Table
{
    /**
     * Local cache for user data to prevent N+1 queries.
     * @var array<int, \WP_User|null>
     */
    private array $user_cache = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'verification_request',
            'plural'   => 'verification_requests',
            'ajax'     => false,
        ]);
    }

    /** Placeholder for future screen options */
    public function register(): void {}

    /** @return array<string,string> */
    public function get_columns(): array
    {
        return [
            'cb'             => '<input type="checkbox" />',
            'user'           => __('User', 'yardlii-core'),
            'form'           => __('Form', 'yardlii-core'),
            'submitted'      => __('Submitted', 'yardlii-core'),
            'status'         => __('Status', 'yardlii-core'),
            'current_role'   => __('Role', 'yardlii-core'),
            'processed_by'   => __('Processed By', 'yardlii-core'),
            'processed_date' => __('Processed Date', 'yardlii-core'),
        ];
    }

    /** @return array<string,array{0:string,1:bool}> */
    protected function get_sortable_columns(): array
    {
        return [
            'submitted' => ['date', true],
        ];
    }

    /** The primary column (anchors row actions). */
    protected function get_primary_column_name(): string
    {
        return 'user';
    }

    /** Checkbox column */
    protected function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="post[]" value="%d" />', (int) $item['ID']);
    }

    protected function extra_tablenav( $which ) {
        // $which is 'top' or 'bottom'
        echo '<div class="alignleft actions">';
        printf(
            '<label for="tv_send_copy_%1$s" class="tv-send-copy-label" title="%2$s">
               <input type="checkbox" name="tv_send_copy" id="tv_send_copy_%1$s" value="1" />
               %3$s
             </label>',
            esc_attr($which),
            esc_attr__('Also send a copy to me (current admin).', 'yardlii-core'),
            esc_html__('Send me a copy', 'yardlii-core')
        );
        echo '</div>';
    }


    /** Fallback when no rows exist */
    public function no_items(): void
    {
        esc_html_e('No verification requests found.', 'yardlii-core');
    }

    /**
     * Default column renderer.
     * @param array<string, mixed> $item
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'user': {
                // Get user from our pre-fetched cache
                $u = $this->get_cached_user((int) $item['_vp_user_id']);
                if (! $u) {
                    return esc_html__('(deleted user)', 'yardlii-core');
                }
                $name = $u->display_name ?: $u->user_login;
                $url  = admin_url('user-edit.php?user_id=' . (int) $u->ID);
                return sprintf(
                    '<a href="%s">%s</a><br><small>%s</small>',
                    esc_url($url),
                    esc_html($name),
                    esc_html($u->user_email)
                );
            }

            case 'form':
                return esc_html((string) $item['_vp_form_id']);

            case 'submitted':
                return esc_html(
                    wp_date(
                        get_option('date_format') . ' ' . get_option('time_format'),
                        (int) $item['_post_time']
                    )
                );

            case 'status': {
                $st    = (string) $item['_post_status'];
                $class = esc_attr(str_replace('vp_', '', $st));
                $label = esc_html($this->labelForStatus($st));
                
                // 1. Create the base badge
                $output = sprintf('<span class="status-badge status-badge--%s">%s</span>', $class, $label);

                // 2. NEW: Check for employer wait state
                // We use get_post_meta directly; WP's internal cache makes this efficient
                // because update_meta_cache() was called in prepare_items().
                $verif_type = get_post_meta((int) $item['ID'], '_vp_verification_type', true);

                if ($st === 'vp_pending' && $verif_type === 'employer_vouch') {
                    // Append the icon
                    $output .= sprintf(
                        ' <span class="dashicons dashicons-businessperson" title="%s" style="color:#888; vertical-align:middle; font-size:18px;"></span>',
                        esc_attr__('Waiting for Employer Vouch', 'yardlii-core')
                    );
                }

                return $output;
            }

            case 'current_role':
                $label = (string) ($item['_vp_role_label'] ?? '');
                $slug  = (string) ($item['_vp_role_slug'] ?? '');
                if ($label === '' && $slug === '') return '—';
                if ($label && $slug) {
                    return sprintf('%s <br><small>%s</small>', esc_html($label), esc_html($slug));
                }
                return esc_html($label ?: $slug);

            case 'processed_by': {
                $by = (int) ($item['_vp_processed_by'] ?? 0);
                if (! $by) return '—';
                // Get admin from our pre-fetched cache
                $u = $this->get_cached_user($by);
                return $u ? esc_html($u->display_name ?: $u->user_login) : '—';
            }

            case 'processed_date': {
                $ts = (string) ($item['_vp_processed_date'] ?? '');
                return $ts
                    ? esc_html(
                        wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ts))
                      )
                    : '—';
            }
        }
        return '';
    }

    /**
     * Row actions (Approve/Reject/Re-open/Resend).
     * @param array<string,mixed> $item
     */
    protected function handle_row_actions($item, $column_name, $primary): string
    {
        if ($column_name !== $primary) return '';

        $post_id = (int) $item['ID'];
        $status  = (string) $item['_post_status'];
        $nonce   = wp_create_nonce('yardlii_tv_action_nonce');
        $base    = admin_url('admin.php');

        $actions = [];

        if ($status === 'vp_pending') {
            $actions['tv_ap'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'yardlii_tv_approve',
                    'post'     => $post_id,
                    '_wpnonce' => $nonce,
                ], $base)),
                esc_html__('Approve', 'yardlii-core')
            );

            $actions['tv_rj'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'yardlii_tv_reject',
                    'post'     => $post_id,
                    '_wpnonce' => $nonce,
                ], $base)),
                esc_html__('Reject', 'yardlii-core')
            );
        } else {
            $actions['tv_ro'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'yardlii_tv_reopen',
                    'post'     => $post_id,
                    '_wpnonce' => $nonce,
                ], $base)),
                esc_html__('Re-open', 'yardlii-core')
            );

            $actions['tv_rs'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(add_query_arg([
                    'action'   => 'yardlii_tv_resend',
                    'post'     => $post_id,
                    '_wpnonce' => $nonce,
                ], $base)),
                esc_html__('Resend Email', 'yardlii-core')
            );
        }

        // Add a per-row nonce for history
        $history_nonce = wp_create_nonce('yardlii_tv_history');

        $actions['tv_hist'] = sprintf(
          '<a href="#" data-action="tv-row-history" data-post="%d" data-nonce="%s">%s</a>',
          $post_id,
          esc_attr($history_nonce),
          esc_html__('History', 'yardlii-core')
        );

        return $this->row_actions($actions);
    }

    /** @return array<string,string> */
    protected function get_bulk_actions(): array
    {
        return [
            'yardlii_tv_bulk_approve' => __('Approve', 'yardlii-core'),
            'yardlii_tv_bulk_reject'  => __('Reject', 'yardlii-core'),
            'yardlii_tv_bulk_reopen'  => __('Re-open', 'yardlii-core'),
            'yardlii_tv_bulk_resend'  => __('Resend Email', 'yardlii-core'),
        ];
    }

    /** Process bulk actions and redirect back with counts. */
    protected function process_bulk_action(): void
    {
        if (empty($_POST['post']) || ! is_array($_POST['post'])) return;

        if (! current_user_can(Caps::MANAGE)) return;

        // Verify WP_List_Table bulk nonce
        check_admin_referer('bulk-' . $this->_args['plural']); // 'bulk-verification_requests'

        $action = $this->current_action();
        $ids    = array_map('intval', (array) $_POST['post']);

        $map = [
            'yardlii_tv_bulk_approve' => 'approve',
            'yardlii_tv_bulk_reject'  => 'reject',
            'yardlii_tv_bulk_reopen'  => 'reopen',
            'yardlii_tv_bulk_resend'  => 'resend',
        ];
        if (! isset($map[$action])) return;

        $decision = new Decisions();
        $done     = 0;

        // NEW: read the tools/toolbar toggle (default off)
        $ccSelf = ! empty($_REQUEST['tv_send_copy']);

        foreach ($ids as $id) {
            if ($decision->applyDecision($id, $map[$action], ['cc_self' => $ccSelf])) {
                $done++;
            }
        }

        $ref = admin_url('admin.php?page=yardlii-core-settings&tab=trust-verification');
        $ref = add_query_arg([
            'tvsection' => 'requests',
            'tv_notice' => 'bulk_' . $map[$action],
            'tv_count'  => $done,
        ], $ref);

        wp_safe_redirect($ref);
        exit;
    }


    /** Load rows, columns, and pagination. */
    public function prepare_items(): void
    {
        // Run bulk processor first
        $this->process_bulk_action();

        $per_page = 20;
        $paged    = max(1, (int) ($_REQUEST['paged'] ?? 1));
        $status   = sanitize_key($_REQUEST['post_status'] ?? '');
        $search   = sanitize_text_field($_REQUEST['s'] ?? '');

        $statuses    = ['vp_pending','vp_approved','vp_rejected'];
        $post_status = in_array($status, $statuses, true) ? [$status] : $statuses;

        // === OPTIMIZATION: STEP 1 ===
        // Run the fast 'ids' query.
        $args = [
            'post_type'              => CPT::POST_TYPE,
            'post_status'            => $post_status,
            'posts_per_page'         => $per_page,
            'paged'                  => $paged,
            's'                      => $search ?: null,
            'fields'                 => 'ids', // Fast!
            'no_found_rows'          => false,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false, // We will handle this manually
        ];

        $q = new \WP_Query($args);

        $items = [];
        $user_ids_to_fetch = [];

        if ($q->posts) {
            // === OPTIMIZATION: STEP 2 ===
            // Prime the post and post_meta caches for all IDs in one go.
            update_post_caches($q->posts, CPT::POST_TYPE);
            update_meta_cache('post', $q->posts);

            // === OPTIMIZATION: STEP 3 ===
            // Loop through IDs, pull from cache, and gather user IDs
            foreach ($q->posts as $post_id) {
                $post    = get_post($post_id); // Pulls from cache
                $user_id = (int) get_post_meta($post_id, '_vp_user_id', true); // Pulls from cache
                $admin_id = (int) get_post_meta($post_id, '_vp_processed_by', true); // Pulls from cache

                // Add user IDs to our list to fetch
                if ($user_id > 0) $user_ids_to_fetch[] = $user_id;
                if ($admin_id > 0) $user_ids_to_fetch[] = $admin_id;

                $role_slug  = '—';
                $role_label = '—';
                $user_obj   = $user_id ? get_userdata($user_id) : null; // This is still N+1, but we'll fix it next

                if ($user_obj && !empty($user_obj->roles)) {
                    $role_slug  = reset($user_obj->roles);
                    $role_label = $this->roleLabel($role_slug);
                }

                $items[] = [
                    'ID'                 => (int) $post_id,
                    '_post_time'         => (int) get_post_time('U', true, $post),
                    '_post_status'       => (string) $post->post_status,
                    '_vp_user_id'        => $user_id,
                    '_vp_form_id'        => (string) get_post_meta($post_id, '_vp_form_id', true),
                    '_vp_processed_by'   => $admin_id,
                    '_vp_processed_date' => (string) get_post_meta($post_id, '_vp_processed_date', true),
                    '_vp_role_slug'      => $role_slug,
                    '_vp_role_label'     => $role_label,
                ];
            }

            // === OPTIMIZATION: STEP 4 ===
            // Pre-fetch all users in a single query
            if (!empty($user_ids_to_fetch)) {
                $this->cache_users(array_unique($user_ids_to_fetch));
            }

            // === OPTIMIZATION: STEP 5 ===
            // Re-loop to fix user roles using the new cache
            // This avoids the get_userdata() N+1 from Step 3
            foreach ($items as $index => $item) {
                $user_obj = $this->get_cached_user((int) $item['_vp_user_id']);
                if ($user_obj && !empty($user_obj->roles)) {
                    $role_slug = reset($user_obj->roles);
                    $items[$index]['_vp_role_slug'] = $role_slug;
                    $items[$index]['_vp_role_label'] = $this->roleLabel($role_slug);
                }
            }
        }

        $this->items = $items;

        // Column headers
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable, 'user']; // 'user' = primary

        // Pagination
        $this->set_pagination_args([
            'total_items' => (int) $q->found_posts,
            'per_page'    => $per_page,
            'total_pages' => (int) $q->max_num_pages,
        ]);
    }

    /** Filter links (All / Pending / Approved / Rejected). */
    protected function get_views(): array
    {
        $base  = remove_query_arg(['post_status','paged']);
        $views = [];

        $all_count    = $this->countByStatus(['vp_pending','vp_approved','vp_rejected']);
        $views['all'] = $this->viewLink($base, '', __('All', 'yardlii-core'), $all_count);

        foreach ([
            'vp_pending'  => __('Pending', 'yardlii-core'),
            'vp_approved' => __('Approved', 'yardlii-core'),
            'vp_rejected' => __('Rejected', 'yardlii-core'),
        ] as $st => $label) {
            $views[$st] = $this->viewLink($base, $st, $label, $this->countByStatus([$st]));
        }

        return $views;
    }

    private function viewLink(string $base, string $status, string $label, int $count): string
    {
        $is_current = (isset($_REQUEST['post_status']) ? $_REQUEST['post_status'] === $status : $status === '');
        $url        = $status ? add_query_arg('post_status', $status, $base) : $base;
        $label_html = sprintf('%s <span class="count">(%d)</span>', esc_html($label), $count);

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            $is_current ? 'current' : '',
            $label_html
        );
    }

    /** Count requests by status. */
    private function countByStatus(array $statuses): int
    {
        $q = new \WP_Query([
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => $statuses,
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => false,
        ]);
        return (int) $q->found_posts;
    }

    /** Human label for a vp_* status. */
    private function labelForStatus(string $status): string
    {
        return match ($status) {
            'vp_pending'  => __('Pending', 'yardlii-core'),
            'vp_approved' => __('Approved', 'yardlii-core'),
            'vp_rejected' => __('Rejected', 'yardlii-core'),
            default       => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /** Render the filter links wrapper the way core expects. */
    public function display_views(): void
    {
        echo '<div class="subsubsub">';
        $links = $this->get_views();
        echo implode(' | ', $links);
        echo '</div>';
    }

    private function roleLabel(string $slug): string
    {
        if ($slug === '') return '—';
        $roles = wp_roles();
        $map   = is_object($roles) ? ($roles->role_names ?? []) : [];
        return $map[$slug] ?? $slug;
    }

    /**
     * Eager-loads an array of user IDs into the local cache.
     * @param int[] $user_ids
     */
    private function cache_users(array $user_ids): void
    {
        $ids_to_fetch = [];
        foreach ($user_ids as $id) {
            if ($id > 0 && ! isset($this->user_cache[$id])) {
                $ids_to_fetch[] = $id;
            }
        }

        if (empty($ids_to_fetch)) {
            return;
        }

        $users = get_users(['include' => array_unique($ids_to_fetch)]);
        foreach ($users as $user) {
            $this->user_cache[$user->ID] = $user;
        }
    }

    /**
     * Gets a user from the local cache, or null if not found.
     */
    private function get_cached_user(int $user_id): ?\WP_User
    {
        return $this->user_cache[$user_id] ?? null;
    }
}