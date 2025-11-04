<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

final class Cleanup
{
    public function register(): void
    {
        // Fires AFTER a user is deleted (single-site)
        add_action('deleted_user',        [$this, 'purgeByUser'], 10, 1);
        // Fires when a user is deleted from a Multisite network
        add_action('wpmu_delete_user',    [$this, 'purgeByUser'], 10, 1);
    }

    /**
     * Delete all verification requests bound to a specific user ID.
     */
    public function purgeByUser(int $user_id): void
    {
        // Find all requests where _vp_user_id == $user_id
        $ids = get_posts([
            'post_type'      => CPT::POST_TYPE,
            'post_status'    => 'any',            // include pending/approved/rejected/trash
            'fields'         => 'ids',
            'numberposts'    => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => '_vp_user_id',
                    'value' => $user_id,
                ],
            ],
        ]);

        if (!$ids) return;

        foreach ($ids as $post_id) {
            // Force delete (skip trash) so the list won’t show “(deleted user)”
            wp_delete_post((int) $post_id, true);
        }
    }
}
