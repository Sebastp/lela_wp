<?php
/*
Plugin Name: Lela Studios Loopback
Description: Reads loopback posts from api and creates wordpress posts. Writes new wordpress posts to loopback api
*/


if ( ! wp_next_scheduled( 'leela_loopback_get_posts' ) ) {
  wp_schedule_event( time(), 'hourly', 'leela_loopback_get_posts' );
}

add_action( 'leela_loopback_get_posts', 'leela_loopback_read' );

function  leela_loopback_read() {
    //read api
    //iterate
    //get post
    //compare with existing
    //update or create
}

function leela_loopback_post_key($wp_id, $loopback_id) {
    if ( ! add_post_meta( $wp_id, 'loopback_id', $loopback_id, true ) ) {
       update_post_meta( $wp_id, 'loopback_id', $loopback_id );
    }
}
?>
