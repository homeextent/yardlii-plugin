<?php
namespace Yardlii\Core\Features;

/**
 * HomepageSearch
 *
 * Generates the homepage search form with:
 * - Keyword field
 * - Primary & secondary taxonomy dropdowns
 * - Optional Location input + Radius slider (controlled by admin setting)
 */
class HomepageSearch
{
    private const OPT_PRIMARY_TAXONOMY   = 'yardlii_primary_taxonomy';
    private const OPT_PRIMARY_LABEL      = 'yardlii_primary_label';
    private const OPT_PRIMARY_FACET      = 'yardlii_primary_facet';
    private const OPT_SECONDARY_TAXONOMY = 'yardlii_secondary_taxonomy';
    private const OPT_SECONDARY_LABEL    = 'yardlii_secondary_label';
    private const OPT_SECONDARY_FACET    = 'yardlii_secondary_facet';
    private const OPT_LOCATION_LABEL     = 'yardlii_location_label';
    private const OPT_ENABLE_LOCATION    = 'yardlii_enable_location_search'; // NEW

    public function register(): void
    {
        // Register shortcode for homepage search form
        add_action('init', function () {
            add_shortcode('yardlii_search_form', [$this, 'render_search_form']);
        });

        // Flush cached terms when taxonomies are modified
        add_action('created_term', [$this, 'flush_term_cache']);
        add_action('edited_term',  [$this, 'flush_term_cache']);
        add_action('delete_term',  [$this, 'flush_term_cache']);
    }

    /**
     * Render the full search form
     */
    public function render_search_form(): string
    {
        if (defined('YARDLII_CORE_FILE') && defined('YARDLII_CORE_VERSION')) {
            wp_enqueue_style(
                'yardlii-frontend',
                plugins_url('/assets/css/frontend.css', YARDLII_CORE_FILE),
                [],
                YARDLII_CORE_VERSION
            );
            wp_enqueue_script(
                'yardlii-frontend',
                plugins_url('/assets/js/frontend.js', YARDLII_CORE_FILE),
                ['jquery'],
                YARDLII_CORE_VERSION,
                true
            );
        }

        $action_url = esc_url(home_url('/listings/'));
        ob_start(); ?>

        <form role="search" method="get" class="yardlii-search-form" action="<?php echo $action_url; ?>">
            <div class="search-row" style="display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;">
                
                <!-- 🔍 Keyword -->
                <div class="search-field">
                    <input type="search" name="_keyword" placeholder="<?php echo esc_attr__('What are you looking for?', 'yardlii-core'); ?>" />
                </div>

                <!-- 🧩 Dropdown fields -->
                <div class="search-dropdowns" style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php echo $this->render_dropdowns(); ?>
                </div>

                <!-- 🔘 Submit -->
                <div class="search-button">
                    <button type="submit"><?php echo esc_html__('Search', 'yardlii-core'); ?></button>
                </div>
            </div>
        </form>

        <?php
        return ob_get_clean();
    }

    /**
     * Build dropdowns for primary, secondary, and (conditionally) location + radius fields
     */
    private function render_dropdowns(): string
    {
        $primary_tax     = get_option(self::OPT_PRIMARY_TAXONOMY, '');
        $primary_label   = get_option(self::OPT_PRIMARY_LABEL, 'Select Option');
        $primary_facet   = get_option(self::OPT_PRIMARY_FACET, '');
        $secondary_tax   = get_option(self::OPT_SECONDARY_TAXONOMY, '');
        $secondary_label = get_option(self::OPT_SECONDARY_LABEL, 'Select Option');
        $secondary_facet = get_option(self::OPT_SECONDARY_FACET, '');
        $location_label  = get_option(self::OPT_LOCATION_LABEL, 'Enter a location');
        $enable_location = (bool) get_option(self::OPT_ENABLE_LOCATION, false); // NEW toggle check

        $out = '';

        // 🏗️ Primary taxonomy dropdown
        if (!empty($primary_tax)) {
            $out .= '<div class="yardlii-search-dropdown primary" data-facet="' . esc_attr($primary_facet) . '">';
            $out .= $this->taxonomy_dropdown_html($primary_tax, $primary_label, $primary_facet);
            $out .= '</div>';
        }

        // 🧱 Secondary taxonomy dropdown
        if (!empty($secondary_tax)) {
            $out .= '<div class="yardlii-search-dropdown secondary" data-facet="' . esc_attr($secondary_facet) . '">';
            $out .= $this->taxonomy_dropdown_html($secondary_tax, $secondary_label, $secondary_facet);
            $out .= '</div>';
        }

        // 🌍 Location Input + Radius Slider (conditionally rendered)
        if ($enable_location) {
            $out .= '<div class="yardlii-search-dropdown location">';
            $out .= '<label for="yardlii_location_input" class="screen-reader-text">'
                  . esc_html($location_label) . '</label>';

            // Location field + compass icon
            $out .= '<div class="yardlii-location-wrapper">';
            $out .= '<input type="text" id="yardlii_location_input"
                           name="_location_text"
                           class="yardlii-location-input"
                           placeholder="' . esc_attr($location_label) . '" />';
            $out .= '<span id="yardlii_locate_me" class="yardlii-locate-icon" title="Use my current location" aria-label="Use my current location">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="12 6 14 12 12 18 10 12 12 6"></polygon>
                        </svg>
                     </span>';
            $out .= '</div>';

            // Radius slider + tooltip
            $out .= '<div class="yardlii-radius-slider">';
            $out .= '<div class="yardlii-slider-wrap">';
            $out .= '<input type="range"
                           id="yardlii_radius_range"
                           name="fwp_distance"
                           min="1"
                           max="500"
                           value="25"
                           step="1"
                           class="yardlii-radius-input" />';
            $out .= '<span id="yardlii_radius_tooltip" class="yardlii-radius-tooltip">25 km</span>';
            $out .= '</div></div></div>';
        }

        return $out;
    }

    /**
     * Generate taxonomy dropdown HTML
     */
    private function taxonomy_dropdown_html(string $taxonomy, string $label, string $facetName = ''): string
    {
        $terms = $this->get_cached_terms($taxonomy);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $nameAttr = $facetName ? '_' . sanitize_key($facetName) : sanitize_key($taxonomy);

        $html  = '<select name="' . esc_attr($nameAttr) . '" class="yardlii-search-select">';
        $html .= '<option value="">' . esc_html($label) . '</option>';
        foreach ($terms as $term) {
            $html .= '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Get cached taxonomy terms (parent-only for hierarchical taxonomies)
     */
    private function get_cached_terms(string $taxonomy)
    {
        $is_hierarchical = is_taxonomy_hierarchical($taxonomy);
        $cache_key = 'yardlii_terms_' . sanitize_key($taxonomy) . ($is_hierarchical ? '_parents' : '_all');
        $terms = get_transient($cache_key);

        if (false === $terms) {
            $query_args = [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ];

            if ($is_hierarchical) {
                $query_args['parent'] = 0;
                $query_args['hierarchical'] = false;
                $query_args['pad_counts'] = true;
            }

            $terms = get_terms($query_args);

            if (!is_wp_error($terms) && $is_hierarchical) {
                $terms = array_values(array_filter($terms, static fn($t) => (int) $t->parent === 0));
            }

            set_transient($cache_key, $terms, DAY_IN_SECONDS);
        }

        return $terms;
    }

    /**
     * Flush cached terms on taxonomy edits
     */
    public function flush_term_cache($term_id): void
    {
        $term = get_term($term_id);
        if ($term && !is_wp_error($term)) {
            delete_transient('yardlii_terms_' . sanitize_key($term->taxonomy) . '_parents');
            delete_transient('yardlii_terms_' . sanitize_key($term->taxonomy) . '_all');
        }
    }
}
