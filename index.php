<?php
/*
Plugin Name: Lela Studios Loopback
Description: Reads loopback posts from api and creates wordpress posts. Writes new wordpress posts to loopback api
*/

define('LOOPBACK_URL','https://whispering-river-30674.herokuapp.com');

if ( ! wp_next_scheduled( 'leela_loopback_get_posts' ) ) {
  wp_schedule_event( time(), 'hourly', 'leela_loopback_get_posts' );
}

add_action( 'leela_loopback_get_posts', 'leela_loopback_read' );

function  leela_loopback_read() {
    //read api
    //iterate
    $meta=false;
    $api_posts=false;
    foreach(json_decode(leela_curl(LOOPBACK_URL.'/api/posts')) as $api) {
        //create list of api posts for later wp_query, so we don't run queries
        //in a loop
        $meta = leela_lookup_append_id($meta, $api->id);
        //index api posts by post_id for easy access
        $api_posts[$api->id]=$api;
    }
    //lookup posts from wordpress
    $meta_query = new WP_Meta_Query($meta);
    //update existing posts if content is different
    foreach($meta_query as $update_post) {
        //@TODO: confirm if different method exists to get_post_meta inside
        //this loop, concern exists about running sql in a loop, may need to
        //refactor to plain old SQL, but meta_values are RBAR
        $api_id = get_post_meta(get_the_ID(), 'loopback_id');
        //check if bodies match, if not, update
        //remove from api posts, later we need to loop to add new posts
        unset($api_posts[$api_id]);
    }
    //add posts that do not exist
    foreach($api_posts as $post) {
         //create new post
    }
}

function leela_curl($url) {
    $curl_handle=curl_init();
    curl_setopt($curl_handle,CURLOPT_URL,$url);
    curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
    curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    if (empty($buffer)) {
        throw new RuntimeException('Empty response from loopback api');
    }
    return $buffer;
}

function leela_lookup_append_id ($meta, $api_id) {
    $meta[]=array(
        'key'       => 'loopback_id',
        'value'     => $api_id,
        'compare'   => '='
    );
     return $meta;
}

function leela_lookup_meta($meta) {
     $args = array(
        'meta_query' => array(
            'relation'=>'OR',
            $meta
        ),
    );
    return new WP_Meta_Query( $meta_query_args );
}

function leela_loopback_post_key($wp_id, $loopback_id) {
    if ( ! add_post_meta( $wp_id, 'loopback_id', $loopback_id, true ) ) {
       update_post_meta( $wp_id, 'loopback_id', $loopback_id );
    }
}



?>
