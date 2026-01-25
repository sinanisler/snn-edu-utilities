<?php
/**
 * Add 'guest' class to body when user is not logged in
 */
function snn_add_guest_body_class( $classes ) {
    if ( ! is_user_logged_in() ) {
        $classes[] = 'guest';
    }
    return $classes;
}
add_filter( 'body_class', 'snn_add_guest_body_class' );
