<?php

/**
 * Custom Dynamic Data Tag: Current Course Enrollment Percentage
 * Returns the percentage of completed/enrolled posts for the current course
 *
 * Usage:
 * {get_current_course_enrollment_percentage} - Returns percentage (e.g., "45%")
 * {get_current_course_enrollment_percentage:bool} - Returns "true" if enrolled (>0%), "false" if not enrolled (0%)
 *
 */

// Step 1: Register the tag in the builder
add_filter( 'bricks/dynamic_tags_list', 'snn_add_course_enrollment_percentage_tag' );
function snn_add_course_enrollment_percentage_tag( $tags ) {
    // Default tag (returns percentage)
    $tags[] = [
        'name'  => '{get_current_course_enrollment_percentage}',
        'label' => 'Current Course Enrollment Percentage',
        'group' => 'SNN Edu',
    ];

    // Bool variant
    $tags[] = [
        'name'  => '{get_current_course_enrollment_percentage:bool}',
        'label' => 'Current Course Enrollment Percentage - Boolean',
        'group' => 'SNN Edu',
    ];

    return $tags;
}

// Step 2: Render the tag value (for individual tag parsing)
add_filter( 'bricks/dynamic_data/render_tag', 'snn_get_course_enrollment_percentage_value', 20, 3 );
function snn_get_course_enrollment_percentage_value( $tag, $post, $context = 'text' ) {
    // Ensure $tag is a string
    if ( ! is_string( $tag ) ) {
        return $tag;
    }

    // Clean the tag (remove curly braces)
    $clean_tag = str_replace( [ '{', '}' ], '', $tag );

    // Parse option if present
    $option = '';
    if ( strpos( $clean_tag, ':' ) !== false ) {
        list( $tag_name, $option ) = explode( ':', $clean_tag, 2 );
    } else {
        $tag_name = $clean_tag;
    }

    // Only process our specific tag
    if ( $tag_name !== 'get_current_course_enrollment_percentage' ) {
        return $tag;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) ) {
        $post_id = $post;
    } else {
        $post_id = get_the_ID();
    }

    // Get the enrollment percentage with option
    $value = snn_calculate_course_enrollment_percentage( $post_id, $option );

    // Return based on context
    // For image context, you would return an array of image IDs
    // For text/other contexts, return the string value
    return $value;
}

// Step 3: Render in content (for content with multiple tags)
add_filter( 'bricks/dynamic_data/render_content', 'snn_render_course_enrollment_percentage_tag', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'snn_render_course_enrollment_percentage_tag', 20, 3 );
function snn_render_course_enrollment_percentage_tag( $content, $post, $context = 'text' ) {

    // Only process if any variant of our tag exists in content
    if ( strpos( $content, '{get_current_course_enrollment_percentage' ) === false ) {
        return $content;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) ) {
        $post_id = $post;
    } else {
        $post_id = get_the_ID();
    }

    // Match both variants using regex
    preg_match_all('/{get_current_course_enrollment_percentage(?::([^}]+))?}/', $content, $matches);

    if ( ! empty( $matches[0] ) ) {
        foreach ( $matches[0] as $index => $full_match ) {
            $option = isset( $matches[1][$index] ) && $matches[1][$index] ? $matches[1][$index] : '';
            $value = snn_calculate_course_enrollment_percentage( $post_id, $option );
            $content = str_replace( $full_match, $value, $content );
        }
    }

    return $content;
}

// Helper function to calculate enrollment percentage
function snn_calculate_course_enrollment_percentage( $post_id = null, $option = '' ) {
    // Get current post ID
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    if ( ! $post_id ) {
        return $option === 'bool' ? 'false' : '0%';
    }

    // Get current user
    $current_user_id = get_current_user_id();

    if ( ! $current_user_id ) {
        return $option === 'bool' ? 'false' : '0%';
    }

    // Get user's enrolled posts
    $enrolled_posts_raw = get_user_meta( $current_user_id, 'snn_edu_enrolled_posts', true );
    
    // Handle if it's stored as JSON string
    if ( is_string( $enrolled_posts_raw ) ) {
        $enrolled_posts = json_decode( $enrolled_posts_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $enrolled_posts = maybe_unserialize( $enrolled_posts_raw );
        }
    } else {
        $enrolled_posts = $enrolled_posts_raw;
    }

    // Ensure it's an array and convert all values to integers
    if ( ! is_array( $enrolled_posts ) || empty( $enrolled_posts ) ) {
        return $option === 'bool' ? 'false' : '0%';
    }
    
    // Convert all enrolled post IDs to integers
    $enrolled_posts = array_map( 'intval', $enrolled_posts );
    $enrolled_posts = array_filter( $enrolled_posts ); // Remove any 0 values

    // Get the top-level parent (level_0)
    $top_parent_id = snn_get_top_level_parent( $post_id );

    // Get all child posts including the parent itself
    $all_course_posts = snn_get_all_course_posts( $top_parent_id );

    if ( empty( $all_course_posts ) ) {
        return $option === 'bool' ? 'false' : '0%';
    }

    // Calculate how many posts user has enrolled in
    $matched_posts = array_intersect( $enrolled_posts, $all_course_posts );
    $enrolled_count = count( $matched_posts );
    $total_count = count( $all_course_posts );

    // Calculate percentage
    if ( $total_count > 0 ) {
        $percentage = round( ( $enrolled_count / $total_count ) * 100 );
    } else {
        $percentage = 0;
    }

    // Return based on option
    if ( $option === 'bool' ) {
        return $percentage > 0 ? 'true' : 'false';
    }

    return $percentage . '%';
}

// Helper function to get the top-level parent (level_0)
function snn_get_top_level_parent( $post_id ) {
    $current_post = get_post( $post_id );

    if ( ! $current_post ) {
        return $post_id;
    }

    $parent_id = $post_id;
    $max_iterations = 20; // Prevent infinite loops
    $iteration = 0;

    // Keep traversing up until we reach the top parent
    while ( $iteration < $max_iterations ) {
        $parent_post = get_post( $parent_id );
        
        if ( ! $parent_post ) {
            break;
        }
        
        if ( $parent_post->post_parent && $parent_post->post_parent != $parent_id ) {
            $parent_id = $parent_post->post_parent;
        } else {
            break;
        }
        
        $iteration++;
    }

    return (int) $parent_id;
}

// Helper function to get all course posts (parent + all descendants)
function snn_get_all_course_posts( $parent_id ) {
    $all_posts = [ (int) $parent_id ]; // Include the parent itself and ensure it's an integer

    // Get all children recursively
    $children = snn_get_all_children_recursive( $parent_id );

    if ( ! empty( $children ) ) {
        $all_posts = array_merge( $all_posts, $children );
    }

    // Remove duplicates and ensure all are integers
    $all_posts = array_unique( array_map( 'intval', $all_posts ) );
    
    // Remove any 0 values
    $all_posts = array_filter( $all_posts );
    
    // Re-index array
    $all_posts = array_values( $all_posts );

    return $all_posts;
}

// Helper function to recursively get all child posts
function snn_get_all_children_recursive( $parent_id ) {
    $children = [];
    
    // Get the post type of the parent
    $post_type = get_post_type( $parent_id );
    
    if ( ! $post_type ) {
        return $children;
    }

    // Get direct children
    $args = [
        'post_type'      => $post_type,
        'post_parent'    => (int) $parent_id,
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ];

    $child_posts = get_posts( $args );

    if ( ! empty( $child_posts ) ) {
        foreach ( $child_posts as $child_id ) {
            $children[] = (int) $child_id;

            // Recursively get children of this child
            $grandchildren = snn_get_all_children_recursive( $child_id );

            if ( ! empty( $grandchildren ) ) {
                $children = array_merge( $children, $grandchildren );
            }
        }
    }

    return $children;
}































/**
 * Custom Dynamic Data Tag: Current Course Enrollment User Count
 * Returns the number of users enrolled in the current course
 *
 * Usage:
 * {get_current_course_enrollment_user_count} - Returns the count of enrolled users (e.g., "25")
 *
 */

// Step 1: Register the tag in the builder
add_filter( 'bricks/dynamic_tags_list', 'snn_add_course_enrollment_user_count_tag' );
function snn_add_course_enrollment_user_count_tag( $tags ) {
    $tags[] = [
        'name'  => '{get_current_course_enrollment_user_count}',
        'label' => 'Current Course Enrollment User Count',
        'group' => 'SNN Edu',
    ];

    return $tags;
}

// Step 2: Render the tag value (for individual tag parsing)
add_filter( 'bricks/dynamic_data/render_tag', 'snn_get_course_enrollment_user_count_value', 20, 3 );
function snn_get_course_enrollment_user_count_value( $tag, $post, $context = 'text' ) {
    // Ensure $tag is a string
    if ( ! is_string( $tag ) ) {
        return $tag;
    }

    // Clean the tag (remove curly braces)
    $clean_tag = str_replace( [ '{', '}' ], '', $tag );

    // Only process our specific tag
    if ( $clean_tag !== 'get_current_course_enrollment_user_count' ) {
        return $tag;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) ) {
        $post_id = $post;
    } else {
        $post_id = get_the_ID();
    }

    // Get the enrollment user count
    $value = snn_calculate_course_enrollment_user_count( $post_id );

    return $value;
}

// Step 3: Render in content (for content with multiple tags)
add_filter( 'bricks/dynamic_data/render_content', 'snn_render_course_enrollment_user_count_tag', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'snn_render_course_enrollment_user_count_tag', 20, 3 );
function snn_render_course_enrollment_user_count_tag( $content, $post, $context = 'text' ) {

    // Only process if our tag exists in content
    if ( strpos( $content, '{get_current_course_enrollment_user_count}' ) === false ) {
        return $content;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) ) {
        $post_id = $post;
    } else {
        $post_id = get_the_ID();
    }

    // Calculate the user count
    $value = snn_calculate_course_enrollment_user_count( $post_id );

    // Replace the tag with the value
    $content = str_replace( '{get_current_course_enrollment_user_count}', $value, $content );

    return $content;
}

// Helper function to calculate enrollment user count
function snn_calculate_course_enrollment_user_count( $post_id = null ) {
    // Get current post ID
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    if ( ! $post_id ) {
        return '0';
    }

    // Get all users
    $users = get_users( [
        'fields' => 'ID',
    ] );

    if ( empty( $users ) ) {
        return '0';
    }

    $enrolled_user_count = 0;

    // Loop through each user and check if they have this post ID in their enrolled posts
    foreach ( $users as $user_id ) {
        $enrolled_posts_raw = get_user_meta( $user_id, 'snn_edu_enrolled_posts', true );

        // Handle if it's stored as JSON string
        if ( is_string( $enrolled_posts_raw ) ) {
            $enrolled_posts = json_decode( $enrolled_posts_raw, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $enrolled_posts = maybe_unserialize( $enrolled_posts_raw );
            }
        } else {
            $enrolled_posts = $enrolled_posts_raw;
        }

        // Ensure it's an array
        if ( ! is_array( $enrolled_posts ) || empty( $enrolled_posts ) ) {
            continue;
        }

        // Convert all enrolled post IDs to integers
        $enrolled_posts = array_map( 'intval', $enrolled_posts );
        $enrolled_posts = array_filter( $enrolled_posts ); // Remove any 0 values

        // Check if the current post ID is in the user's enrolled posts
        if ( in_array( (int) $post_id, $enrolled_posts, true ) ) {
            $enrolled_user_count++;
        }
    }

    return (string) $enrolled_user_count;
}














/**
 * Custom Dynamic Data Tag: Check if Current Post ID Exists in User Enrollment List
 * Returns "true" if the specific post ID is in the user's enrollment list, "false" otherwise
 *
 * Usage:
 * {get_current_course_id_if_exist_in_user_enrollment_list} - Returns "true" or "false"
 *
 */

// Step 1: Register the tag in the builder
add_filter( 'bricks/dynamic_tags_list', 'snn_add_course_id_in_enrollment_list_tag' );
function snn_add_course_id_in_enrollment_list_tag( $tags ) {
    $tags[] = [
        'name'  => '{get_current_course_id_if_exist_in_user_enrollment_list}',
        'label' => 'Current Course ID Exists in User Enrollment List',
        'group' => 'SNN Edu',
    ];

    return $tags;
}

// Step 2: Render the tag value (for individual tag parsing)
add_filter( 'bricks/dynamic_data/render_tag', 'snn_get_course_id_in_enrollment_list_value', 20, 3 );
function snn_get_course_id_in_enrollment_list_value( $tag, $post, $context = 'text' ) {
    // Ensure $tag is a string
    if ( ! is_string( $tag ) ) {
        return $tag;
    }

    // Clean the tag (remove curly braces)
    $clean_tag = str_replace( [ '{', '}' ], '', $tag );

    // Only process our specific tag
    if ( $clean_tag !== 'get_current_course_id_if_exist_in_user_enrollment_list' ) {
        return $tag;
    }

    // Get the correct post ID from context - prioritize the $post parameter
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) && $post > 0 ) {
        $post_id = (int) $post;
    }

    // Fallback: try get_the_ID() first, then queried object
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }

    // Check if post ID exists in user enrollment list
    $value = snn_check_post_id_in_user_enrollment( $post_id );

    return $value;
}

// Step 3: Render in content (for content with multiple tags)
add_filter( 'bricks/dynamic_data/render_content', 'snn_render_course_id_in_enrollment_list_tag', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'snn_render_course_id_in_enrollment_list_tag', 20, 3 );
function snn_render_course_id_in_enrollment_list_tag( $content, $post, $context = 'text' ) {

    // Only process if our tag exists in content
    if ( strpos( $content, '{get_current_course_id_if_exist_in_user_enrollment_list}' ) === false ) {
        return $content;
    }

    // Get the correct post ID from context - prioritize the $post parameter
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) && $post > 0 ) {
        $post_id = (int) $post;
    }

    // Fallback: try get_the_ID() first, then queried object
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }

    // Check if post ID exists in user enrollment list
    $value = snn_check_post_id_in_user_enrollment( $post_id );

    // Replace the tag with the value
    $content = str_replace( '{get_current_course_id_if_exist_in_user_enrollment_list}', $value, $content );

    return $content;
}

// Helper function to check if post ID exists in user enrollment list
function snn_check_post_id_in_user_enrollment( $post_id = null ) {
    // Get current post ID with multiple fallbacks
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }

    if ( ! $post_id ) {
        return 'false';
    }

    // Ensure post_id is an integer
    $post_id = (int) $post_id;

    // Get current user
    $current_user_id = get_current_user_id();

    if ( ! $current_user_id ) {
        return 'false';
    }

    // Get user's enrolled posts
    $enrolled_posts_raw = get_user_meta( $current_user_id, 'snn_edu_enrolled_posts', true );

    // Handle if it's stored as JSON string
    if ( is_string( $enrolled_posts_raw ) ) {
        $enrolled_posts = json_decode( $enrolled_posts_raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $enrolled_posts = maybe_unserialize( $enrolled_posts_raw );
        }
    } else {
        $enrolled_posts = $enrolled_posts_raw;
    }

    // Ensure it's an array
    if ( ! is_array( $enrolled_posts ) || empty( $enrolled_posts ) ) {
        return 'false';
    }

    // Convert all enrolled post IDs to integers
    $enrolled_posts = array_map( 'intval', $enrolled_posts );
    $enrolled_posts = array_filter( $enrolled_posts ); // Remove any 0 values

    // Check if the current post ID is in the user's enrolled posts
    if ( in_array( (int) $post_id, $enrolled_posts, true ) ) {
        return 'true';
    }

    return 'false';
}























/**
 * ----------------------------------------
 * Parent and Child Posts List Dynamic Tag Module
 * ----------------------------------------
 * Usage: {snn_edu_get_current_parent_and_child_list} or {snn_edu_get_current_parent_and_child_list:property}
 *
 * Supported Properties and Outputs:
 * - (default): Returns list with names and links (HTML list)
 * - name: Returns only names list
 * - id: Returns only IDs list
 *
 * Logic:
 * - Works on any post (parent or child)
 * - Always returns the top-level parent + all children
 * - Uses get_post_parent() to find the root parent
 * - Uses get_children() to get all child posts
 * ----------------------------------------
 */

// Step 1: Register the dynamic tags with Bricks Builder.
add_filter('bricks/dynamic_tags_list', 'snn_edu_add_parent_and_child_list_tags_to_builder');
function snn_edu_add_parent_and_child_list_tags_to_builder($tags) {
    $properties = [
        ''     => 'Parent & Child List (Name + Link)',
        'name' => 'Parent & Child List (Name Only)',
        'id'   => 'Parent & Child List (ID Only)',
    ];

    foreach ($properties as $property => $label) {
        $tag_name = $property ? "{snn_edu_get_current_parent_and_child_list:$property}" : '{snn_edu_get_current_parent_and_child_list}';
        $tags[] = [
            'name'  => $tag_name,
            'label' => $label,
            'group' => 'SNN',
        ];
    }

    return $tags;
}

// Step 2: Get the top-level parent post ID (root ancestor)
function get_top_level_parent_id($post_id) {
    $post = get_post($post_id);

    if (!$post) {
        return $post_id;
    }

    // If the post has no parent, it's already the top-level
    if ($post->post_parent == 0) {
        return $post_id;
    }

    // Traverse up to find the root parent
    $parent_id = $post->post_parent;
    while ($parent_id) {
        $parent_post = get_post($parent_id);
        if (!$parent_post || $parent_post->post_parent == 0) {
            break;
        }
        $parent_id = $parent_post->post_parent;
    }

    return $parent_id;
}

// Step 3: Get all descendants recursively
function get_all_descendants($parent_id, $post_type) {
    $all_children = [];

    $children = get_children([
        'post_parent' => $parent_id,
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'orderby'     => 'menu_order title',
        'order'       => 'ASC',
    ]);

    foreach ($children as $child) {
        $all_children[] = $child;
        // Recursively get children of this child
        $grandchildren = get_all_descendants($child->ID, $post_type);
        $all_children = array_merge($all_children, $grandchildren);
    }

    return $all_children;
}

// Step 4: Main function to get parent and child list
function get_parent_and_child_list($property = '') {
    // Get the current post ID using get_queried_object_id for reliability
    $current_post_id = get_queried_object_id();

    // Fallback to get_the_ID if queried object is not available
    if (!$current_post_id) {
        $current_post_id = get_the_ID();
    }

    if (!$current_post_id) {
        return '';
    }

    $current_post = get_post($current_post_id);

    if (!$current_post) {
        return '';
    }

    $post_type = $current_post->post_type;

    // Get the top-level parent
    $top_parent_id = get_top_level_parent_id($current_post_id);
    $top_parent = get_post($top_parent_id);

    if (!$top_parent) {
        return '';
    }

    // Build the list: parent first, then all descendants
    $posts_list = [$top_parent];
    $descendants = get_all_descendants($top_parent_id, $post_type);
    $posts_list = array_merge($posts_list, $descendants);

    // Format output based on property
    $output = [];

    // Get enrolled posts for current user (for default output with enrollment class)
    $enrolled_posts = [];
    if ($property === '' && is_user_logged_in()) {
        $user_id = get_current_user_id();
        $enrolled_posts = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);
        if (!is_array($enrolled_posts)) {
            $enrolled_posts = [];
        }
    }

    foreach ($posts_list as $post_item) {
        switch ($property) {
            case 'id':
                $output[] = $post_item->ID;
                break;
            case 'name':
                $output[] = $post_item->post_title;
                break;
            default:
                // Default: name with link, with enrolled/notenrolled class
                $enrollment_class = in_array($post_item->ID, $enrolled_posts) ? 'enrolled' : 'notenrolled';
                $output[] = '<a href="' . esc_url(get_permalink($post_item->ID)) . '" class="' . $enrollment_class . '">' . esc_html($post_item->post_title) . '</a>';
                break;
        }
    }

    // Return as comma-separated list for id/name, or HTML list for default
    if ($property === 'id' || $property === 'name') {
        return implode(', ', $output);
    }

    // Default: return as unordered list
    return '<ul class="parent-child-list"><li>' . implode('</li><li>', $output) . '</li></ul>';
}

// Step 5: Render the dynamic tag in Bricks Builder.
add_filter('bricks/dynamic_data/render_tag', 'render_parent_and_child_list_tag', 20, 3);
function render_parent_and_child_list_tag($tag, $post, $context = 'text') {
    // Ensure that $tag is a string before processing.
    if (is_string($tag)) {
        // Match {snn_edu_get_current_parent_and_child_list} or {snn_edu_get_current_parent_and_child_list:property}
        if (strpos($tag, '{snn_edu_get_current_parent_and_child_list') === 0) {
            // Extract the property from the tag
            if (preg_match('/{snn_edu_get_current_parent_and_child_list:([^}]+)}/', $tag, $matches)) {
                $property = trim($matches[1]);
                return get_parent_and_child_list($property);
            } elseif ($tag === '{snn_edu_get_current_parent_and_child_list}') {
                return get_parent_and_child_list();
            }
        }
    }

    // If $tag is an array, iterate through and process each element.
    if (is_array($tag)) {
        foreach ($tag as $key => $value) {
            if (is_string($value) && strpos($value, '{snn_edu_get_current_parent_and_child_list') === 0) {
                if (preg_match('/{snn_edu_get_current_parent_and_child_list:([^}]+)}/', $value, $matches)) {
                    $property = trim($matches[1]);
                    $tag[$key] = get_parent_and_child_list($property);
                } elseif ($value === '{snn_edu_get_current_parent_and_child_list}') {
                    $tag[$key] = get_parent_and_child_list();
                }
            }
        }
        return $tag;
    }

    // Return the original tag if it doesn't match the expected pattern.
    return $tag;
}

// Step 6: Replace placeholders in dynamic content dynamically.
add_filter('bricks/dynamic_data/render_content', 'replace_parent_and_child_list_in_content', 20, 3);
add_filter('bricks/frontend/render_data', 'replace_parent_and_child_list_in_content', 20, 2);
function replace_parent_and_child_list_in_content($content, $post, $context = 'text') {
    if (!is_string($content)) {
        return $content;
    }

    // Match all {snn_edu_get_current_parent_and_child_list} and {snn_edu_get_current_parent_and_child_list:property} tags
    preg_match_all('/{snn_edu_get_current_parent_and_child_list(?::([^}]+))?}/', $content, $matches);

    if (!empty($matches[0])) {
        foreach ($matches[0] as $index => $full_match) {
            $property = isset($matches[1][$index]) && $matches[1][$index] ? $matches[1][$index] : '';
            $value = get_parent_and_child_list($property);
            $content = str_replace($full_match, $value, $content);
        }
    }

    return $content;
}
