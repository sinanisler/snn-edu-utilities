<?php

/**
 * Custom Dynamic Data Tag: Current Course Enrollment Percentage
 * Returns the percentage of completed/enrolled posts for the current course
 * 
 * {get_current_course_enrollment_percentage}
 * 
 */

// Step 1: Register the tag in the builder
add_filter( 'bricks/dynamic_tags_list', 'snn_add_course_enrollment_percentage_tag' );
function snn_add_course_enrollment_percentage_tag( $tags ) {
    $tags[] = [
        'name'  => '{get_current_course_enrollment_percentage}',
        'label' => 'Current Course Enrollment Percentage',
        'group' => 'SNN Edu',
    ];

    return $tags;
}

// Step 2: Render the tag value
add_filter( 'bricks/dynamic_data/render_tag', 'snn_get_course_enrollment_percentage_value', 20, 3 );
function snn_get_course_enrollment_percentage_value( $tag, $post, $context = 'text' ) {
    if ( ! is_string( $tag ) ) {
        return $tag;
    }

    // Clean the tag
    $clean_tag = str_replace( [ '{', '}' ], '', $tag );

    // Only process our specific tag
    if ( $clean_tag !== 'get_current_course_enrollment_percentage' ) {
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

    // Get the enrollment percentage
    $value = snn_calculate_course_enrollment_percentage( $post_id );

    return $value;
}

// Step 3: Render in content
add_filter( 'bricks/dynamic_data/render_content', 'snn_render_course_enrollment_percentage_tag', 20, 3 );
add_filter( 'bricks/frontend/render_data', 'snn_render_course_enrollment_percentage_tag', 20, 2 );
function snn_render_course_enrollment_percentage_tag( $content, $post, $context = 'text' ) {

    // Only process if our tag exists in content
    if ( strpos( $content, '{get_current_course_enrollment_percentage}' ) === false ) {
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

    // Get the enrollment percentage
    $value = snn_calculate_course_enrollment_percentage( $post_id );

    // Replace the tag with the value
    $content = str_replace( '{get_current_course_enrollment_percentage}', $value, $content );

    return $content;
}

// Helper function to calculate enrollment percentage
function snn_calculate_course_enrollment_percentage( $post_id = null ) {
    // Get current post ID
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }

    if ( ! $post_id ) {
        return '0%';
    }

    // Get current user
    $current_user_id = get_current_user_id();

    if ( ! $current_user_id ) {
        return '0%';
    }

    // Get user's enrolled posts
    $enrolled_posts = get_user_meta( $current_user_id, 'snn_edu_enrolled_posts', true );

    // Ensure it's an array
    if ( ! is_array( $enrolled_posts ) || empty( $enrolled_posts ) ) {
        $enrolled_posts = [];
    }

    // Get the top-level parent (level_0)
    $top_parent_id = snn_get_top_level_parent( $post_id );

    // Get all child posts including the parent itself
    $all_course_posts = snn_get_all_course_posts( $top_parent_id );

    if ( empty( $all_course_posts ) ) {
        return '0%';
    }

    // Calculate how many posts user has enrolled in
    $enrolled_count = count( array_intersect( $enrolled_posts, $all_course_posts ) );
    $total_count = count( $all_course_posts );

    // Calculate percentage
    if ( $total_count > 0 ) {
        $percentage = round( ( $enrolled_count / $total_count ) * 100 );
    } else {
        $percentage = 0;
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

    return $parent_id;
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
        'post_parent'    => $parent_id,
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