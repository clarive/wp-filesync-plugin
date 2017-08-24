<?php

require_once "spyc/spyc.php";

WP_CLI::add_command( 'fs', 'FileSyncCLI' );

class FileSyncCLI extends WP_CLI_Command {
    /**
     *
     * Syncronizes database and other content into a directory.
     *
     * ## OPTIONS
     *
     * <repopath>
     * : the root path of the repository
     *   to be dumped into, or '.' to dump into the current dir.
     *
     * [--no-cleanup]
     * : Prevent removing Windows newlines, replaces tabs with spaces and
     *   removes spaces at the end of the line.
     *
     * [--grep]
     * : Filter files to sync with a regex.
     *
     * @subcommand sync
     * @alias sync
     *
     **/
    function sync( $args, $assoc_args ) {

        $repo = $args[0];

        // remove missing
        $this->_delete_old( $repo, $args, $assoc_args );

        // dump
        $this->dump( $args, $assoc_args );

        // upload dir
        $upload_parts = wp_upload_dir();
        $upload_dir = $upload_parts['basedir'];
        echo "upload dir=$upload_dir";

        if ( file_exists( $upload_dir ) ) {

            $dest = $repo . '/uploads';
            if( ! file_exists( $dest ) ) {
                mkdir( $dest, 0744, true);
            }

            recurse_copy( $upload_dir, $dest );
        }

        WP_CLI::success( "$cnt file(s) dumped." );
    }

    /**
     *
     * Dumps the database content into a root repository.
     *
     * ## OPTIONS
     *
     * <repopath>
     * : the root path of the repository
     *   to be dumped into, or '.' to dump into the current dir.
     *
     * [--no-cleanup]
     * : Removes Windows newlines, replaces tabs with spaces and
     *   removes spaces at the end of the line.
     *
     * [--grep]
     * : Filter files to dump with a regex.
     *
     * @subcommand dump
     * @alias dump
     *
     **/
    function dump( $args, $assoc_args ) {

        $repo = $args[0];
        $postmeta = $this->_get_meta();
        $results = $this->_get_posts();

        $cnt = 0;

        foreach ($results as $post) {

            $this->_dump_post( $repo, $post, $postmeta, $assoc_args );

            $cnt++;
        }

        WP_CLI::success( "$cnt file(s) dumped." );
    }

    /**
     *
     * Dumps a single file data to the repository.
     *
     * ## OPTIONS
     *
     * <repopath>
     * : the root path of the repository
     *   to be dumped into, or '.' to dump into the current dir.
     *
     * [--no-cleanup]
     * : Removes Windows newlines, replaces tabs with spaces and
     *   removes spaces at the end of the line.
     *
     * @subcommand dump-file
     * @alias dump-file
     *
     **/
    function dump_file( $args, $assoc_args ) {

        $path = $args[0];
        $repo = realpath( dirname( dirname($path) ) );

        $parts = $this->_parse_file( $path );
        $data = $parts[0];

        if( ! isset( $data['ID'] ) ) WP_CLI::error( "invalid file yaml metadata: $path" );

        $id = $data['ID'];
        $post = $this->_get_posts( $id );
        $postmeta = $this->_get_meta( $id );
        $file = $this->_dump_post( $repo, $post, $postmeta, $assoc_args, $path );
        echo "dumped $file\n";
    }

    /**
     *
     * Dumps a single file data to the repository by post ID.
     *
     * ## OPTIONS
     *
     * <repopath>
     * : the root path of the repository
     *   to be dumped into, or '.' to dump into the current dir.
     *
     * <id>
     * : the post Wordpress ID.
     *
     * [--no-cleanup]
     * : Removes Windows newlines, replaces tabs with spaces and
     *   removes spaces at the end of the line.
     *
     * @subcommand get
     * @alias get
     *
     **/
    function dump_id( $args, $assoc_args ) {

        $repo = $args[0];
        $id = $args[1];

        $post = $this->_get_posts( $id );
        $postmeta = $this->_get_meta( $id );

        $file = $this->_dump_post( $repo, $post, $postmeta, $assoc_args );

        echo "dumped $file\n";
    }

    /**
     *
     * Loads a complete repository into WP.
     *
     * ## OPTIONS
     *
     * <repopath>
     * : the root path of the repository
     *   to be loaded, or '.' to load the current dir.
     *
     * [--keep-date]
     * : Does not change the modified date
     *
     * [--no-refresh]
     * : Does not rewrite file with new date and id
     *
     * [--grep]
     * : Filter files to load with a regex.
     *
     * @subcommand load
     * @alias load
     *
     **/
    function load( $args, $assoc_args ) {
        $repo = $args[0];

        $git_dir = realpath( "$repo/.git" );
        $upload_dir = realpath( "$repo/uploads" );

        $Directory = new RecursiveDirectoryIterator( $repo );
        $Iterator = new RecursiveIteratorIterator( $Directory );

        foreach ( $Iterator as $filename=>$cur ) {
            $path = $cur->getPathname();

            // ignore .git files
            if( substr( $path, 0, strlen($git_dir) ) == $git_dir ) continue;

            // ignore not posts
            if( ! preg_match( '/\.(yml|html)$/', $path ) ) continue;

            // ignore uploads
            if( substr( $path, 0, strlen($upload_dir) ) == $upload_dir ) continue;

            echo ">>> $path\n";

            $this->load_file( [ $path ], $assoc_args );

        }
    }

    /**
     *
     * Loads a single file.
     *
     * ## OPTIONS
     *
     * <filepath>
     * : the path of the file to be loaded
     *
     * [--no-cleanup]
     * : Removes Windows newlines, replaces tabs with spaces and
     *   removes spaces at the end of the line.
     *
     * [--keep-date]
     * : Does not change the modified date
     *
     * [--no-refresh]
     * : Does not rewrite file with new date and id
     *
     * @subcommand load-file
     * @alias load-file
     *
     **/
    function load_file( $args, $assoc_args ) {

        $path = $args[0];
        $repo = realpath( dirname( dirname($path) ) );

        $parts = $this->_parse_file( $path );
        $content = $parts[1];

        if( ! array_key_exists( 'no-cleanup', $assoc_args ) ) {
            $content = $this->_cleanup_content( $content );
        }

        $data = $parts[0];
        $data['post_content'] = $content;
        $meta = $data['meta'];
        unset( $data['meta'] );

        if( ! array_key_exists( 'keep-date', $assoc_args ) ) {
            $date = current_time('mysql');
            $data['post_modified'] = $date;
            $data['post_modified_gmt'] = $date;
            echo "new modified time = $date\n";
        }

        // use wp_slash() to escape data? see: https://developer.wordpress.org/reference/functions/wp_update_post/

        if( isset( $data['ID'] ) && is_string( get_post_status( $data['ID'] ) ) ) {

            $id = $data['ID'];
            wp_update_post( $data );

            echo "updated post id=$id, title=$data[post_title], name=$data[post_name]\n";
        }
        else {
            $id = wp_insert_post( $data );
            echo "post did not exist, inserted with id=$id, title=$data[post_title], name=$data[post_name]\n" ;
            $data['ID'] = $id;
        }

        foreach( $meta as $meta_key => $meta_value ) {
            update_post_meta( $id, $meta_key, $meta_value );
        }
        // TODO remove meta keys that are not in file?

        $this->_dump_post( $repo, $data, $meta, $assoc_args );
        echo "updated file $path\n";
    }

    private function _parse_file( $path ) {
        if (!file_exists( $path )) {
            WP_CLI::error( "file does not exist: $path\n" );
        }

        $body = file_get_contents( $path );
        $parts = explode( "\n---\n", $body );
        $data = Spyc::YAMLLoad( $parts[0] );

        if( ! is_array( $data ) ) {
            WP_CLI::error( "invalid yaml for file $file\n" );
        }

        return [ $data, $parts[1] ];
    }

    private function _get_meta( $id = false ) {
        global $wpdb;

        $id_condition = $id ? "WHERE post_id = $id " : "";

        $metaquery = "
            SELECT *
            FROM $wpdb->postmeta
            $id_condition
            ORDER BY LOWER(meta_key)
            ";

        $postmeta = [];
        $meta_result = $wpdb->get_results($metaquery, ARRAY_A );

        foreach ($meta_result as $row) {
            $id = $row['post_id'] . "";
            if( !isset( $postmeta[ $id ] ) ) {
                $postmeta[ $id ] = array();
            }

            $postmeta[ $id ][ $row['meta_key'] ] = $row['meta_value'];
        }

        return $postmeta;
    }

    private function _get_posts( $id = false ) {
        global $wpdb;

        $id_condition = $id ? "AND ID = $id " : "";

        $querystr = "
            SELECT *
            FROM $wpdb->posts
            WHERE post_status != 'trash'
            AND post_type != 'revision'
            $id_condition
            ORDER BY $wpdb->posts.post_date DESC
            ";

        $results = $wpdb->get_results( $querystr, ARRAY_A );

        if( $id ) {
            return $results[0];
        }
        else {
            return $results;
        }
    }

    private function _dump_post( $repo, $post, $postmeta = false, $assoc_args, $prev_file = false ) {

        $id = $post['ID'];

        if( ! $postmeta ) {
            $postmeta = $this->_get_meta( $id );
        }

        ksort( $post );

        $type = $post['post_type'];
        $title = $post['post_title'];
        if( strlen( $post['post_title'] ) ) {
            $title = preg_replace('/(\t|\s|-|\\\|\\/|\\.|"|\'|\[|\]|\!|\?|\#|\%|\(|\)|\+|\*|\^|\:|\;|\|)+/', '_', strtolower( $post['post_title'] ) );
            $title = preg_replace('/_+/', '_', $title );
        }
        else {
            $title = $type . "-" . $id;
        }

        $content = $post['post_content'];
        unset( $post['post_content'] );
        $header = Spyc::YAMLDump( $post );

        $dir = "$repo/$type";
        $ext = preg_match( '/(post|page)/', $type ) ? 'html' : 'yml';
        $file = "$repo/$type/$title.$ext";

        if( isset( $assoc_args['grep'] ) ) {
            if( ! preg_match( $assoc_args['grep'], $file ) ) {
                echo "--- $file\n";
                return;
            }
            else {
                echo "+++ $file\n";
            }
        }

        if( array_key_exists( "$id", $postmeta ) ) {
            $post[ 'meta' ] = $postmeta[ "$id" ];
        }
        else {
            $post[ 'meta' ] = array();
        }

        if (!file_exists( $dir )) {
            mkdir( $dir, 0744, true);
        }

        if( ! array_key_exists( 'no-cleanup', $assoc_args ) ) {
            $content = $this->_cleanup_content( $content );
        }

        $body = Spyc::YAMLDump( $post )
               .  "---\n$content"
               ;
        $myfile = fopen( $file, "w") or WP_CLI::error("Unable to open file `$file`");
        fwrite( $myfile, $body );
        fclose( $myfile );

        if( $prev_file && realpath($prev_file) != realpath($file) ) {
            unlink( $prev_file ) or WP_CLI::error( "Could not delete renamed file: $prev_file" );
            echo "removed old file $prev_file for ID=$id\n";
        }

        return $file;
    }

    private function _cleanup_content( $content ) {
        $content = preg_replace('/\r/', '', $content );  // no windows new line
        $content = preg_replace('/\t/', '   ', $content ); // no tabs
        $content = preg_replace('/\s+\n/', "\n", $content ); // no spaces at end of line
        return $content;
    }

    private function _delete_old( $repo, $args, $assoc_args ) {


    }
}

?>
