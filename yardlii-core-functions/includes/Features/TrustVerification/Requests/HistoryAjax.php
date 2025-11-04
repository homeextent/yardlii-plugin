<?php
declare(strict_types=1);

namespace Yardlii\Core\Features\TrustVerification\Requests;

use Yardlii\Core\Features\TrustVerification\Caps; // NEW

final class HistoryAjax
{
    public function register(): void
    {
        add_action('wp_ajax_yardlii_tv_history_load', [$this, 'handle']);
    }

    public function handle(): void
    {
        // NEW: dedicated capability
        if (! current_user_can(Caps::MANAGE)) {
            wp_send_json_error(['message' => __('Permission denied.', 'yardlii-core')], 403);
        }

        check_ajax_referer('yardlii_tv_history', '_ajax_nonce');

        $request_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
        $post       = $request_id ? get_post($request_id) : null;

        if (! $post || $post->post_type !== CPT::POST_TYPE) {
            wp_send_json_error(['message' => __('Invalid request.', 'yardlii-core')], 400);
        }

        $logs = (array) get_post_meta($request_id, '_vp_action_logs', true);
        if (!is_array($logs)) $logs = [];

        usort($logs, function($a, $b){
            return strcmp((string)($a['ts'] ?? ''), (string)($b['ts'] ?? ''));
        });

        $html = '<div class="tv-history"><ol class="tv-history-list">';

        if (!$logs) {
            $html .= '<li>'.esc_html__('No history yet.', 'yardlii-core').'</li>';
        } else {
            foreach ($logs as $entry) {
                $ts   = isset($entry['ts']) ? (string) $entry['ts'] : '';
                $who  = (int)($entry['by'] ?? 0);
                $what = (string)($entry['action'] ?? '');
                $data = (array)($entry['data'] ?? []);

                $who_name = '—';
                if ($who) {
                    $u = get_userdata($who);
                    if ($u) $who_name = esc_html($u->display_name ?: $u->user_login);
                }

                $label = match ($what) {
                    'created'           => __('Request created', 'yardlii-core'),
                    'upgrade_submitted' => __('Upgrade submitted', 'yardlii-core'),
                    'approved'          => __('Approved', 'yardlii-core'),
                    'rejected'          => __('Rejected', 'yardlii-core'),
                    'reopened'          => __('Reopened', 'yardlii-core'),
                    'resend'            => __('Decision email resent', 'yardlii-core'),
                    'email_skipped'     => __('Email skipped (no recipient)', 'yardlii-core'),
                    default             => ucfirst(str_replace('_',' ', $what)),
                };

                if ($ts) {
                    $when = esc_html( wp_date(get_option('date_format').' '.get_option('time_format'), strtotime($ts)) );
                } else {
                    $fallback = (string) get_post_meta($request_id, '_vp_processed_date', true);
                    $when = $fallback
                        ? esc_html( wp_date(get_option('date_format').' '.get_option('time_format'), strtotime($fallback)) )
                        : esc_html( get_the_time(get_option('date_format').' '.get_option('time_format'), $post) );
                }

                $pretty = '';

                $fromRoleCsv = isset($data['from_role']) ? (string) $data['from_role'] : '';
                $toRoleCsv   = isset($data['to_role'])   ? (string) $data['to_role']   : '';

                $fromRoleLbl = $this->roleCsvToLabels($fromRoleCsv);
                $toRoleLbl   = $this->roleCsvToLabels($toRoleCsv);

                $roleChanged = (trim($fromRoleCsv, " ,") !== trim($toRoleCsv, " ,"));
                if (($fromRoleCsv !== '' || $toRoleCsv !== '') && $roleChanged) {
                    $pretty .= '<div class="tv-history-line"><strong>'
                             . esc_html__('Role:', 'yardlii-core')
                             . '</strong> ' . esc_html($fromRoleLbl ?: '—') . ' → ' . esc_html($toRoleLbl ?: '—') . '</div>';
                }

                $fromForm = isset($data['from_form']) ? (string) $data['from_form'] : '';
                $toForm   = isset($data['to_form'])   ? (string) $data['to_form']   : '';
                if ($fromForm !== '' || $toForm !== '') {
                    $pretty .= '<div class="tv-history-line"><strong>'
                             . esc_html__('Form:', 'yardlii-core')
                             . '</strong> ' . esc_html($fromForm ?: '—') . ' → ' . esc_html($toForm ?: '—') . '</div>';
                }

                $debug = $data;
                unset($debug['ts'], $debug['from_role'], $debug['to_role'], $debug['from_form'], $debug['to_form']);

                $kv = '';
                if (!empty($debug)) {
                    $pairs = [];
                    foreach ($debug as $k=>$v) {
                        $pairs[] = '<code>'.esc_html($k).'</code>: '.esc_html(is_scalar($v) ? (string)$v : wp_json_encode($v));
                    }
                    $kv = '<div class="tv-history-detail">'.implode(' · ', $pairs).'</div>';
                }

                $html .= '<li class="tv-history-item">'
                      .  '<div class="tv-history-head"><strong>'.$label.'</strong>'
                      .  ' <span class="tv-history-meta">— '.$when.' · '.$who_name.'</span></div>'
                      .  ($pretty ?: '')
                      .  $kv
                      .  '</li>';
            }
        }

        $html .= '</ol></div>';

        wp_send_json_success(['html' => $html]);
    }

    /** Convert a CSV of role slugs into human-readable labels. */
    private function roleCsvToLabels(string $csv): string
    {
        $csv = trim($csv);
        if ($csv === '') return '';
        $slugs = array_filter(array_map('trim', explode(',', $csv)));
        if (!$slugs) return '';

        $roles = wp_roles();
        $map   = is_object($roles) ? ($roles->role_names ?? []) : [];

        $labels = array_map(function ($slug) use ($map) {
            return $map[$slug] ?? $slug;
        }, $slugs);

        return implode(', ', array_values(array_unique($labels)));
    }
}
