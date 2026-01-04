<?php   
/**  
 * Plugin Name: SNN Edu Utilities
 * Plugin URI: https://github.com/sinanisler/snn-edu-utilities
 * Description: Educational utilities including admin restrictions, dashboard notepad, and custom author permalinks with role-based URLs.
 * Version: 1.3
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
    echo '<li><strong>JavaScript Events:</strong> Listens to <code>snn_video_started</code> and <code>snn_video_completed</code> custom events</li>';
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

// Sanitize settings
function snn_edu_sanitize_settings($input) {
    $sanitized = array();

    $sanitized['enable_admin_restriction'] = isset($input['enable_admin_restriction']) ? 1 : 0;
    $sanitized['enable_admin_bar_restriction'] = isset($input['enable_admin_bar_restriction']) ? 1 : 0;
    $sanitized['enable_custom_author_urls'] = isset($input['enable_custom_author_urls']) ? 1 : 0;
    $sanitized['enable_comment_ratings'] = isset($input['enable_comment_ratings']) ? 1 : 0;
    $sanitized['enable_user_meta_tracking'] = isset($input['enable_user_meta_tracking']) ? 1 : 0;

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
 */
function snn_edu_user_meta_enroll_user($request) {
    $post_id = $request->get_param('post_id');
    $user_id = get_current_user_id();

    // Verify post exists
    if (!get_post($post_id)) {
        return new WP_Error(
            'invalid_post',
            'Post does not exist',
            array('status' => 404)
        );
    }

    // Get current enrollments
    $enrollments = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);
    if (!is_array($enrollments)) {
        $enrollments = array();
    }

    // Add post_id if not already enrolled
    $post_id_int = intval($post_id);
    if (!in_array($post_id_int, $enrollments, true)) {
        $enrollments[] = $post_id_int;
        update_user_meta($user_id, 'snn_edu_enrolled_posts', $enrollments);

        return array(
            'success' => true,
            'message' => 'Successfully enrolled',
            'post_id' => $post_id_int,
            'enrolled_count' => count($enrollments),
        );
    }

    return array(
        'success' => false,
        'message' => 'Already enrolled',
        'post_id' => $post_id_int,
    );
}

/**
 * Unenroll user from a post (remove post_id from user meta)
 */
function snn_edu_user_meta_unenroll_user($request) {
    $post_id = $request->get_param('post_id');
    $user_id = get_current_user_id();

    // Get current enrollments
    $enrollments = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);
    if (!is_array($enrollments)) {
        $enrollments = array();
    }

    // Remove post_id if enrolled
    $post_id_int = intval($post_id);
    $key = array_search($post_id_int, $enrollments, true);

    if ($key !== false) {
        unset($enrollments[$key]);
        $enrollments = array_values($enrollments); // Re-index array
        update_user_meta($user_id, 'snn_edu_enrolled_posts', $enrollments);

        return array(
            'success' => true,
            'message' => 'Successfully unenrolled',
            'post_id' => $post_id_int,
            'enrolled_count' => count($enrollments),
        );
    }

    return array(
        'success' => false,
        'message' => 'Not enrolled',
        'post_id' => $post_id_int,
    );
}

/**
 * Get all enrollments for current user
 */
function snn_edu_user_meta_get_enrollments($request) {
    $user_id = get_current_user_id();

    $enrollments = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);
    if (!is_array($enrollments)) {
        $enrollments = array();
    }

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
    $enrollments = get_user_meta($user->ID, 'snn_edu_enrolled_posts', true);

    if (!is_array($enrollments) || empty($enrollments)) {
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
        window.snnEduEnrollUser = function(postId) {
            if (!postId || !Number.isInteger(parseInt(postId))) {
                console.error('SNN Edu: Invalid post ID', postId);
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
                    console.log('✅ SNN Edu: Successfully enrolled in post', postId);

                    // Dispatch custom event for other scripts to listen
                    document.dispatchEvent(new CustomEvent('snn_edu_enrolled', {
                        detail: {
                            post_id: postId,
                            enrolled_count: data.enrolled_count
                        }
                    }));
                } else {
                    console.log('ℹ️ SNN Edu:', data.message, postId);
                }
                return data;
            })
            .catch(error => {
                console.error('❌ SNN Edu: Enrollment failed', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Unenroll user from a post via REST API
         */
        window.snnEduUnenrollUser = function(postId) {
            if (!postId || !Number.isInteger(parseInt(postId))) {
                console.error('SNN Edu: Invalid post ID', postId);
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
                    console.log('✅ SNN Edu: Successfully unenrolled from post', postId);

                    // Dispatch custom event
                    document.dispatchEvent(new CustomEvent('snn_edu_unenrolled', {
                        detail: {
                            post_id: postId,
                            enrolled_count: data.enrolled_count
                        }
                    }));
                } else {
                    console.log('ℹ️ SNN Edu:', data.message, postId);
                }
                return data;
            })
            .catch(error => {
                console.error('❌ SNN Edu: Unenrollment failed', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Get all enrollments for current user
         */
        window.snnEduGetEnrollments = function() {
            return fetch(snnEduUserMeta.restUrl + 'enrollments', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': snnEduUserMeta.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('📚 SNN Edu: User enrollments', data);
                return data;
            })
            .catch(error => {
                console.error('❌ SNN Edu: Failed to get enrollments', error);
                return { success: false, error: error.message };
            });
        };

        /**
         * Check if user is enrolled in a specific post
         */
        window.snnEduIsEnrolled = function(postId) {
            return window.snnEduGetEnrollments()
                .then(data => {
                    if (data.success && data.enrollments) {
                        return data.enrollments.includes(parseInt(postId));
                    }
                    return false;
                });
        };

        // Log initialization
        console.log('🎓 SNN Edu User Meta Tracker initialized for user:', snnEduUserMeta.userId);

    })();
    </script>
    <?php
}
add_action('wp_footer', 'snn_edu_user_meta_inline_script');

/**
 * Shortcode to enable video enrollment tracking
 * Usage: [snn_video_tracker]
 */
function snn_edu_user_meta_tracker_shortcode($atts) {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return '';
    }

    if (!is_user_logged_in()) {
        return '<p class="snn-edu-login-required">Please log in to track your progress.</p>';
    }

    $atts = shortcode_atts(array(
        'event' => 'completed', // 'started' or 'completed'
        'auto' => 'true', // Auto-enroll on event
    ), $atts);

    $post_id = get_the_ID();

    ob_start();
    ?>
    <div class="snn-edu-tracker" data-post-id="<?php echo esc_attr($post_id); ?>" data-event="<?php echo esc_attr($atts['event']); ?>" data-auto="<?php echo esc_attr($atts['auto']); ?>">
        <div class="snn-edu-tracker-status">
            <span class="snn-edu-tracker-icon">📊</span>
            <span class="snn-edu-tracker-text">Video tracking active</span>
        </div>
    </div>

    <style>
        .snn-edu-tracker {
            padding: 10px 15px;
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            margin: 15px 0;
            border-radius: 4px;
        }
        .snn-edu-tracker-status {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #0c4a6e;
        }
        .snn-edu-tracker-icon {
            font-size: 18px;
        }
        .snn-edu-login-required {
            padding: 10px 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            margin: 15px 0;
            border-radius: 4px;
            color: #92400e;
        }
    </style>

    <script>
        // Initialize tracker for this shortcode instance
        (function() {
            const tracker = document.querySelector('.snn-edu-tracker[data-post-id="<?php echo esc_js($post_id); ?>"]');
            if (!tracker) return;
            
            const event = tracker.dataset.event;
            const auto = tracker.dataset.auto === 'true';
            const postId = tracker.dataset.postId;

            if (auto) {
                // Listen for the appropriate video event
                const eventName = event === 'started' ? 'snn_video_started' : 'snn_video_completed';

                document.addEventListener(eventName, function(e) {
                    // Only track if the event is for this post
                    if (e.detail.post_id == postId) {
                        if (typeof snnEduEnrollUser !== 'undefined') {
                            snnEduEnrollUser(postId);
                        }
                    }
                });
            }
        })();
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('snn_video_tracker', 'snn_edu_user_meta_tracker_shortcode');
