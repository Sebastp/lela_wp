<?php
/*
Plugin Name: Lela Studios Loopback
Description: Reads loopback posts from api and creates wordpress posts. Writes new wordpress posts to loopback api
*/

if ( ! wp_next_scheduled( 'my_task_hook' ) ) {
  wp_schedule_event( time(), 'hourly', 'my_task_hook' );
}

add_action( 'my_task_hook', 'my_task_function' );

function my_task_function() {
  wp_mail( 'your@email.com', 'Automatic email', 'Automatic scheduled email from WordPress.');
}
?>
