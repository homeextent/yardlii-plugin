<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Support;

final class Meta
{
    public static function appendLog(int $request_id, string $action, int $by = 0, array $data = []): void
{
    $logs = (array) get_post_meta($request_id, '_vp_action_logs', true);
    if (!is_array($logs)) $logs = [];

    // Single authoritative timestamp
    $ts = (isset($data['ts']) && is_string($data['ts']) && $data['ts'] !== '')
        ? $data['ts']
        : gmdate('c');

    unset($data['ts']); // don't duplicate ts in the data blob

    $logs[] = [
        'action' => $action,
        'by'     => $by,
        'ts'     => $ts,
        'data'   => $data,
    ];

    update_post_meta($request_id, '_vp_action_logs', $logs);
}



    // TODO: typed getters/setters for:
    // _vp_user_id, _vp_form_id, _vp_old_roles, _vp_processed_by, _vp_processed_date
}
