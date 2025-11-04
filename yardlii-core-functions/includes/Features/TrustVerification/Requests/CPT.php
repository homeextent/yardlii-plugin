<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

final class CPT
{
    public const POST_TYPE = 'verification_request';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType'], 0);   // priority 0 (earliest)
    add_action('init', [$this, 'registerStatuses'], 0);   // priority 0 (earliest)
    }

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('Verification Requests', 'yardlii-core'),
            'public' => false, 'show_ui' => false, // rendered inside our tab
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function registerStatuses(): void
    {
        foreach ([
            'vp_pending'  => __('Pending', 'yardlii-core'),
            'vp_approved' => __('Approved', 'yardlii-core'),
            'vp_rejected' => __('Rejected', 'yardlii-core'),
        ] as $status => $label) {
            register_post_status($status, [
                'label' => $label,
                'public' => false,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop("$label <span class='count'>(%s)</span>", "$label <span class='count'>(%s)</span>", 'yardlii-core'),
            ]);
        }
    }
}
