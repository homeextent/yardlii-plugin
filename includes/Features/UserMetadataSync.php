<?php
declare(strict_types=1);

namespace Yardlii\Core\Features;

/**
 * Feature: User Metadata Sync
 * ---------------------------
 * Lightweight sync for "Member Since" data (replacing the deprecated ACF User Sync).
 * Runs only on user creation.
 */
class UserMetadataSync {

    /**
     * Register hooks.
     */
    public function register(): void {
        // Hook into user registration (fires immediately after a user is created)
        add_action('user_register', [$this, 'sync_member_since'], 10, 1);
    }

    /**
     * Sync the WP 'user_registered' timestamp to the 'yardlii_member_since' ACF field.
     *
     * @param int $user_id
     */
    public function sync_member_since(int $user_id): void {
        // 1. Guard: Ensure ACF is active
        if (!function_exists('update_field')) {
            return; 
        }

        // 2. Get user data
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        // 3. Sync the data
        // Source: WP Core 'user_registered' (e.g., "2025-11-15 12:00:00")
        // Target: ACF Field 'yardlii_member_since'
        // Note: ACF requires the 'user_{id}' format for user meta keys.
        update_field('yardlii_member_since', $user->user_registered, 'user_' . $user_id);
        
        // Debug logging (respects plugin debug constant)
        if (defined('YARDLII_DEBUG') && YARDLII_DEBUG) {
            error_log("[YARDLII] Synced 'member_since' for User ID: $user_id");
        }
    }
}