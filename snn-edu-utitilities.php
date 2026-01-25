<?php   
/**  
 * Plugin Name: SNN Edu Utilities
 * Plugin URI: https://github.com/sinanisler/snn-edu-utilities
 * Description: Educational utilities including admin restrictions, dashboard notepad, and custom author permalinks with role-based URLs.
 * Version: 1.3
 * Author: sinanisler
 * Author URI: https://github.com/sinanisler
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snn
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SNN_EDU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SNN_EDU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SNN_EDU_PLUGIN_FILE', __FILE__);

// Include GitHub updater
require_once SNN_EDU_PLUGIN_DIR . 'github-update.php';
require_once SNN_EDU_PLUGIN_DIR . 'simple-page-order.php';
require_once SNN_EDU_PLUGIN_DIR . 'dynamic-tags.php';
require_once SNN_EDU_PLUGIN_DIR . 'guest-class-body.php';

// Register Bricks custom element after theme is loaded
add_action('init', function() {
    if (class_exists('\Bricks\Elements')) {
        \Bricks\Elements::register_element(SNN_EDU_PLUGIN_DIR . 'comment-list-custom.php');
    }
}, 11);

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

    add_settings_field(
        'enable_user_meta_tracking',
        'Enable User Video Enrollment Tracking',
        'snn_edu_user_meta_tracking_callback',
        'snn-edu-utilities',
        'snn_edu_main_section'
    );

    add_settings_field(
        'enrollment_allowed_post_types',
        'Allowed Post Types for Enrollment',
        'snn_edu_enrollment_post_types_callback',
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
    echo '<p class="description">Redirects non-administrator users away from the wp-admin area to the home page. AJAX requests are not affected.</p>';
}

function snn_edu_admin_bar_restriction_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_admin_bar_restriction']) && $options['enable_admin_bar_restriction'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_admin_bar_restriction]" value="1" ' . $checked . '>';
    echo '<p class="description">Removes the WordPress admin bar from the front-end for users who don\'t have the \'manage_options\' capability.</p>';
}

function snn_edu_custom_author_urls_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_custom_author_urls']) && $options['enable_custom_author_urls'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_custom_author_urls]" value="1" ' . $checked . '>';
    echo '<p class="description">Changes author archive URLs to use numeric IDs instead of usernames:</p>';
    echo '<ul style="list-style: disc; margin-left: 20px;">';
    echo '<li>Regular users: <code>/user/123</code></li>';
    echo '<li>Instructors: <code>/instructor/456</code></li>';
    echo '<li>Old <code>/author/</code> URLs automatically redirect to the new format</li>';
    echo '</ul>';
    echo '<p class="description"><strong>Important:</strong> After enabling or disabling this feature, go to <a href="' . admin_url('options-permalink.php') . '">Settings → Permalinks</a> and click "Save Changes" to flush rewrite rules.</p>';
}

function snn_edu_comment_ratings_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_comment_ratings']) && $options['enable_comment_ratings'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_comment_ratings]" value="1" ' . $checked . '>';
    echo '<p class="description">Adds a rating column to the comments list in wp-admin that displays star ratings based on the <code>snn_rating_comment</code> custom field:</p>';
    echo '<ul style="list-style: disc; margin-left: 20px;">';
    echo '<li>Reads integer values (1-5) from the <code>snn_rating_comment</code> comment meta field</li>';
    echo '<li>Displays 5 stars total: filled stars (yellow) for the rating value, empty stars (gray) for the remainder</li>';
    echo '<li>Example: A rating of 3 shows ★★★☆☆ (3 yellow, 2 gray)</li>';
    echo '</ul>';
}

function snn_edu_user_meta_tracking_callback() {
    $options = get_option('snn_edu_settings', array());
    $checked = isset($options['enable_user_meta_tracking']) && $options['enable_user_meta_tracking'] ? 'checked' : '';
    echo '<input type="checkbox" name="snn_edu_settings[enable_user_meta_tracking]" value="1" ' . $checked . '>';
    echo '<p class="description">Enables video enrollment tracking system that saves user progress to custom fields:</p>';
    echo '<ul style="list-style: disc; margin-left: 20px;">';
    echo '<li><strong>REST API Endpoints:</strong> <code>/wp-json/snn-edu/v1/enroll</code>, <code>/wp-json/snn-edu/v1/unenroll</code>, <code>/wp-json/snn-edu/v1/enrollments</code></li>';
    echo '<li><strong>User Meta Field:</strong> Stores enrolled post IDs in <code>snn_edu_enrolled_posts</code> as an array</li>';
    echo '<li><strong>Shortcode:</strong> Use <code>[snn_video_tracker]</code> to auto-enroll users when video events fire</li>';
    echo '<li><strong>Shortcode Parameters:</strong> <code>events="both|started|completed"</code> (default: both), <code>post_id="123"</code> (optional), <code>debug="true|false"</code></li>';
    echo '<li><strong>JavaScript Events:</strong> Listens to <code>snn_video_started</code> and/or <code>snn_video_completed</code> custom events (both by default)</li>';
    echo '<li><strong>Post ID Detection:</strong> Automatically gets the correct post ID from video events, works with parent/child page hierarchies</li>';
    echo '<li><strong>Parent Enrollment:</strong> When enrolling in a child post, automatically enrolls in ALL ancestor parents (immediate parent, grandparent, etc. up to top-level)</li>';
    echo '<li><strong>Admin Meta Box:</strong> View enrolled courses in user profile edit page</li>';
    echo '<li><strong>Security:</strong> Only works for logged-in users, sanitizes all post IDs (integers only)</li>';
    echo '</ul>';
    echo '<p class="description"><strong>JavaScript Usage Example:</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">
// Listen for video completion event
document.addEventListener(\'snn_video_completed\', function(event) {
    const postId = event.detail.post_id;

    // Automatically enroll user via REST API
    snnEduEnrollUser(postId).then(response => {
        console.log(\'User enrolled!\', response);
    });
});

// Or manually enroll/unenroll
snnEduEnrollUser(123);      // Enroll in post 123
snnEduUnenrollUser(123);    // Unenroll from post 123
snnEduGetEnrollments();     // Get all enrollments
snnEduIsEnrolled(123);      // Check if enrolled
</pre>';
}

function snn_edu_enrollment_post_types_callback() {
    $options = get_option('snn_edu_settings', array());
    $selected_post_types = isset($options['enrollment_allowed_post_types']) && is_array($options['enrollment_allowed_post_types'])
        ? $options['enrollment_allowed_post_types']
        : array();

    // Get all public post types
    $post_types = get_post_types(array('public' => true), 'objects');

    echo '<div style="max-width: 600px;">';
    echo '<p class="description" style="margin-bottom: 10px;">Select which post types can be enrolled. Only posts of these types will be allowed for enrollment tracking.</p>';

    if (empty($post_types)) {
        echo '<p><em>No public post types found.</em></p>';
    } else {
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">';

        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected_post_types) ? 'checked' : '';
            echo '<label style="display: flex; align-items: center; padding: 8px; background: #f9f9f9; border-radius: 4px; cursor: pointer;">';
            echo '<input type="checkbox" name="snn_edu_settings[enrollment_allowed_post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' style="margin-right: 8px;">';
            echo '<span style="font-weight: 500;">' . esc_html($post_type->label) . '</span>';
            echo '<code style="margin-left: 5px; font-size: 11px; color: #666;">(' . esc_html($post_type->name) . ')</code>';
            echo '</label>';
        }

        echo '</div>';
    }

    echo '<p class="description" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
    echo '<strong>Note:</strong> If no post types are selected, enrollment will be allowed for <strong>all</strong> public post types. Select at least one to restrict enrollment to specific post types.';
    echo '</p>';
    echo '</div>';
}

// Sanitize settings
function snn_edu_sanitize_settings($input) {
    $sanitized = array();

    $sanitized['enable_admin_restriction'] = isset($input['enable_admin_restriction']) ? 1 : 0;
    $sanitized['enable_admin_bar_restriction'] = isset($input['enable_admin_bar_restriction']) ? 1 : 0;
    $sanitized['enable_custom_author_urls'] = isset($input['enable_custom_author_urls']) ? 1 : 0;
    $sanitized['enable_comment_ratings'] = isset($input['enable_comment_ratings']) ? 1 : 0;
    $sanitized['enable_user_meta_tracking'] = isset($input['enable_user_meta_tracking']) ? 1 : 0;

    // Sanitize enrollment allowed post types
    if (isset($input['enrollment_allowed_post_types']) && is_array($input['enrollment_allowed_post_types'])) {
        $sanitized['enrollment_allowed_post_types'] = array_map('sanitize_key', $input['enrollment_allowed_post_types']);
    } else {
        $sanitized['enrollment_allowed_post_types'] = array();
    }

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

    </div>

    <style>
        .snn-edu-footer {
            text-align: center;
            color: #666;
            margin-top: 30px;
        }
    </style>
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

/**
 * ==========================================
 * FEATURE 7: USER META - VIDEO ENROLLMENT TRACKING
 * ==========================================
 */

/**
 * Get user enrollments safely
 * Uses WordPress native get_user_meta (scales with object caching)
 *
 * @param int $user_id The user ID
 * @return array The enrollments array (always returns array, never false)
 */
function snn_edu_get_enrollments_safe($user_id) {
    $enrollments = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);

    if (is_array($enrollments)) {
        return array_values(array_map('intval', $enrollments));
    }

    return array();
}

/**
 * Safe enrollment - ONLY ADDS, never removes
 * Simple and bulletproof: merge new IDs with existing, duplicates handled by array_unique
 * Even with race conditions, data can never be lost
 *
 * @param int $user_id The user ID
 * @param array $new_post_ids Array of post IDs to ADD
 * @return array Result with success status and final enrollments
 */
function snn_edu_add_enrollments_safe($user_id, $new_post_ids) {
    if (!is_array($new_post_ids)) {
        return array('success' => false, 'error' => 'Invalid post IDs');
    }

    // Sanitize all post IDs
    $new_post_ids = array_map('absint', $new_post_ids);
    $new_post_ids = array_filter($new_post_ids);

    if (empty($new_post_ids)) {
        return array('success' => false, 'error' => 'No valid post IDs to add');
    }

    // Get current enrollments
    $current_enrollments = snn_edu_get_enrollments_safe($user_id);

    // ONLY ADD - merge new IDs with existing (array_unique prevents duplicates)
    $merged_enrollments = array_unique(array_merge($current_enrollments, $new_post_ids));
    $merged_enrollments = array_map('intval', $merged_enrollments);
    $merged_enrollments = array_values($merged_enrollments);

    // Determine what was actually added
    $actually_added = array_values(array_diff($merged_enrollments, $current_enrollments));

    // Update only if there are new enrollments
    if (!empty($actually_added)) {
        update_user_meta($user_id, 'snn_edu_enrolled_posts', $merged_enrollments);
    }

    return array(
        'success' => true,
        'added' => $actually_added,
        'total_count' => count($merged_enrollments),
        'all_enrollments' => $merged_enrollments
    );
}

/**
 * Unenrollment is DISABLED to prevent data loss
 * Enrollments are permanent - once enrolled, always enrolled
 *
 * @param int $user_id The user ID
 * @param int $post_id The post ID
 * @return array Error response
 */
function snn_edu_remove_enrollment_safe($user_id, $post_id) {
    return array(
        'success' => false,
        'message' => 'Unenrollment is disabled to protect user data',
        'post_id' => absint($post_id)
    );
}

/**
 * Check if a post type is allowed for enrollment
 */
function snn_edu_is_post_type_allowed($post_type) {
    $allowed_post_types = snn_edu_get_option('enrollment_allowed_post_types', array());

    // If no post types are selected, allow all public post types
    if (empty($allowed_post_types)) {
        return true;
    }

    // Check if the post type is in the allowed list
    return in_array($post_type, $allowed_post_types, true);
}

/**
 * Register REST API routes for user enrollment tracking
 */
function snn_edu_user_meta_register_routes() {
    register_rest_route('snn-edu/v1', '/enroll', array(
        'methods' => 'POST',
        'callback' => 'snn_edu_user_meta_enroll_user',
        'permission_callback' => 'snn_edu_user_meta_check_permission',
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                },
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    register_rest_route('snn-edu/v1', '/unenroll', array(
        'methods' => 'POST',
        'callback' => 'snn_edu_user_meta_unenroll_user',
        'permission_callback' => 'snn_edu_user_meta_check_permission',
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && intval($param) > 0;
                },
                'sanitize_callback' => 'absint',
            ),
        ),
    ));

    register_rest_route('snn-edu/v1', '/enrollments', array(
        'methods' => 'GET',
        'callback' => 'snn_edu_user_meta_get_enrollments',
        'permission_callback' => 'snn_edu_user_meta_check_permission',
    ));
}
add_action('rest_api_init', 'snn_edu_user_meta_register_routes');

/**
 * Permission callback - check if feature is enabled and user is logged in
 */
function snn_edu_user_meta_check_permission() {
    // Check if feature is enabled
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return new WP_Error(
            'feature_disabled',
            'Video enrollment tracking feature is not enabled. Please enable it in Settings → SNN Edu Utilities.',
            array('status' => 403)
        );
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        return new WP_Error(
            'not_logged_in',
            'You must be logged in to use this feature.',
            array('status' => 401)
        );
    }

    return true;
}

/**
 * Enroll user in a post (add post_id to user meta)
 * Also enrolls in ALL ancestor parents (immediate parent, grandparent, etc. up to level 0)
 *
 * CRITICAL: Uses safe enrollment functions to prevent data loss
 */
function snn_edu_user_meta_enroll_user($request) {
    $post_id = $request->get_param('post_id');
    $user_id = get_current_user_id();

    // Verify post exists
    $post = get_post($post_id);
    if (!$post) {
        return new WP_Error(
            'invalid_post',
            'Post does not exist',
            array('status' => 404)
        );
    }

    // Check if post type is allowed for enrollment
    if (!snn_edu_is_post_type_allowed($post->post_type)) {
        return new WP_Error(
            'post_type_not_allowed',
            'This post type is not allowed for enrollment. Please check the plugin settings.',
            array('status' => 403)
        );
    }

    $post_id_int = absint($post_id);
    $posts_to_enroll = array($post_id_int);

    // Check if post has a parent and get ALL ancestor parents (not just top-level)
    if ($post->post_parent > 0) {
        $ancestor_ids = snn_edu_get_all_ancestors($post->ID);
        $posts_to_enroll = array_merge($posts_to_enroll, $ancestor_ids);
    }

    // Use the SAFE enrollment function (prevents data loss, handles race conditions)
    $result = snn_edu_add_enrollments_safe($user_id, $posts_to_enroll);

    if ($result['success']) {
        if (!empty($result['added'])) {
            return array(
                'success' => true,
                'message' => 'Successfully enrolled',
                'post_id' => $post_id_int,
                'enrolled_posts' => $result['added'],
                'enrolled_count' => $result['total_count'],
                'all_enrollments' => $result['all_enrollments'],
            );
        } else {
            // Get current enrollments for response
            $current = snn_edu_get_enrollments_safe($user_id);
            return array(
                'success' => false,
                'message' => 'Already enrolled in all posts (current + ancestors)',
                'post_id' => $post_id_int,
                'current_enrollments' => $current,
            );
        }
    }

    // Error occurred
    return new WP_Error(
        'enrollment_failed',
        isset($result['error']) ? $result['error'] : 'Enrollment failed',
        array('status' => 500)
    );
}

/**
 * Get top-level parent (level 0) of a post
 * Recursively traverses up the parent hierarchy
 */
function snn_edu_get_top_level_parent($post_id) {
    $post = get_post($post_id);

    if (!$post) {
        return false;
    }

    // If post has no parent, it's already top-level
    if ($post->post_parent == 0) {
        return $post->ID;
    }

    // Recursively get parent until we reach top level
    return snn_edu_get_top_level_parent($post->post_parent);
}

/**
 * Get ALL ancestor IDs (all parent levels) of a post
 * Returns array of parent IDs from immediate parent up to top-level (level 0)
 * Example: If post hierarchy is Level0 > Level1 > Level2 (current), returns [Level1_ID, Level0_ID]
 */
function snn_edu_get_all_ancestors($post_id) {
    $ancestors = array();
    $current_post = get_post($post_id);

    if (!$current_post) {
        return $ancestors;
    }

    // Traverse up the parent hierarchy
    while ($current_post->post_parent > 0) {
        $parent_id = $current_post->post_parent;
        $ancestors[] = $parent_id;

        // Get the parent post to continue traversing
        $current_post = get_post($parent_id);

        if (!$current_post) {
            break; // Safety check: stop if parent doesn't exist
        }
    }

    return $ancestors;
}

/**
 * Unenroll user from a post (remove post_id from user meta)
 *
 * CRITICAL: Unenrollment is disabled to prevent data loss
 */
function snn_edu_user_meta_unenroll_user($request) {
    $post_id = $request->get_param('post_id');
    $user_id = get_current_user_id();

    // Use the SAFE unenrollment function (returns error - unenrollment is disabled)
    return snn_edu_remove_enrollment_safe($user_id, absint($post_id));
}

/**
 * Get all enrollments for current user
 *
 * CRITICAL: Uses safe retrieval function
 */
function snn_edu_user_meta_get_enrollments($request) {
    $user_id = get_current_user_id();

    // Use the SAFE enrollment retrieval function
    $enrollments = snn_edu_get_enrollments_safe($user_id);

    return array(
        'success' => true,
        'enrollments' => $enrollments,
        'count' => count($enrollments),
    );
}

/**
 * Add custom meta box to user edit screen
 */
function snn_edu_user_meta_add_meta_box() {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return;
    }

    add_meta_box(
        'snn_edu_user_enrollments',
        'Course Enrollments',
        'snn_edu_user_meta_render_meta_box',
        'user-edit',
        'normal',
        'high'
    );
}
add_action('load-user-edit.php', 'snn_edu_user_meta_add_meta_box');
add_action('load-profile.php', 'snn_edu_user_meta_add_meta_box');

/**
 * Render the meta box content
 */
function snn_edu_user_meta_render_meta_box($user) {
    // Use safe retrieval method
    $enrollments = snn_edu_get_enrollments_safe($user->ID);

    if (empty($enrollments)) {
        echo '<p>No enrollments yet.</p>';
        return;
    }

    echo '<div class="snn-edu-enrollments">';
    echo '<p><strong>Total Enrollments:</strong> ' . count($enrollments) . '</p>';
    echo '<table class="widefat striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Post ID</th>';
    echo '<th>Post Title</th>';
    echo '<th>Post Type</th>';
    echo '<th>Status</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($enrollments as $post_id) {
        $post = get_post($post_id);

        if ($post) {
            $edit_link = get_edit_post_link($post_id);
            $view_link = get_permalink($post_id);

            echo '<tr>';
            echo '<td>' . intval($post_id) . '</td>';
            echo '<td><a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($post->post_title) . '</a></td>';
            echo '<td>' . esc_html($post->post_type) . '</td>';
            echo '<td>' . esc_html($post->post_status) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url($view_link) . '" target="_blank" class="button button-small">View</a> ';
            echo '<a href="' . esc_url($edit_link) . '" target="_blank" class="button button-small">Edit</a>';
            echo '</td>';
            echo '</tr>';
        } else {
            echo '<tr>';
            echo '<td>' . intval($post_id) . '</td>';
            echo '<td colspan="4"><em>Post not found (may be deleted)</em></td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    echo '<style>
        .snn-edu-enrollments {
            margin: 15px 0;
        }
        .snn-edu-enrollments table {
            margin-top: 10px;
        }
        .snn-edu-enrollments th {
            font-weight: 600;
        }
    </style>';
}

/**
 * Add inline JavaScript for enrollment tracking in footer
 */
function snn_edu_user_meta_inline_script() {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return;
    }

    // Only add for logged-in users
    if (!is_user_logged_in()) {
        return;
    }

    $rest_url = rest_url('snn-edu/v1/');
    $nonce = wp_create_nonce('wp_rest');
    $user_id = get_current_user_id();
    ?>
    <script>
    /**
     * SNN Edu User Meta - Video Enrollment Tracker
     *
     * Listens for video events and tracks user enrollment via REST API
     */
    (function() {
        'use strict';

        // Config object
        const snnEduUserMeta = {
            restUrl: <?php echo json_encode($rest_url); ?>,
            nonce: <?php echo json_encode($nonce); ?>,
            userId: <?php echo json_encode($user_id); ?>
        };

        /**
         * Enroll user in a post via REST API
         */
        window.snnEduEnrollUser = function(postId, debug = false) {
            if (!postId || !Number.isInteger(parseInt(postId))) {
                if (debug) console.error('SNN Edu: Invalid post ID', postId);
                return Promise.reject('Invalid post ID');
            }

            const enrollmentData = {
                post_id: parseInt(postId)
            };

            return fetch(snnEduUserMeta.restUrl + 'enroll', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': snnEduUserMeta.nonce
                },
                body: JSON.stringify(enrollmentData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (debug) console.log('✅ SNN Edu: Successfully enrolled in post', postId);

                    // Dispatch custom event for other scripts to listen
                    document.dispatchEvent(new CustomEvent('snn_edu_enrolled', {
                        detail: {
                            post_id: postId,
                            enrolled_count: data.enrolled_count
                        }
                    }));
                } else {
                    if (debug) console.log('ℹ️ SNN Edu:', data.message, postId);
                }
                return data;
            })
            .catch(error => {
                if (debug) console.error('❌ SNN Edu: Enrollment failed', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Unenroll user from a post via REST API
         */
        window.snnEduUnenrollUser = function(postId, debug = false) {
            if (!postId || !Number.isInteger(parseInt(postId))) {
                if (debug) console.error('SNN Edu: Invalid post ID', postId);
                return Promise.reject('Invalid post ID');
            }

            const enrollmentData = {
                post_id: parseInt(postId)
            };

            return fetch(snnEduUserMeta.restUrl + 'unenroll', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': snnEduUserMeta.nonce
                },
                body: JSON.stringify(enrollmentData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (debug) console.log('✅ SNN Edu: Successfully unenrolled from post', postId);

                    // Dispatch custom event
                    document.dispatchEvent(new CustomEvent('snn_edu_unenrolled', {
                        detail: {
                            post_id: postId,
                            enrolled_count: data.enrolled_count
                        }
                    }));
                } else {
                    if (debug) console.log('ℹ️ SNN Edu:', data.message, postId);
                }
                return data;
            })
            .catch(error => {
                if (debug) console.error('❌ SNN Edu: Unenrollment failed', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Get all enrollments for current user
         */
        window.snnEduGetEnrollments = function(debug = false) {
            return fetch(snnEduUserMeta.restUrl + 'enrollments', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': snnEduUserMeta.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (debug) console.log('📚 SNN Edu: User enrollments', data);
                return data;
            })
            .catch(error => {
                if (debug) console.error('❌ SNN Edu: Failed to get enrollments', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Check if user is enrolled in a specific post
         */
        window.snnEduIsEnrolled = function(postId, debug = false) {
            return window.snnEduGetEnrollments(debug)
                .then(data => {
                    if (data.success && data.enrollments) {
                        return data.enrollments.includes(parseInt(postId));
                    }
                    return false;
                });
        };

    })();
    </script>
    <?php
}
add_action('wp_footer', 'snn_edu_user_meta_inline_script');

/**
 * Shortcode to enable video enrollment tracking
 * Usage: [snn_video_tracker] or [snn_video_tracker debug="true"]
 */
function snn_edu_user_meta_tracker_shortcode($atts) {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return '';
    }

    if (!is_user_logged_in()) {
        return '<p class="snn-edu-login-required">Please log in to track your progress.</p>';
    }

    $atts = shortcode_atts(array(
        'post_id' => '', // Allow manual post ID override
        'auto' => 'true', // Auto-enroll on event
        'debug' => 'false', // Enable debug mode
        'events' => 'both', // 'started', 'completed', or 'both'
    ), $atts);

    // Get post ID - priority: shortcode attribute > queried object ONLY
    $queried_object = get_queried_object();

    if (!empty($atts['post_id'])) {
        // Manual override via shortcode attribute
        $post_id = intval($atts['post_id']);
    } elseif ($queried_object && isset($queried_object->ID)) {
        // Use queried object ID (works correctly with child pages)
        $post_id = $queried_object->ID;
    } else {
        // Fallback - should rarely happen
        $post_id = 0;
    }

    $user_id = get_current_user_id();
    $is_debug = ($atts['debug'] === 'true');

    // Get current enrollments for debug display (using safe retrieval)
    $current_enrollments = snn_edu_get_enrollments_safe($user_id);
    $is_enrolled = in_array($post_id, $current_enrollments);

    // Get parent post info for debug display
    $current_post = get_post($post_id);
    $parent_id = $current_post ? $current_post->post_parent : 0;
    $all_ancestors = ($parent_id > 0) ? snn_edu_get_all_ancestors($post_id) : array();
    $immediate_parent_id = !empty($all_ancestors) ? $all_ancestors[0] : 0;
    $top_parent_id = !empty($all_ancestors) ? end($all_ancestors) : 0;
    $is_immediate_parent_enrolled = ($immediate_parent_id > 0) ? in_array($immediate_parent_id, $current_enrollments) : false;
    $is_top_parent_enrolled = ($top_parent_id > 0) ? in_array($top_parent_id, $current_enrollments) : false;

    // Get post type info for debug display
    $post_type = $current_post ? $current_post->post_type : 'unknown';
    $post_type_obj = get_post_type_object($post_type);
    $post_type_label = $post_type_obj ? $post_type_obj->label : $post_type;
    $is_post_type_allowed = snn_edu_is_post_type_allowed($post_type);
    $allowed_post_types = snn_edu_get_option('enrollment_allowed_post_types', array());

    ob_start();
    ?>
    <div class="snn-edu-tracker"
         data-post-id="<?php echo esc_attr($post_id); ?>"
         data-events="<?php echo esc_attr($atts['events']); ?>"
         data-auto="<?php echo esc_attr($atts['auto']); ?>"
         data-debug="<?php echo esc_attr($atts['debug']); ?>">

        

        <?php if ($is_debug): ?>
        <div class="snn-edu-debug-panel">
            <div class="snn-edu-debug-header">🔍 Debug Mode</div>

            <div class="snn-edu-debug-info">
                <strong>Configuration:</strong>
                <ul>
                    <li><strong>✅ Using Post ID:</strong> <code><?php echo $post_id; ?></code> (from Queried Object)</li>
                    <li>Current URL: <code><?php echo esc_html($_SERVER['REQUEST_URI']); ?></code></li>
                    <li>User ID: <code><?php echo $user_id; ?></code></li>
                    <li>Events Tracking: <code><?php echo esc_html($atts['events']); ?></code></li>
                    <li>Auto-enroll: <code><?php echo esc_html($atts['auto']); ?></code></li>
                    <li>Currently Enrolled: <code><?php echo $is_enrolled ? 'YES' : 'NO'; ?></code></li>
                    <li>Total Enrollments: <code><?php echo count($current_enrollments); ?></code></li>
                </ul>

                <strong>Post Type Information:</strong>
                <ul>
                    <li><strong>📝 Post Type:</strong> <code><?php echo esc_html($post_type_label); ?></code> <code style="color: #666;">(<?php echo esc_html($post_type); ?>)</code></li>
                    <li><strong>✓ Allowed for Enrollment:</strong> <code style="color: <?php echo $is_post_type_allowed ? '#10b981' : '#ef4444'; ?>;"><?php echo $is_post_type_allowed ? 'YES' : 'NO'; ?></code></li>
                    <?php if (!empty($allowed_post_types)): ?>
                    <li><strong>Configured Allowed Types:</strong> <code><?php echo esc_html(implode(', ', $allowed_post_types)); ?></code></li>
                    <?php else: ?>
                    <li><strong>Configured Allowed Types:</strong> <code>All public post types</code></li>
                    <?php endif; ?>
                </ul>

                <?php if ($parent_id > 0): ?>
                <strong>Parent Hierarchy:</strong>
                <ul>
                    <li><strong>🔗 Has Parent:</strong> <code>YES</code></li>
                    <li><strong>📊 Total Ancestors:</strong> <code><?php echo count($all_ancestors); ?></code></li>
                    <li><strong>🔗 All Ancestor IDs:</strong> <code><?php echo implode(' → ', $all_ancestors); ?></code> (immediate parent → top-level)</li>
                    <?php if ($immediate_parent_id > 0): ?>
                    <li><strong>👉 Level_1 (Immediate Parent ID):</strong> <code><?php echo $immediate_parent_id; ?></code></li>
                    <li><strong>Level_1 Enrolled:</strong> <code style="color: <?php echo $is_immediate_parent_enrolled ? '#10b981' : '#ef4444'; ?>;"><?php echo $is_immediate_parent_enrolled ? 'YES ✅' : 'NO ❌'; ?></code></li>
                    <?php endif; ?>
                    <?php if ($top_parent_id > 0): ?>
                    <li><strong>📂 Level_0 (Top-Level Parent ID):</strong> <code><?php echo $top_parent_id; ?></code></li>
                    <li><strong>Level_0 Enrolled:</strong> <code style="color: <?php echo $is_top_parent_enrolled ? '#10b981' : '#ef4444'; ?>;"><?php echo $is_top_parent_enrolled ? 'YES ✅' : 'NO ❌'; ?></code></li>
                    <?php endif; ?>
                </ul>
                <?php else: ?>
                <strong>Parent Hierarchy:</strong>
                <ul>
                    <li><strong>🔗 Has Parent:</strong> <code>NO</code> (This is a top-level post)</li>
                </ul>
                <?php endif; ?>
            </div>

            <div class="snn-edu-debug-buttons">
                <button onclick="snnEduTestEnroll(<?php echo $post_id; ?>)" class="snn-debug-btn">
                    Test Enroll (POST ID: <?php echo $post_id; ?>)
                </button>
                <button onclick="snnEduTestUnenroll(<?php echo $post_id; ?>)" class="snn-debug-btn">
                    Test Unenroll
                </button>
                <button onclick="snnEduTestGetEnrollments()" class="snn-debug-btn">
                    Get All Enrollments
                </button>
                <button onclick="snnEduTestFireEvent(<?php echo $post_id; ?>, 'started')" class="snn-debug-btn">
                    🟢 Fire "started" Event
                </button>
                <button onclick="snnEduTestFireEvent(<?php echo $post_id; ?>, 'completed')" class="snn-debug-btn">
                    🔵 Fire "completed" Event
                </button>
            </div>

            <div class="snn-edu-debug-log">
                <div class="snn-edu-debug-log-header">
                    <strong>Event Log:</strong>
                    <button onclick="document.getElementById('snn-debug-log-<?php echo $post_id; ?>').innerHTML = '';" class="snn-debug-clear-btn">Clear</button>
                </div>
                <div id="snn-debug-log-<?php echo $post_id; ?>" class="snn-edu-debug-log-content">
                    <div class="snn-debug-log-item snn-debug-info">Waiting for events...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <style>
        .snn-edu-login-required {
            padding: 10px 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            margin: 15px 0;
            border-radius: 4px;
            color: #92400e;
        }

        /* Debug Panel Styles */
        .snn-edu-debug-panel {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .snn-edu-debug-header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #fbbf24;
            border-bottom: 2px solid #374151;
            padding-bottom: 5px;
        }
        .snn-edu-debug-info ul {
            margin: 10px 0;
            padding-left: 20px;
            list-style: none;
        }
        .snn-edu-debug-info li {
            margin: 5px 0;
            color: #cbd5e1;
        }
        .snn-edu-debug-info code {
            background: #374151;
            padding: 2px 6px;
            border-radius: 3px;
            color: #fbbf24;
        }
        .snn-edu-debug-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        .snn-debug-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }
        .snn-debug-btn:hover {
            background: #2563eb;
        }
        .snn-edu-debug-log {
            margin-top: 15px;
        }
        .snn-edu-debug-log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 2px solid #374151;
        }
        .snn-debug-clear-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }
        .snn-debug-clear-btn:hover {
            background: #dc2626;
        }
        .snn-edu-debug-log-content {
            background: #0f172a;
            padding: 10px;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
        }
        .snn-debug-log-item {
            margin: 5px 0;
            padding: 5px 8px;
            border-left: 3px solid #64748b;
            background: #1e293b;
        }
        .snn-debug-success {
            border-left-color: #10b981;
            background: #064e3b;
        }
        .snn-debug-error {
            border-left-color: #ef4444;
            background: #7f1d1d;
        }
        .snn-debug-info {
            border-left-color: #3b82f6;
            background: #1e3a8a;
        }
        .snn-debug-warning {
            border-left-color: #f59e0b;
            background: #78350f;
        }
        .snn-debug-timestamp {
            color: #64748b;
            font-size: 11px;
            margin-right: 8px;
        }
    </style>

    <script>
        // Initialize tracker for this shortcode instance
        (function() {
            const tracker = document.querySelector('.snn-edu-tracker[data-post-id="<?php echo esc_js($post_id); ?>"]');
            if (!tracker) {
                return;
            }

            const eventsMode = tracker.dataset.events || 'both';
            const auto = tracker.dataset.auto === 'true';
            const postId = parseInt(tracker.dataset.postId);
            const isDebug = tracker.dataset.debug === 'true';

            // Debug logging function
            function debugLog(message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                const logContainer = document.getElementById('snn-debug-log-<?php echo $post_id; ?>');

                if (logContainer) {
                    const logItem = document.createElement('div');
                    logItem.className = 'snn-debug-log-item snn-debug-' + type;
                    logItem.innerHTML = '<span class="snn-debug-timestamp">[' + timestamp + ']</span>' + message;
                    logContainer.appendChild(logItem);
                    logContainer.scrollTop = logContainer.scrollHeight;
                }

                // Also log to console
                const emoji = {
                    'success': '✅',
                    'error': '❌',
                    'warning': '⚠️',
                    'info': 'ℹ️'
                };
                console.log((emoji[type] || '') + ' SNN Edu Debug:', message);
            }

            if (isDebug) {
                debugLog('Tracker initialized for post ID: <?php echo $post_id; ?>', 'success');
                debugLog('Events mode: ' + eventsMode + ', Auto-enroll: ' + auto, 'info');

                let listeningEvents = [];
                if (eventsMode === 'both' || eventsMode === 'started') listeningEvents.push('snn_video_started');
                if (eventsMode === 'both' || eventsMode === 'completed') listeningEvents.push('snn_video_completed');

                debugLog('Listening for events: ' + listeningEvents.join(', '), 'info');
            }

            // Test functions
            window.snnEduTestEnroll = function(testPostId) {
                debugLog('Manual test: Enrolling in post ' + testPostId, 'info');
                snnEduEnrollUser(testPostId, true).then(response => {
                    if (response.enrolled_posts && response.enrolled_posts.length > 0) {
                        debugLog('✅ Enrolled in posts: ' + JSON.stringify(response.enrolled_posts), 'success');
                        debugLog('Total enrolled count: ' + response.enrolled_count, 'info');
                    }
                    debugLog('Full response: ' + JSON.stringify(response), response.success ? 'success' : 'warning');
                });
            };

            window.snnEduTestUnenroll = function(testPostId) {
                debugLog('Manual test: Unenrolling from post ' + testPostId, 'info');
                snnEduUnenrollUser(testPostId, true).then(response => {
                    debugLog('Unenroll response: ' + JSON.stringify(response), response.success ? 'success' : 'error');
                });
            };

            window.snnEduTestGetEnrollments = function() {
                debugLog('Manual test: Getting all enrollments', 'info');
                snnEduGetEnrollments(true).then(response => {
                    debugLog('Enrollments: ' + JSON.stringify(response.enrollments), 'success');
                });
            };

            window.snnEduTestFireEvent = function(testPostId, eventType) {
                const eventName = eventType === 'started' ? 'snn_video_started' : 'snn_video_completed';
                debugLog('Manual test: Firing custom event "' + eventName + '" for post ' + testPostId, 'warning');

                const customEvent = new CustomEvent(eventName, {
                    detail: { post_id: testPostId }
                });
                document.dispatchEvent(customEvent);

                debugLog('Event dispatched successfully', 'success');
            };

            if (auto) {
                // Function to handle enrollment for an event
                function handleVideoEvent(eventName) {
                    document.addEventListener(eventName, function(e) {
                        if (isDebug) {
                            debugLog('Event "' + eventName + '" received! Event detail: ' + JSON.stringify(e.detail), 'warning');
                        }

                        // Get post ID from event detail (this is the correct child page ID)
                        const eventPostId = e.detail && e.detail.post_id ? parseInt(e.detail.post_id) : null;

                        if (!eventPostId) {
                            if (isDebug) {
                                debugLog('No post_id in event detail - ignoring', 'error');
                            }
                            return;
                        }

                        if (isDebug) {
                            debugLog('Event post_id: ' + eventPostId, 'info');
                        }

                        // Enroll using the post ID from the event (not the shortcode's post ID)
                        if (typeof snnEduEnrollUser !== 'undefined') {
                            if (isDebug) {
                                debugLog('Attempting to enroll in post ' + eventPostId + '...', 'success');
                            }

                            snnEduEnrollUser(eventPostId, isDebug).then(response => {
                                if (isDebug) {
                                    debugLog('Enrollment attempt completed: ' + JSON.stringify(response), response.success ? 'success' : 'warning');
                                }
                            }).catch(error => {
                                if (isDebug) {
                                    debugLog('Enrollment failed: ' + error, 'error');
                                }
                            });
                        } else {
                            if (isDebug) {
                                debugLog('ERROR: snnEduEnrollUser function not found!', 'error');
                            }
                        }
                    });
                }

                // Listen to both events based on eventsMode setting
                if (eventsMode === 'both' || eventsMode === 'started') {
                    handleVideoEvent('snn_video_started');
                }

                if (eventsMode === 'both' || eventsMode === 'completed') {
                    handleVideoEvent('snn_video_completed');
                }

                if (isDebug) {
                    debugLog('Event listeners registered successfully', 'success');
                }
            }
        })();
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('snn_video_tracker', 'snn_edu_user_meta_tracker_shortcode');













/**
 * Shortcode for manual enrollment button
 * Usage: [snn_mark_complete] or [snn_mark_complete text="Complete Course"]
 */
function snn_edu_mark_complete_shortcode($atts) {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return '';
    }

    if (!is_user_logged_in()) {
        return '<p class="snn-edu-login-required">Please log in to mark as complete.</p>';
    }

    $atts = shortcode_atts(array(
        'text' => 'Mark Completed',
        'completed_text' => 'Completed ✓',
    ), $atts);

    $post_id = get_the_ID();
    $user_id = get_current_user_id();
    // Use safe retrieval method
    $enrollments = snn_edu_get_enrollments_safe($user_id);
    $is_enrolled = in_array(intval($post_id), $enrollments, true);

    // Get REST API data (same as inline script)
    $rest_url = rest_url('snn-edu/v1/');
    $nonce = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <div class="snn-edu-mark-complete-wrapper">
        <button class="snn-edu-mark-complete-btn" 
                data-post-id="<?php echo esc_attr($post_id); ?>" 
                data-rest-url="<?php echo esc_attr($rest_url); ?>"
                data-nonce="<?php echo esc_attr($nonce); ?>"
                data-text="<?php echo esc_attr($atts['text']); ?>"
                data-completed-text="<?php echo esc_attr($atts['completed_text']); ?>"
                <?php echo $is_enrolled ? 'disabled' : ''; ?>>
            <?php echo $is_enrolled ? esc_html($atts['completed_text']) : esc_html($atts['text']); ?>
        </button>
        <span class="snn-edu-mark-complete-message"></span>
    </div>

    <style>
        .snn-edu-mark-complete-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .snn-edu-mark-complete-btn {
            background: var(--c2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .snn-edu-mark-complete-btn:hover:not(:disabled) {
            background: var(--c2-d-4);
            transform: translateY(-1px);
        }
        .snn-edu-mark-complete-btn:active:not(:disabled) {
            transform: translateY(0);
        }
        .snn-edu-mark-complete-btn:disabled {
            background: #10b981;
            cursor: not-allowed;
            opacity: 0.9;
        }
        .snn-edu-mark-complete-message {
            font-size: 14px;
            font-weight: 500;
        }
        .snn-edu-mark-complete-message.success {
            color: #10b981;
        }
        .snn-edu-mark-complete-message.error {
            color: #ef4444;
        }
    </style>

    <script>
    (function() {
        const btn = document.querySelector('.snn-edu-mark-complete-btn[data-post-id="<?php echo esc_js($post_id); ?>"]');
        if (!btn || btn.disabled) return;

        const message = btn.parentElement.querySelector('.snn-edu-mark-complete-message');
        const postId = parseInt(btn.dataset.postId);
        const restUrl = btn.dataset.restUrl;
        const nonce = btn.dataset.nonce;
        const originalText = btn.dataset.text;
        const completedText = btn.dataset.completedText;

        btn.addEventListener('click', function() {
            btn.disabled = true;
            btn.textContent = 'Saving...';
            message.textContent = '';
            message.className = 'snn-edu-mark-complete-message';

            fetch(restUrl + 'enroll', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ post_id: postId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    btn.textContent = completedText;
                    message.textContent = 'Progress saved!';
                    message.className = 'snn-edu-mark-complete-message success';
                    
                    // Dispatch custom event for other scripts
                    document.dispatchEvent(new CustomEvent('snn_edu_enrolled', {
                        detail: {
                            post_id: postId,
                            enrolled_count: data.enrolled_count,
                            enrolled_posts: data.enrolled_posts
                        }
                    }));

                    // Hide message after 3 seconds
                    setTimeout(() => {
                        message.textContent = '';
                    }, 3000);
                } else {
                    throw new Error(data.message || 'Enrollment failed');
                }
            })
            .catch(error => {
                console.error('SNN Edu: Enrollment failed', error);
                btn.disabled = false;
                btn.textContent = originalText;
                message.textContent = 'Failed to save. Please try again.';
                message.className = 'snn-edu-mark-complete-message error';
                
                // Hide error message after 5 seconds
                setTimeout(() => {
                    message.textContent = '';
                }, 5000);
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('snn_mark_complete', 'snn_edu_mark_complete_shortcode');
