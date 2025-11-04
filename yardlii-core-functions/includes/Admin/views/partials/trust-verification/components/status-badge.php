<?php
if (!function_exists('yardlii_tv_status_badge')) {
    function yardlii_tv_status_badge(string $status): string {
        $map = [
            'vp_pending'  => ['Pending',  'pending'],
            'vp_approved' => ['Approved', 'approved'],
            'vp_rejected' => ['Rejected', 'rejected'],
        ];
        [$label, $cls] = $map[$status] ?? [$status, sanitize_html_class($status)];
        return sprintf('<span class="status-badge status-badge--%s">%s</span>', esc_attr($cls), esc_html($label));
    }
}
