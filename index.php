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

}
?>
