<?php
/*
Plugin Name: Lela Studios Loopback
Description: Reads loopback posts from api and creates wordpress posts. Writes new wordpress posts to loopback api
*/

define('LOOPBACK_URL','https://lelastudios.herokuapp.com');
define('LOOPBACK_EMAIL', 'admin@admin.com');
define('LOOPBACK_PASSWORD', 'admin');
//skip unique titles regardless, extra protection if keys fail
define('LEELA_UNIQUE_TITLES', false);
//cron to get posts
if ( ! wp_next_scheduled( 'leela_loopback_get_posts' ) ) {
  wp_schedule_event( time(), 'hourly', 'leela_loopback_get_posts' );
}

add_action( 'leela_loopback_get_posts', 'leela_loopback_read' );

//query_var force get posts
add_action ('init','leela_loopback_force');

/**
 * Force api read if loopback query var is set to "run"
 *
 * Used by hooking into wp action, allows user to force a api pull instead of waiting for cron
 */
function leela_loopback_force() {
    global $runonce;
    if($_GET['loopback']=='run' && !$runonce) {
        leela_loopback_read();
    }
    $runonce=false;
}

/**
 * Read posts from api, and upsert posts in wordpress
 *
 * Posts store api id in "loopback_id" meta value
 */
function  leela_loopback_read() {
    remove_action('publish_post','leela_postback_write',10);
    global $user;
    //read api
    //iterate
    $meta=false;
    $api_posts=false;
    $token=leela_login();
    $data=json_decode(leela_curl(LOOPBACK_URL.'/api/posts/show', $token));
    foreach($data->post as $api) {
        //create list of api posts for later wp_query, so we don't run queries
        //in a loop
        $meta = leela_lookup_append_id($meta, $api->id);
        //index api posts by post_id for easy access
        $api_posts[$meta[0]['value']]=$api;
    }
    //lookup posts from wordpress
    $meta_query = leela_lookup_meta($meta);
    //update existing posts if content is different
    while ( $meta_query->have_posts() ) {
        $meta_query->the_post();
        //@TODO: confirm if different method exists to get_post_meta inside
        //this loop, concern exists about running sql in a loop, may need to
        //refactor to plain old SQL, but meta_values are RBAR
        $api_id = get_post_meta(get_the_ID(), 'loopback_id');
        //@TODO: check if bodies match, if not, update
        //remove from api posts, later we need to loop to add new posts
        unset($api_posts[$api_id]);
    }
    //add posts that do not exist
    $userid=get_current_user_id();
    foreach($api_posts as $post) {
        //create new post
        $new_post = array(
          'ID' => '',
          'post_author' => $userid,
          'post_content' => $post->content,
          'post_title' => $post->title,
          'post_status' => 'publish'
        );
        $db_post=get_page_by_title($post->title, OBJECT, 'post');
        if(!is_object($db_post) && LEELA_UNIQUE_TITLES) {
            $post_id = wp_insert_post($new_post);
            leela_loopback_post_key($post_id, $loopback_id);
        }
    }
}

/**
 * Wrapper for curl to request loopback api
 *
 * @param string url Loopback api endpoint
 * @param string $data (optional) data to send to loopback api as json post
 *
 * @return string response of loopback request
 */
function leela_curl($url, $token=false, $data=false) {
    if($token) {
        $url=$url.'?access_token='.$token;
    }
    $curl_handle=curl_init();
    curl_setopt($curl_handle,CURLOPT_URL,$url);
    curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,10);
    curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
    if($data) {
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER,array('Accept: application/json','Content-Type: application/json'));
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    if (empty($buffer)) {
        throw new RuntimeException('Empty response from loopback api');
    }
    return $buffer;
}

/**
 * Creates array to be used in wp_query for looking up existing API posts
 *
 * For each post added to wordpress from loopback api, a meta value exists "loopback_id", storing
 * the post id of the loopback api
 * This function builds the parameter for WP_Meta_Query so multiple posts can be retrieved at once
 * Helpful when iterating through api responses and preventing a query in a loop
 *
 * @param mixed[] $meta existing meta arguments, initiate with an empty variable if none exists, and send back to this function in an iterator
 * @param integer $loopback_id post id from api to include in query
 *
 * @return mixed[] $meta same input parameter, with meta lookup element appended
 */
function leela_lookup_append_id ($meta, $loopback_id) {
    $meta[]=array(
        'key'       => 'loopback_id',
        'value'     => $loopback_id,
        'compare'   => '='
    );
     return $meta;
}

/**
 * Runs meta query with meta argument from function parameters and returns results
 *
 * Meta arguments needs further processing before being run in wp meta query
 * After formating meta arguments meta query is run and results are returned
 *
 * @param mixed[] $meta array of meta query arguments, which is an associative array with key, value and compare properties
 *
 * @return mixed[] WP_Meta_Query result of WP_Meta_Query
 */
function leela_lookup_meta($meta) {
     $args = array(
        'meta_query' => array(
            'relation'=>'OR',
        )
    );
     foreach($meta as $row) {
          $args['meta_query'][]=$row;
    }
    return new WP_Query( $args );
}

/**
 * Adds 'loopback_id' meta value to post
 *
 * Need method to associate loopback api posts with wordpress posts
 * Accomplished by creating 'loopback_id' meta value custom post option
 *
 * @param integer $wp_id wordpress post id
 * @param integer $loopback_id loopback api post id
 *
 */
function leela_loopback_post_key($wp_id, $loopback_id) {
    if ( ! add_post_meta( $wp_id, 'loopback_id', $loopback_id, true ) ) {
       update_post_meta( $wp_id, 'loopback_id', $loopback_id );
    }
}

add_action('publish_post','leela_postback_write',10,2);

/**
 * Writes post to loopback api
 *
 * Sends title and content to loopback api for upsert
 * Uses wp post meta loopback_id as loopback api post id
 * Function is hooked into wp publish_post action
 *
 * @param integer $ID id of post, used for "loopback_id" meta value lookup
 * @param object $post wp post object
 *
 */
function leela_postback_write($ID, $post) {
    $title = $post->post_title;
    $content = $post->post_content;
    $loopback_id = get_post_meta($ID, 'loopback_id');
    try {
        $result=leela_curl(LOOPBACK_URL.'/api/posts/publish', leela_login(), array('title'=>$title,'content'=>$content,'id'=>$loopback_id));
    }
    catch (RuntimeException $e) {
        return;
    }
}

function leela_login() {
    $response=leela_curl(LOOPBACK_URL.'/api/users/login', false, array('email'=>LOOPBACK_EMAIL,'password'=>LOOPBACK_PASSWORD,'ttl'=>60));
    $response=json_decode($response);
    return $response->id;
}
?>
