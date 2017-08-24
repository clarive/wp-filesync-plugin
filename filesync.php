<?php
   /*
   Plugin Name: Filesync
   Plugin URI: http://github.com/clarive/wp-filesync
   Description: a plugin that syncs content with the filesystem
   Version: 1.0
   Author: The Clarive Team
   Author URI: http://clarive.com
   License: GPL2
   */

/*
function post_updated( $post_id ) {

    // If this is just a revision, don't send the email.
    if ( wp_is_post_revision( $post_id ) )
        return;

    $post_title = get_the_title( $post_id );
    $post_url = get_permalink( $post_id );
    $subject = 'A post has been updated';

    $message = "A post has been updated on your website:\n\n";
    $message .= $post_title . ": " . $post_url;

    $myfile = fopen( __DIR__ . "/bbb.txt", "w") or die("Unable to open file!");
    fwrite($myfile, $message);
    fclose($myfile);

}
 */

add_action( 'save_post', 'post_updated' );

if( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once "fs_cli.php";
}

function recurse_copy( $src, $dst ) { // https://stackoverflow.com/a/33696183/636790
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

?>
