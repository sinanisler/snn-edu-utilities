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
 * - [custom_field_name]: Returns list with custom field value displayed after link (e.g., video_length)
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
function snn_edu_get_top_level_parent_id($post_id) {
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
function snn_edu_get_all_descendants($parent_id, $post_type) {
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
        $grandchildren = snn_edu_get_all_descendants($child->ID, $post_type);
        $all_children = array_merge($all_children, $grandchildren);
    }

    return $all_children;
}

// Step 4: Build nested hierarchical HTML list recursively
function snn_edu_build_nested_list($parent_id, $post_type, $depth, $current_post_id, $enrolled_posts, $custom_field = '') {
    $children = get_children([
        'post_parent' => $parent_id,
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'orderby'     => 'menu_order title',
        'order'       => 'ASC',
    ]);

    if (empty($children)) {
        return '';
    }

    $output = '<ul class="depth-' . $depth . ' children">';

    foreach ($children as $child) {
        // Build li and a classes
        $li_classes = ['depth-' . $depth];
        $a_classes = ['depth-' . $depth];

        // Check if this is the current post
        if ($child->ID === $current_post_id) {
            $li_classes[] = 'current';
            $a_classes[] = 'current';
        }

        // Check enrollment status
        if (in_array($child->ID, $enrolled_posts)) {
            $li_classes[] = 'enrolled';
            $a_classes[] = 'enrolled';
        } else {
            $li_classes[] = 'notenrolled';
            $a_classes[] = 'notenrolled';
        }

        // Check if has children
        $has_children = get_children([
            'post_parent' => $child->ID,
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);
        if (!empty($has_children)) {
            $li_classes[] = 'has-children';
            $a_classes[] = 'has-children';
        }

        // Check free_preview custom field
        $free_preview = get_post_meta($child->ID, 'free_preview', true);
        if ($free_preview === true || $free_preview === 'true' || $free_preview === '1') {
            $li_classes[] = 'free_preview';
        } else {
            $li_classes[] = 'membersonly';
        }

        $output .= '<li class="' . implode(' ', $li_classes) . '">';

        // depth-1 items render as div (no link), others as anchor
        if ($depth === 1) {
            $output .= '<div class="' . implode(' ', $a_classes) . '">' . esc_html($child->post_title) . '</div>';
        } else {
            $output .= '<a href="' . esc_url(get_permalink($child->ID)) . '" class="' . implode(' ', $a_classes) . '">' . esc_html($child->post_title) . '</a>';
        }

        // Add custom field if specified and exists
        if ($custom_field) {
            $field_value = get_post_meta($child->ID, $custom_field, true);
            if ($field_value) {
                $output .= '<span class="' . esc_attr($custom_field) . '">' . esc_html($field_value) . '</span>';
            }
        }

        // Recursively get children
        $output .= snn_edu_build_nested_list($child->ID, $post_type, $depth + 1, $current_post_id, $enrolled_posts, $custom_field);

        $output .= '</li>';
    }

    $output .= '</ul>';

    return $output;
}

// Step 5: Main function to get parent and child list
function snn_edu_get_parent_and_child_list($property = '') {
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
    $top_parent_id = snn_edu_get_top_level_parent_id($current_post_id);
    $top_parent = get_post($top_parent_id);

    if (!$top_parent) {
        return '';
    }

    // For id and name properties, use flat list
    if ($property === 'id' || $property === 'name') {
        $posts_list = [$top_parent];
        $descendants = snn_edu_get_all_descendants($top_parent_id, $post_type);
        $posts_list = array_merge($posts_list, $descendants);

        $output = [];
        foreach ($posts_list as $post_item) {
            if ($property === 'id') {
                $output[] = $post_item->ID;
            } else {
                $output[] = $post_item->post_title;
            }
        }
        return implode(', ', $output);
    }

    // Determine if property is a custom field name (not empty, not 'id', not 'name')
    $custom_field = '';
    if ($property !== '' && $property !== 'id' && $property !== 'name') {
        $custom_field = $property;
    }

    // Default: Build nested hierarchical list with semantic classes
    $enrolled_posts = [];
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $enrolled_posts = get_user_meta($user_id, 'snn_edu_enrolled_posts', true);
        if (!is_array($enrolled_posts)) {
            $enrolled_posts = [];
        }
    }

    // Build root li and a classes
    $root_li_classes = ['depth-0', 'root'];
    $root_a_classes = ['depth-0', 'root'];
    if ($top_parent_id === $current_post_id) {
        $root_li_classes[] = 'current';
        $root_a_classes[] = 'current';
    }
    if (in_array($top_parent_id, $enrolled_posts)) {
        $root_li_classes[] = 'enrolled';
        $root_a_classes[] = 'enrolled';
    } else {
        $root_li_classes[] = 'notenrolled';
        $root_a_classes[] = 'notenrolled';
    }

    // Check if root has children
    $has_children = get_children([
        'post_parent' => $top_parent_id,
        'post_type'   => $post_type,
        'post_status' => 'publish',
        'numberposts' => 1,
    ]);
    if (!empty($has_children)) {
        $root_li_classes[] = 'has-children';
        $root_a_classes[] = 'has-children';
    }

    // Check free_preview custom field for root
    $root_free_preview = get_post_meta($top_parent_id, 'free_preview', true);
    if ($root_free_preview === true || $root_free_preview === 'true' || $root_free_preview === '1') {
        $root_li_classes[] = 'free_preview';
    } else {
        $root_li_classes[] = 'membersonly';
    }

    // Start building the nested list
    $output = '<ul class="parent-child-list depth-0">';
    $output .= '<li class="' . implode(' ', $root_li_classes) . '">';
    $output .= '<a href="' . esc_url(get_permalink($top_parent_id)) . '" class="' . implode(' ', $root_a_classes) . '">' . esc_html($top_parent->post_title) . '</a>';

    // Add custom field for root if specified and exists
    if ($custom_field) {
        $root_field_value = get_post_meta($top_parent_id, $custom_field, true);
        if ($root_field_value) {
            $output .= '<span class="' . esc_attr($custom_field) . '">' . esc_html($root_field_value) . '</span>';
        }
    }

    // Add nested children
    $output .= snn_edu_build_nested_list($top_parent_id, $post_type, 1, $current_post_id, $enrolled_posts, $custom_field);

    $output .= '</li>';
    $output .= '</ul>';

    return $output;
}

// Step 6: Render the dynamic tag in Bricks Builder.
add_filter('bricks/dynamic_data/render_tag', 'snn_edu_render_parent_and_child_list_tag', 20, 3);
function snn_edu_render_parent_and_child_list_tag($tag, $post, $context = 'text') {
    // Ensure that $tag is a string before processing.
    if (is_string($tag)) {
        // Match {snn_edu_get_current_parent_and_child_list} or {snn_edu_get_current_parent_and_child_list:property}
        if (strpos($tag, '{snn_edu_get_current_parent_and_child_list') === 0) {
            // Extract the property from the tag
            if (preg_match('/{snn_edu_get_current_parent_and_child_list:([^}]+)}/', $tag, $matches)) {
                $property = trim($matches[1]);
                return snn_edu_get_parent_and_child_list($property);
            } elseif ($tag === '{snn_edu_get_current_parent_and_child_list}') {
                return snn_edu_get_parent_and_child_list();
            }
        }
    }

    // If $tag is an array, iterate through and process each element.
    if (is_array($tag)) {
        foreach ($tag as $key => $value) {
            if (is_string($value) && strpos($value, '{snn_edu_get_current_parent_and_child_list') === 0) {
                if (preg_match('/{snn_edu_get_current_parent_and_child_list:([^}]+)}/', $value, $matches)) {
                    $property = trim($matches[1]);
                    $tag[$key] = snn_edu_get_parent_and_child_list($property);
                } elseif ($value === '{snn_edu_get_current_parent_and_child_list}') {
                    $tag[$key] = snn_edu_get_parent_and_child_list();
                }
            }
        }
        return $tag;
    }

    // Return the original tag if it doesn't match the expected pattern.
    return $tag;
}

// Step 7: Replace placeholders in dynamic content dynamically.
add_filter('bricks/dynamic_data/render_content', 'snn_edu_replace_parent_and_child_list_in_content', 20, 3);
add_filter('bricks/frontend/render_data', 'snn_edu_replace_parent_and_child_list_in_content', 20, 2);
function snn_edu_replace_parent_and_child_list_in_content($content, $post, $context = 'text') {
    if (!is_string($content)) {
        return $content;
    }

    // Match all {snn_edu_get_current_parent_and_child_list} and {snn_edu_get_current_parent_and_child_list:property} tags
    preg_match_all('/{snn_edu_get_current_parent_and_child_list(?::([^}]+))?}/', $content, $matches);

    if (!empty($matches[0])) {
        foreach ($matches[0] as $index => $full_match) {
            $property = isset($matches[1][$index]) && $matches[1][$index] ? $matches[1][$index] : '';
            $value = snn_edu_get_parent_and_child_list($property);
            $content = str_replace($full_match, $value, $content);
        }
    }

    return $content;
}















/**
 * Custom Dynamic Data Tag: Current User Current Course Certificate Hash
 * Returns a deterministic 32-character hash based on user ID and course ID
 *
 * Usage:
 * {current_user_current_course_certificate_hash} - Returns a unique hash (e.g., "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6")
 *
 */

// Step 1: Register the tag in the builder
add_filter( 'bricks/dynamic_tags_list', 'snn_add_certificate_hash_tag' );
function snn_add_certificate_hash_tag( $tags ) {
    $tags[] = [
        'name'  => '{current_user_current_course_certificate_hash}',
        'label' => 'Current User Current Course Certificate Hash',
        'group' => 'SNN Edu',
    ];

    return $tags;
}

// Step 2: Render the tag value (for individual tag parsing)
add_filter( 'bricks/dynamic_data/render_tag', 'snn_get_certificate_hash_value', 20, 3 );
function snn_get_certificate_hash_value( $tag, $post, $context = 'text' ) {
    // Ensure $tag is a string
    if ( ! is_string( $tag ) ) {
        return $tag;
    }

    // Clean the tag (remove curly braces)
    $clean_tag = str_replace( [ '{', '}' ], '', $tag );

    // Only process our specific tag
    if ( $clean_tag !== 'current_user_current_course_certificate_hash' ) {
        return $tag;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) && $post > 0 ) {
        $post_id = (int) $post;
    }

    // Fallback: try get_queried_object_id() first, then get_the_ID()
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    // Generate the certificate hash
    $value = snn_generate_certificate_hash( $post_id );

    return $value;
}

// Step 3: Render in content (for content with multiple tags)
add_filter( 'bricks/dynamic_data/render_content', 'snn_render_certificate_hash_tag', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'snn_render_certificate_hash_tag', 20, 3 );
function snn_render_certificate_hash_tag( $content, $post, $context = 'text' ) {

    // Only process if our tag exists in content
    if ( strpos( $content, '{current_user_current_course_certificate_hash}' ) === false ) {
        return $content;
    }

    // Get the correct post ID from context
    $post_id = null;
    if ( is_object( $post ) && isset( $post->ID ) ) {
        $post_id = $post->ID;
    } elseif ( is_numeric( $post ) && $post > 0 ) {
        $post_id = (int) $post;
    }

    // Fallback: try get_queried_object_id() first, then get_the_ID()
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    // Generate the certificate hash
    $value = snn_generate_certificate_hash( $post_id );

    // Replace the tag with the value
    $content = str_replace( '{current_user_current_course_certificate_hash}', $value, $content );

    return $content;
}

// Helper function to generate deterministic certificate hash
function snn_generate_certificate_hash( $post_id = null ) {
    // Get current post ID with multiple fallbacks
    if ( ! $post_id ) {
        $post_id = get_queried_object_id();
    }
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    if ( ! $post_id ) {
        return '';
    }

    // Get current user ID
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return '';
    }

    // Combine inputs into one string
    $input = $user_id . ':' . $post_id;

    // Define available characters (letters and numbers only)
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $chars_length = strlen( $chars );

    // Step 1: Create a numeric seed from the input string using DJB2 algorithm logic
    $numeric_seed = 0;
    $input_length = strlen( $input );

    for ( $i = 0; $i < $input_length; $i++ ) {
        // Bitwise operations to create a unique number based on characters
        $numeric_seed = ( ( $numeric_seed << 5 ) - $numeric_seed ) + ord( $input[ $i ] );
        // Keep it as a 32-bit integer
        $numeric_seed = $numeric_seed & 0xFFFFFFFF;
    }

    // Step 2: Generate 32 characters using a Linear Congruential Generator (LCG)
    $result = '';
    $current_seed = abs( $numeric_seed );

    for ( $i = 0; $i < 32; $i++ ) {
        // Standard LCG constants to scramble the number further each step
        $current_seed = ( $current_seed * 1664525 + 1013904223 ) % 4294967296;
        // Pick a character based on the current state of the seed
        $result .= $chars[ $current_seed % $chars_length ];
    }

    return $result;
}
