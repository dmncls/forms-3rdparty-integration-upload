<?php
/*
 Plugin Name: Forms: 3rd-Party Integration - Upload
 Plugin URI: https://github.com/dominiceales/forms-3rdparty-integration-upload
 Description: Extension to zaus/forms-3rdparty-integration, forwards uploaded files to 3rd party
 Author: dominiceales
 Version: 1.1
 Changelog:
    1.1 - Initial version, works for CF7 and probably Ninja
*/

include_once plugin_dir_path(__FILE__).'/../forms-3rdparty-integration/forms-3rdparty-integration.php';

add_filter( 'Forms3rdPartyIntegration_init', 'f3iup_init' );

function f3iup_init() {
    add_filter( 'Forms3rdPartyIntegration_service_filter_args', 'f3iup_check_for_file_uploads', 10, 4 );
} 
 
function f3iup_check_for_file_uploads( $post, $service, $form, $submission ) {

    // check if we have uploaded files, if not then skip processing
    if ( !isset( $submission['FILES'] ) ) {
        return $post;
    }

    $boundary = wp_generate_password( 24 );
    $post['headers']['content-type'] = 'multipart/form-data; boundary=' . $boundary;
    $payload = '';

    // build reverse lookup for third party values
    $third2form = array();
    foreach($service['mapping'] as $mid => $mapping){
        $third2form[$mapping[Forms3rdPartyIntegration::PARAM_3RD]] = $mapping[Forms3rdPartyIntegration::PARAM_SRC];
    }
    
    // field prefix
    $upload_field_prefix = 'UPLOAD_';

    // check if we have any files to upload
    foreach ( $post['body'] as $field => $value ) {
        if ( 0 === strpos($field,$upload_field_prefix) ) {

            $filename = $submission['FILES'][$third2form[$field]];
            $field = substr( $field, strlen($upload_field_prefix));

            if (is_file($filename)) {
                $payload .= '--' . $boundary;
                $payload .= "\r\n";
                $payload .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . basename( $value ) . '"' . "\r\n";
                $payload .= 'Content-Type: ' . get_mime_type($value, 'C:\\xampp\\apache\\conf') . "\r\n";
                $payload .= "\r\n";
                $payload .= file_get_contents( $filename );
                $payload .= "\r\n";
                continue;
            }
        }

        // for non-file fields or file fields with missing file
        $payload .= '--' . $boundary;
        $payload .= "\r\n";
        $payload .= 'Content-Disposition: form-data; name="' . $field . '"' . "\r\n\r\n";
        $payload .= $value;
        $payload .= "\r\n";
    }
    $payload .= '--' . $boundary . '--';
    
    $post['body'] = $payload;

    return $post;
}


function get_mime_type( $filename, $mimePath = '../etc' ) {
    $fileext = substr( strrchr( $filename, '.' ), 1 );
    if ( empty( $fileext ) )
        return (false);
    $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileext\s)/i";
    $lines = file( "$mimePath/mime.types" );
    foreach ( $lines as $line ) {
        if ( substr( $line, 0, 1 ) == '#' )
            continue;
        // skip comments
        $line = rtrim( $line ) . " ";
        if ( !preg_match( $regex, $line, $matches ) )
            continue;
        // no match to the extension
        return ($matches[1]);
    }
    return (false);
    // no match at all
}

    
