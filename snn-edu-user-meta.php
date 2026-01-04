<?php
/**
 * SNN Edu User Meta - Video Enrollment Tracking
 *
 * Tracks user video completion and enrollment via REST API
 * Stores enrolled post IDs in user meta
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==========================================
 * REST API ENDPOINTS
 * ==========================================
 */

/**
 * Register REST API routes for user enrollment tracking
 */
function snn_edu_user_meta_register_routes() {
    // Only register if feature is enabled
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return;
    }

    register_rest_route('snn-edu/v1', '/enroll', array(
        'methods' => 'POST',
        'callback' => 'snn_edu_user_meta_enroll_user',
        'permission_callback' => 'snn_edu_user_meta_check_logged_in',
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
        'permission_callback' => 'snn_edu_user_meta_check_logged_in',
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
        'permission_callback' => 'snn_edu_user_meta_check_logged_in',
    ));
}
add_action('rest_api_init', 'snn_edu_user_meta_register_routes');

/**
 * Permission callback - check if user is logged in
 */
function snn_edu_user_meta_check_logged_in() {
    return is_user_logged_in();
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
 * ==========================================
 * ADMIN: USER META BOX
 * ==========================================
 */

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
 * ==========================================
 * FRONTEND: JAVASCRIPT ENQUEUE
 * ==========================================
 */

/**
 * Enqueue tracking JavaScript on frontend
 */
function snn_edu_user_meta_enqueue_scripts() {
    if (!snn_edu_get_option('enable_user_meta_tracking', false)) {
        return;
    }

    // Only enqueue for logged-in users
    if (!is_user_logged_in()) {
        return;
    }

    wp_enqueue_script(
        'snn-edu-user-meta-tracker',
        SNN_EDU_PLUGIN_URL . 'js/snn-edu-user-meta-tracker.js',
        array(),
        SNN_EDU_VERSION,
        true
    );

    // Pass REST API data to JavaScript
    wp_localize_script('snn-edu-user-meta-tracker', 'snnEduUserMeta', array(
        'restUrl' => rest_url('snn-edu/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'userId' => get_current_user_id(),
    ));
}
add_action('wp_enqueue_scripts', 'snn_edu_user_meta_enqueue_scripts');

/**
 * ==========================================
 * SHORTCODE: VIDEO ENROLLMENT TRACKER
 * ==========================================
 */

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
            const event = tracker.dataset.event;
            const auto = tracker.dataset.auto === 'true';
            const postId = tracker.dataset.postId;

            if (auto) {
                // Listen for the appropriate video event
                const eventName = event === 'started' ? 'snn_video_started' : 'snn_video_completed';

                document.addEventListener(eventName, function(e) {
                    // Only track if the event is for this post
                    if (e.detail.post_id == postId) {
                        snnEduEnrollUser(postId);
                    }
                });
            }
        })();
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('snn_video_tracker', 'snn_edu_user_meta_tracker_shortcode');
