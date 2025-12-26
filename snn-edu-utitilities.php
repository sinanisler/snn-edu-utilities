<?php   
/** 
 * Plugin Name: SNN Edu Utilities
 * Plugin URI: https://github.com/sinanisler/snn-edu-utilities
 * Description: Educational utilities including admin restrictions, dashboard notepad, and custom author permalinks with role-based URLs.
 * Version: 1.1
 * Author: sinanisler
 * Author URI: https://sinanisler.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snn
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SNN_EDU_VERSION', '1.0');
define('SNN_EDU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SNN_EDU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SNN_EDU_PLUGIN_FILE', __FILE__);

// Include GitHub updater
require_once SNN_EDU_PLUGIN_DIR . 'github-update.php';

// Get plugin options
function snn_edu_get_option($option_name, $default = false) {
    $options = get_option('snn_edu_settings', array());
    return isset($options[$option_name]) ? $options[$option_name] : $default;
}

/**
 * ==========================================
 * FEATURE 1: Restrict wp-admin to administrators only
 * ==========================================
 */
function snn_edu_restrict_admin_to_administrators_only() {
    if (!snn_edu_get_option('enable_admin_restriction', false)) {
        return;
    }
    
    if (is_admin() && !current_user_can('manage_options') && !(defined('DOING_AJAX') && DOING_AJAX)) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('admin_init', 'snn_edu_restrict_admin_to_administrators_only');

/**
 * ==========================================
 * FEATURE 2: Show admin bar to admins only
 * ==========================================
 */
function snn_edu_hide_admin_bar() {
    if (!snn_edu_get_option('enable_admin_bar_restriction', false)) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'snn_edu_hide_admin_bar');

/**
 * ==========================================
 * FEATURE 4: Custom Role-Based Author Permalinks (ID-based)
 * ==========================================
 */

// Step 1: Change default author base to 'user'
function snn_edu_custom_author_base() {
    if (!snn_edu_get_option('enable_custom_author_urls', false)) {
        return;
    }
    
    global $wp_rewrite;
    $wp_rewrite->author_base = 'user';
}
add_action('init', 'snn_edu_custom_author_base');

// Step 2: Add rewrite rules for 'user' and 'instructor' with numeric IDs
function snn_edu_custom_author_rewrite_rules($author_rewrite) {
    if (!snn_edu_get_option('enable_custom_author_urls', false)) {
        return $author_rewrite;
    }
    
    // Rules for /user/ base (numeric ID only)
    $user_rules = array(
        'user/([0-9]+)/?$' => 'index.php?author=$matches[1]',
        'user/([0-9]+)/page/?([0-9]{1,})/?$' => 'index.php?author=$matches[1]&paged=$matches[2]',
    );
    
    // Rules for /instructor/ base (numeric ID only)
    $instructor_rules = array(
        'instructor/([0-9]+)/?$' => 'index.php?author=$matches[1]',
        'instructor/([0-9]+)/page/?([0-9]{1,})/?$' => 'index.php?author=$matches[1]&paged=$matches[2]',
    );
    
    // ONLY return our custom rules (this removes /author/ rules)
    return array_merge($instructor_rules, $user_rules);
}
add_filter('author_rewrite_rules', 'snn_edu_custom_author_rewrite_rules');

// Step 3: Redirect /author/ URLs (both username and ID based) before WordPress processes them
function snn_edu_redirect_old_author_base() {
    if (!snn_edu_get_option('enable_custom_author_urls', false)) {
        return;
    }
    
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Check if URL contains /author/
    if (preg_match('#/author/([^/]+)(/page/([0-9]+))?/?$#', $request_uri, $matches)) {
        $identifier = $matches[1];
        $page = isset($matches[3]) ? $matches[3] : '';
        
        // Try to get user (by ID if numeric, by slug if not)
        if (is_numeric($identifier)) {
            $user = get_user_by('id', $identifier);
        } else {
            $user = get_user_by('slug', $identifier);
        }
        
        if ($user) {
            $is_instructor = in_array('instructor', (array) $user->roles);
            $base = $is_instructor ? 'instructor' : 'user';
            
            // Build redirect URL with user ID
            $redirect_url = home_url("/{$base}/{$user->ID}");
            if ($page) {
                $redirect_url .= "/page/{$page}";
            }
            $redirect_url .= '/';
            
            wp_redirect($redirect_url, 301);
            exit();
        }
    }
}
add_action('template_redirect', 'snn_edu_redirect_old_author_base', 1);

// Step 4: Modify author links to use ID and correct base
function snn_edu_custom_author_link($link, $author_id) {
    if (!snn_edu_get_option('enable_custom_author_urls', false)) {
        return $link;
    }
    
    $user = get_userdata($author_id);
    
    if ($user) {
        $base = in_array('instructor', (array) $user->roles) ? 'instructor' : 'user';
        // Replace the entire author link with ID-based URL
        $link = home_url("/{$base}/{$author_id}/");
    }
    
    return $link;
}
add_filter('author_link', 'snn_edu_custom_author_link', 10, 2);

// Step 5: Enforce correct base - 404 if wrong base is used
function snn_edu_enforce_author_base() {
    if (!snn_edu_get_option('enable_custom_author_urls', false)) {
        return;
    }
    
    if (is_author()) {
        $author = get_queried_object();
        $current_url = $_SERVER['REQUEST_URI'];
        
        $is_instructor = in_array('instructor', (array) $author->roles);
        $using_instructor_base = (strpos($current_url, '/instructor/') !== false);
        $using_user_base = (strpos($current_url, '/user/') !== false);
        
        // If instructor accessed via /user/ OR regular user accessed via /instructor/
        if (($is_instructor && $using_user_base) || (!$is_instructor && $using_instructor_base)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit();
        }
    }
}
add_action('template_redirect', 'snn_edu_enforce_author_base', 10);

/**
 * ==========================================
 * FEATURE 5: Video Duration Shortcodes
 * ==========================================
 */

/**
 * Get video duration from attachment ID
 */
function snn_edu_get_video_duration($attachment_id) {
    if (empty($attachment_id)) {
        return 0;
    }
    
    // Get the attachment metadata
    $metadata = wp_get_attachment_metadata($attachment_id);
    
    if (!empty($metadata['length'])) {
        return (int) $metadata['length'];
    }
    
    if (!empty($metadata['length_formatted'])) {
        // Try to parse formatted time like "1:23:45"
        $parts = explode(':', $metadata['length_formatted']);
        $seconds = 0;
        if (count($parts) == 3) {
            $seconds = ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        } elseif (count($parts) == 2) {
            $seconds = ($parts[0] * 60) + $parts[1];
        }
        return $seconds;
    }
    
    return 0;
}

/**
 * Format seconds to hours and minutes
 */
function snn_edu_format_duration($total_seconds) {
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    
    if ($hours > 0) {
        return sprintf('%d hour%s %d minute%s', $hours, $hours > 1 ? 's' : '', $minutes, $minutes != 1 ? 's' : '');
    } else {
        return sprintf('%d minute%s', $minutes, $minutes != 1 ? 's' : '');
    }
}

/**
 * Shortcode: Single video duration
 * Usage: [snn_course_single_hour_minutes_video field="video_field"]
 */
function snn_edu_single_video_duration_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'field' => ''
    ), $atts, 'snn_course_single_hour_minutes_video');
    
    $custom_field = trim($atts['field']);
    
    if (empty($custom_field)) {
        return '<span class="snn-video-error">Error: No custom field specified</span>';
    }
    
    // Get current post ID
    $post_id = get_the_ID();
    if (!$post_id) {
        return '<span class="snn-video-error">Error: No post found</span>';
    }
    
    // Get the attachment ID from custom field
    $attachment_id = get_post_meta($post_id, $custom_field, true);
    
    if (empty($attachment_id)) {
        return '<span class="snn-video-duration">0 minutes</span>';
    }
    
    // Get video duration
    $duration = snn_edu_get_video_duration($attachment_id);
    
    if ($duration == 0) {
        return '<span class="snn-video-duration">0 minutes</span>';
    }
    
    $formatted = snn_edu_format_duration($duration);
    
    return '<span class="snn-video-duration">' . esc_html($formatted) . '</span>';
}

/**
 * Shortcode: Total video duration from parent and children
 * Usage: [snn_course_total_hour_minutes_videos field="video_field"]
 */
function snn_edu_total_video_duration_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'field' => ''
    ), $atts, 'snn_course_total_hour_minutes_videos');
    
    $custom_field = trim($atts['field']);
    
    if (empty($custom_field)) {
        return '<span class="snn-video-error">Error: No custom field specified</span>';
    }
    
    // Get current post ID
    $post_id = get_the_ID();
    if (!$post_id) {
        return '<span class="snn-video-error">Error: No post found</span>';
    }
    
    $total_duration = 0;
    $attachment_ids = array();
    
    // Get attachment ID from parent post
    $parent_attachment = get_post_meta($post_id, $custom_field, true);
    if (!empty($parent_attachment)) {
        $attachment_ids[] = $parent_attachment;
    }
    
    // Get all child posts
    $child_posts = get_children(array(
        'post_parent' => $post_id,
        'post_type'   => get_post_type($post_id),
        'post_status' => 'publish',
        'numberposts' => -1
    ));
    
    // Get attachment IDs from all child posts
    foreach ($child_posts as $child) {
        $child_attachment = get_post_meta($child->ID, $custom_field, true);
        if (!empty($child_attachment)) {
            $attachment_ids[] = $child_attachment;
        }
    }
    
    // Calculate total duration
    foreach ($attachment_ids as $attachment_id) {
        $duration = snn_edu_get_video_duration($attachment_id);
        $total_duration += $duration;
    }
    
    if ($total_duration == 0) {
        return '<span class="snn-video-total-duration">0 minutes</span>';
    }
    
    $formatted = snn_edu_format_duration($total_duration);
    
    return '<span class="snn-video-total-duration">' . esc_html($formatted) . '</span>';
}

// Register shortcodes
add_shortcode('snn_course_single_hour_minutes_video', 'snn_edu_single_video_duration_shortcode');
add_shortcode('snn_course_total_hour_minutes_videos', 'snn_edu_total_video_duration_shortcode');

/**
 * ==========================================
 * FEATURE 6: Comment Ratings Column
 * ==========================================
 */

/**
 * Add custom column to comments list
 */
function snn_edu_add_comment_rating_column($columns) {
    if (!snn_edu_get_option('enable_comment_ratings', false)) {
        return $columns;
    }
    
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        // Add rating column after author column
        if ($key === 'author') {
            $new_columns['snn_rating'] = 'Rating';
        }
    }
    return $new_columns;
}
add_filter('manage_edit-comments_columns', 'snn_edu_add_comment_rating_column');

/**
 * Display rating stars in the custom column
 */
function snn_edu_display_comment_rating_column($column, $comment_id) {
    if (!snn_edu_get_option('enable_comment_ratings', false)) {
        return;
    }
    
    if ($column === 'snn_rating') {
        $rating = get_comment_meta($comment_id, 'snn_rating_comment', true);
        $rating = intval($rating);
        
        // Ensure rating is between 0 and 5
        $rating = max(0, min(5, $rating));
        
        echo '<div class="snn-comment-rating">';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                echo '<span class="snn-star snn-star-filled">★</span>';
            } else {
                echo '<span class="snn-star snn-star-empty">★</span>';
            }
        }
        echo '</div>';
    }
}
add_action('manage_comments_custom_column', 'snn_edu_display_comment_rating_column', 10, 2);

/**
 * Add CSS for rating stars in admin
 */
function snn_edu_comment_rating_admin_css() {
    if (!snn_edu_get_option('enable_comment_ratings', false)) {
        return;
    }
    
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-comments') {
        echo '<style>
            .snn-comment-rating {
                font-size: 16px;
                line-height: 1;
                white-space: nowrap;
            }
            .snn-star {
                display: inline-block; 
            }
            .snn-star-filled {
                color: #ffc107;
                font-size:24px;
            }
            .snn-star-empty {
                color: #727272ff;
                font-size:24px;
            }
        </style>';
    }
}
add_action('admin_head', 'snn_edu_comment_rating_admin_css');

/**
 * Add metabox to comment edit screen for rating field
 */
function snn_edu_add_comment_rating_metabox() {
    if (!snn_edu_get_option('enable_comment_ratings', false)) {
        return;
    }
    
    add_meta_box(
        'snn_comment_rating_metabox',
        'Comment Rating',
        'snn_edu_comment_rating_metabox_html',
        'comment',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_comment', 'snn_edu_add_comment_rating_metabox');

/**
 * Display the metabox content
 */
function snn_edu_comment_rating_metabox_html($comment) {
    $rating = get_comment_meta($comment->comment_ID, 'snn_rating_comment', true);
    $rating = !empty($rating) ? intval($rating) : 0;
    
    wp_nonce_field('snn_comment_rating_metabox', 'snn_comment_rating_nonce', false);
    ?>
    <p>
        <label for="snn_rating_comment">Rating (0-5):</label><br>
        <select name="snn_rating_comment" id="snn_rating_comment" style="width: 100%; max-width: 200px;">
            <option value="0" <?php selected($rating, 0); ?>>0 - No Rating</option>
            <option value="1" <?php selected($rating, 1); ?>>1 Star</option>
            <option value="2" <?php selected($rating, 2); ?>>2 Stars</option>
            <option value="3" <?php selected($rating, 3); ?>>3 Stars</option>
            <option value="4" <?php selected($rating, 4); ?>>4 Stars</option>
            <option value="5" <?php selected($rating, 5); ?>>5 Stars</option>
        </select>
    </p>
    <p class="description">Select the star rating for this comment (stored in the <code>snn_rating_comment</code> custom field).</p>
    
    <style>
        #snn_comment_rating_metabox {
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        #snn_comment_rating_metabox h2 {
            background: #2271b1;
            color: #fff;
            margin: 0;
            padding: 12px;
        }
        #snn_comment_rating_metabox .inside {
            padding: 15px;
        }
    </style>
    <?php
}

/**
 * Save the rating when comment is updated
 */
function snn_edu_save_comment_rating_metabox($comment_id) {
    if (!snn_edu_get_option('enable_comment_ratings', false)) {
        return;
    }
    
    // Verify nonce
    if (!isset($_POST['snn_comment_rating_nonce']) || 
        !wp_verify_nonce($_POST['snn_comment_rating_nonce'], 'snn_comment_rating_metabox')) {
        return;
    }
    
    // Check if user has permission to edit comments
    if (!current_user_can('edit_comment', $comment_id)) {
        return;
    }
    
    // Save the rating
    if (isset($_POST['snn_rating_comment'])) {
        $rating = intval($_POST['snn_rating_comment']);
        $rating = max(0, min(5, $rating)); // Ensure rating is between 0 and 5
        update_comment_meta($comment_id, 'snn_rating_comment', $rating);
    }
}
add_action('edit_comment', 'snn_edu_save_comment_rating_metabox');

/**
 * ==========================================
 * FEATURE 7: Average Comment Rating Shortcode
 * ==========================================
 */

/**
 * Shortcode: Calculate average rating from all comments on current post
 * Usage: [snn_rating_comment_total_average]
 */
function snn_edu_average_comment_rating_shortcode($atts) {
    // Get current post ID
    $post_id = get_the_ID();
    if (!$post_id) {
        return '0';
    }
    
    // Get all approved comments for this post
    $comments = get_comments(array(
        'post_id' => $post_id,
        'status' => 'approve',
        'type' => 'comment'
    ));
    
    if (empty($comments)) {
        return '0';
    }
    
    $total_rating = 0;
    $rating_count = 0;
    
    // Loop through comments and collect ratings
    foreach ($comments as $comment) {
        $rating = get_comment_meta($comment->comment_ID, 'snn_rating_comment', true);
        
        // Only count if rating exists and is a valid number
        if (!empty($rating) && is_numeric($rating)) {
            $total_rating += floatval($rating);
            $rating_count++;
        }
    }
    
    // Calculate average
    if ($rating_count == 0) {
        return '0';
    }
    
    $average = $total_rating / $rating_count;
    
    // Round to nearest 0.5
    $average = round($average * 2) / 2;
    
    // Format the number (remove .0 for whole numbers)
    if (floor($average) == $average) {
        return number_format($average, 0);
    } else {
        return number_format($average, 1);
    }
}

// Register shortcode
add_shortcode('snn_rating_comment_total_average', 'snn_edu_average_comment_rating_shortcode');

/**
 * ==========================================
 * SETTINGS PAGE
 * ==========================================
 */

// Add settings page to Settings menu
function snn_edu_add_settings_page() {
    add_options_page(
        'SNN Edu Utilities Settings',
        'SNN Edu Utilities',
        'manage_options',
        'snn-edu-utilities',
        'snn_edu_settings_page_html'
    );
}
add_action('admin_menu', 'snn_edu_add_settings_page');

// Register settings
function snn_edu_register_settings() {
    register_setting('snn_edu_settings_group', 'snn_edu_settings', 'snn_edu_sanitize_settings');
    
    add_settings_section(
        'snn_edu_main_section',
        'Feature Controls',
        'snn_edu_main_section_callback',
        'snn-edu-utilities'
    );
    
    add_settings_field(
        'enable_admin_restriction',
        'Restrict wp-admin to Administrators',
        'snn_edu_admin_restriction_callback',
        'snn-edu-utilities',
        'snn_edu_main_section'
    );
    
    add_settings_field(
        'enable_admin_bar_restriction',
        'Hide Admin Bar for Non-Admins',
        'snn_edu_admin_bar_restriction_callback',
        'snn-edu-utilities',
        'snn_edu_main_section'
    );
    
    add_settings_field(
        'enable_custom_author_urls',
        'Enable Custom Author Permalinks',
        'snn_edu_custom_author_urls_callback',
        'snn-edu-utilities',
        'snn_edu_main_section'
    );
    
    add_settings_field(
        'enable_comment_ratings',
        'Show Comment Ratings Column',
        'snn_edu_comment_ratings_callback',
        'snn-edu-utilities',
        'snn_edu_main_section'
    );
}
add_action('admin_init', 'snn_edu_register_settings');

// Section callback
function snn_edu_main_section_callback() {
    echo '<p>Enable or disable features as needed. Changes take effect immediately.</p>';
}

// Field callbacks
function snn_edu_admin_restriction_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_admin_restriction']) && $options['enable_admin_restriction'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_admin_restriction]" value="1" ' . $checked . '>';
    echo '<p class="description">Prevents non-administrators from accessing wp-admin area.</p>';
}

function snn_edu_admin_bar_restriction_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_admin_bar_restriction']) && $options['enable_admin_bar_restriction'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_admin_bar_restriction]" value="1" ' . $checked . '>';
    echo '<p class="description">Hides the WordPress admin bar for non-administrators.</p>';
}

function snn_edu_custom_author_urls_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_custom_author_urls']) && $options['enable_custom_author_urls'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_custom_author_urls]" value="1" ' . $checked . '>';
    echo '<p class="description">Changes author URLs to /user/ID or /instructor/ID based on user role. <strong>Flush permalinks after enabling/disabling.</strong></p>';
}

function snn_edu_comment_ratings_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_comment_ratings']) && $options['enable_comment_ratings'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_comment_ratings]" value="1" ' . $checked . '>';
    echo '<p class="description">Displays a star rating column in the comments list based on the <code>snn_rating_comment</code> custom field. Shows 5 stars total with filled (yellow) and empty (gray) stars.</p>';
}

// Sanitize settings
function snn_edu_sanitize_settings($input) {
    $sanitized = array();
    
    $sanitized['enable_admin_restriction'] = isset($input['enable_admin_restriction']) ? 1 : 0;
    $sanitized['enable_admin_bar_restriction'] = isset($input['enable_admin_bar_restriction']) ? 1 : 0;
    $sanitized['enable_custom_author_urls'] = isset($input['enable_custom_author_urls']) ? 1 : 0;
    $sanitized['enable_comment_ratings'] = isset($input['enable_comment_ratings']) ? 1 : 0;
    
    // Flush rewrite rules if custom author URLs setting changed
    if (isset($input['enable_custom_author_urls']) != snn_edu_get_option('enable_custom_author_urls')) {
        flush_rewrite_rules();
    }
    
    return $sanitized;
}

// Settings page HTML
function snn_edu_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Show success message if settings saved
    if (isset($_GET['settings-updated'])) {
        add_settings_error('snn_edu_messages', 'snn_edu_message', 'Settings Saved', 'updated');
    }
    
    settings_errors('snn_edu_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('snn_edu_settings_group');
            do_settings_sections('snn-edu-utilities');
            submit_button('Save Settings');
            ?>
        </form>
        
        <hr>
        
        <div class="snn-edu-info-section">
            <h2>Available Shortcodes</h2>
            
            <h3>🎬 Video Duration Shortcodes</h3>
            
            <h4>Single Video Duration</h4>
            <p>Displays the duration of a video from the current post's custom field:</p>
            <div class="snn-shortcode-box">
                <code>[snn_course_single_hour_minutes_video field="your_custom_field_name"]</code>
                <button class="button button-small snn-copy-btn" onclick="snnCopyToClipboard('[snn_course_single_hour_minutes_video field=&quot;your_custom_field_name&quot;]')">Copy</button>
            </div>
            <p class="description">Replace <code>your_custom_field_name</code> with your actual custom field name that stores the video attachment ID.</p>
            
            <h4>Total Course Duration</h4>
            <p>Displays the combined duration of videos from parent post and all child posts:</p>
            <div class="snn-shortcode-box">
                <code>[snn_course_total_hour_minutes_videos field="your_custom_field_name"]</code>
                <button class="button button-small snn-copy-btn" onclick="snnCopyToClipboard('[snn_course_total_hour_minutes_videos field=&quot;your_custom_field_name&quot;]')">Copy</button>
            </div>
            <p class="description">Use this on parent/course pages to show total duration of all lessons. Replace <code>your_custom_field_name</code> with your actual custom field name.</p>
            
            <h4>Example Usage:</h4>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>If your custom field is named <code>lesson_video</code>, use: <code>[snn_course_single_hour_minutes_video field="lesson_video"]</code></li>
                <li>If your custom field is named <code>course_video</code>, use: <code>[snn_course_total_hour_minutes_videos field="course_video"]</code></li>
            </ul>
            
            <h3>⭐ Average Comment Rating</h3>
            <p>Displays the average rating from all comments on the current post:</p>
            <div class="snn-shortcode-box">
                <code>[snn_rating_comment_total_average]</code>
                <button class="button button-small snn-copy-btn" onclick="snnCopyToClipboard('[snn_rating_comment_total_average]')">Copy</button>
            </div>
            <p class="description">Reads the <code>snn_rating_comment</code> custom field from all approved comments, calculates the average, and outputs a number (e.g., 3, 4.5, 2.5). Returns 0 if no ratings exist.</p>
        </div>
        
        <hr>
        
        <div class="snn-edu-info-section">
            <h2>Feature Information</h2>
            
            <h3>🔒 Restrict wp-admin to Administrators</h3>
            <p>Redirects non-administrator users away from the wp-admin area to the home page. AJAX requests are not affected.</p>
            
            <h3>👁️ Hide Admin Bar for Non-Admins</h3>
            <p>Removes the WordPress admin bar from the front-end for users who don't have the 'manage_options' capability.</p>
            
            <h3>🔗 Custom Author Permalinks</h3>
            <p>Changes author archive URLs to use numeric IDs instead of usernames:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Regular users: <code>/user/123</code></li>
                <li>Instructors: <code>/instructor/456</code></li>
                <li>Old <code>/author/</code> URLs automatically redirect to the new format</li>
            </ul>
            <p><strong>Important:</strong> After enabling or disabling this feature, go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules.</p>
            
            <h3>⭐ Comment Ratings Column</h3>
            <p>Adds a rating column to the comments list in wp-admin that displays star ratings based on the <code>snn_rating_comment</code> custom field:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Reads integer values (1-5) from the <code>snn_rating_comment</code> comment meta field</li>
                <li>Displays 5 stars total: filled stars (yellow) for the rating value, empty stars (gray) for the remainder</li>
                <li>Example: A rating of 3 shows ★★★☆☆ (3 yellow, 2 gray)</li>
            </ul>
        </div>
        
        <hr>
        
        <div class="snn-edu-footer">
            <p><strong>SNN Edu Utilities</strong> v<?php echo SNN_EDU_VERSION; ?> | By <a href="https://sinanisler.com" target="_blank">sinanisler</a> | <a href="https://github.com/sinanisler/snn-edu-utilities" target="_blank">GitHub</a></p>
        </div>
    </div>
    
    <style>
        .snn-edu-info-section {
            background: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #2271b1;
            margin-top: 20px;
        }
        .snn-edu-info-section h3 {
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .snn-edu-info-section h4 {
            margin-top: 12px;
            margin-bottom: 8px;
            color: #1d2327;
        }
        .snn-edu-info-section code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        .snn-shortcode-box {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .snn-shortcode-box code {
            font-size: 14px;
            flex: 1;
            background: transparent;
            border: none;
            padding: 0;
        }
        .snn-copy-btn {
            margin-left: 15px;
        }
        .snn-edu-footer {
            text-align: center;
            color: #666;
            margin-top: 30px;
        }
    </style>
    
    <script>
        function snnCopyToClipboard(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    // Show success feedback
                    var btn = event.target;
                    var originalText = btn.textContent;
                    btn.textContent = 'Copied!';
                    btn.style.background = '#00a32a';
                    btn.style.borderColor = '#00a32a';
                    btn.style.color = '#fff';
                    
                    setTimeout(function() {
                        btn.textContent = originalText;
                        btn.style.background = '';
                        btn.style.borderColor = '';
                        btn.style.color = '';
                    }, 2000);
                }).catch(function(err) {
                    alert('Failed to copy: ' + err);
                });
            } else {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('Shortcode copied to clipboard!');
                } catch (err) {
                    alert('Failed to copy shortcode');
                }
                document.body.removeChild(textArea);
            }
        }
    </script>
    <?php
}

// Activation hook - flush rewrite rules
function snn_edu_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'snn_edu_activate');

// Deactivation hook - flush rewrite rules
function snn_edu_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'snn_edu_deactivate');
