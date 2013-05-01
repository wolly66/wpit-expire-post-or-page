<?php
/**
 * @package WPIT EXPIRE POSTS OR PAGES
 * @author Paolo Valenti & Stefano Aglietti
 * @version 1.2 beta
 */
/*
Plugin Name: WPIT Expire Post & Pages
Plugin URI: http://www.goodpress.it
Description: This plugin allow you to set expirationa date for posts and pages and to checks what are expired today
Author: Paolo Valenti & Stefano Aglietti
Version: 1.2Beta
Author URI: http://paolovalenti.info
*/

/*  
	Copyright 2012 GoodPress (email : info@goodpress.it)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//define the plugin path with final trailing slash
define( 'WPIT_POSTPAGES_EXPIRE_PATH', plugin_dir_path(__FILE__) );


// Add action and filters
// Add meta box in page and post
add_action( 'add_meta_boxes', 'wpit_ppe_metabox' );
// Save the expiration metadata
add_action( 'save_post', 'wpit_ppe_metabox_save' );  
// Function Show expire post Column in WP-Admin
add_action('manage_posts_custom_column', 'wpit_ppe_add_expire_column_content', 5, 2);
add_filter('manage_posts_columns', 'wpit_ppe_add_expire_column', 5, 2);
// Function Show expire page Column in WP-Admin
add_action('manage_pages_custom_column', 'wpit_ppe_add_expire_column_content',5, 2);
add_filter('manage_pages_columns', 'wpit_ppe_add_expire_column',5, 2);
// Creation of administration menu and sub menu
add_action('admin_menu', 'wpit_ppe_admin_menu');


function my_admin_init($hook) {
    
	if( ! ( 'post-new.php' == $hook || 'post.php' == $hook ) )
        return;
    $pluginfolder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-datepicker');
	wp_register_script( 'jquery-ui-i18n', 'http://jquery-ui.googlecode.com/svn/trunk/ui/i18n/jquery.ui.datepicker-it.js', 'jquery-ui-datepicker', false, true );
    wp_enqueue_script( 'jquery-ui-i18n' );
    
    wp_enqueue_style('jquery.ui.theme', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/themes/base/jquery-ui.css');
}

add_action('admin_enqueue_scripts', 'my_admin_init');



//check permissions to manage options
function wpit_ppe_check_permissions(){
    if ( !current_user_can( 'edit_post' ) )  {
    	wp_die( __( 'You do not have sufficient permissions to access this page.' , 'wpit-gantt' ) );
	}
}

function wpit_ppe_metabox() {
	$types = array('post', 'page');
	foreach($types as $type) {
	   add_meta_box( '10', 'Data di scadenza', 'wpit_ppe_metabox_help', $type, 'side', 'high' );
	}
    
}

function wpit_ppe_metabox_help( $post ){
	$values = get_post_custom( $post->ID );
	$text = isset( $values['_postexpire'] ) ? esc_attr( $values['_postexpire'][0] ) : '';
	
	// We'll use this nonce field later on when saving.  
    wp_nonce_field( 'wpit_metabox_nonce', 'wpit_meta_box_nonce' ); 
	
    echo '
    <p>
	   <input type="text" name="_postexpire" id="_postexpire" value="' . wpit_ppe_reversedate($text) . ' " readonly="true" />
    </p>
    <p class="howto">Indicare una eventuale data di scadenza per l\'articolo o la pagina, questa data non rimuover&agrave; l\'articolo ma svolge solo la funzione di segnalare quali articoli o pagine sono da aggiornare entro una certa data</p>';
    
    echo '
    <script>
        jQuery(document).ready(function() {
            jQuery("#_postexpire").datepicker({
                autoSize: true,
                constrainInput: true,
            });
        });
    </script>';
}


function wpit_ppe_metabox_save( $post_id ) {  

    // Bail if we're doing an auto save  
    if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return; 
    // if our nonce isn't there, or we can't verify it, bail 
    if( !isset( $_POST['wpit_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['wpit_meta_box_nonce'], 'wpit_metabox_nonce' ) )
        return; 
    // if our current user can't edit this post, bail  
    if( !current_user_can( 'edit_post' ) )
        return;  

    // now we can actually save the data  
    // Make sure your data is set before trying to save it  
    if( (isset( $_POST['_postexpire'] ) ) )
    		if ( $_POST['_postexpire'] != ' ')
	    update_post_meta( $post_id, '_postexpire',  wpit_ppe_reversedate ($_POST['_postexpire']) );
    	
          
        
            
}  

function wpit_ppe_admin_menu() {

	add_submenu_page( 
          'edit.php' 
        , 'Elementi in scadenza' 
        , 'Elementi in scadenza'
        , 'administrator'
        , 'post-expire'
        , 'wpit_ppe_list_diplay'
    );
    
	add_submenu_page( 
          'edit.php?post_type=page' 
        , 'Elementi in scadenza' 
        , 'Elementi in scadenza'
        , 'administrator'
        , 'post-expire'
        , 'wpit_ppe_list_diplay'
    );

}


function wpit_ppe_add_expire_column( $defaults ) {
    $defaults['wpitexpire'] = 'Scadenza';
    return $defaults;
}

### Functions Fill the expire date
function wpit_ppe_add_expire_column_content($column_name) {
    if( 'wpitexpire' == $column_name ) {
        echo wpit_ppe_reversedate( get_post_meta( get_the_ID() , '_postexpire', true ) ); 
    }
}
        
function wpit_ppe_list_diplay () {

    $head_foot_content = '
    <tr>
        <th scope="col" id="descr" class="manage-column column-descr">
            <span>Titolo articolo/pagina</span>
        </th>
        <th scope="col" id="descr" class="manage-column column-datascad">
            <span>Data di scadenza</span>
        </th>
        <th scope="col" id="descr" class="manage-column column-datapubb">
            <span>Pubblicato</span>
        </th>
        <th scope="col" id="descr" class="manage-column column-datamod">
            <span>Ultima modifica</span>
        </th>
        <th scope="col" id="descr" class="manage-column column-descr">
            <span>Tipo</span>
        </th>
        <th scope="col" id="descr" class="manage-column column-descr">
            <span>Modifica</span>
        </th>
    </tr>'; 

	//Page title
	$th = '
    <div class="wrap">
        <div id="icon-edit" class="icon32 icon32-posts-post"><br /></div>
        <h2>Articoli e Pagine scadute al ' . date('d/m/Y') . '</h2></div>
        <br />
        
        <table class="wp-list-table widefat fixed posts" cellspacing="0">
            <thead>' . $head_foot_content . '</thead>';
				    							
	$args = array(
        'post_type' => array( 'post' , 'page' ),
        'meta_key' => '_postexpire',
        'meta_value' => date('Ymd'),
        'meta_compare' => '<=',
        'orderby' => 'meta_value',
    );
    
    $elements = get_posts( $args );


    foreach ( $elements as $post) {
 		$titolo = $post->post_title;
 		$type = ('post' == $post->post_type)? 'Articolo' : 'Pagina';
 		$expiredate = get_post_meta( $post->ID , '_postexpire', true );
 		$adminurl = admin_url();
        $post_status = get_post_status_object( $post->post_status );
 		$th .= '<tr>
                    <td><span>
                        <a class="row-title" href="' . get_edit_post_link( $post->ID ) . '" title="modifica">' . $titolo . '<a>
                        <div class="row-actions">
                            <span class="edit"><a href="' . get_edit_post_link( $post->ID ) . '" title="Modifica questo elemento">Modifica</a> | </span>
                            <span class="trash"><a class="submitdelete" title="Spostare questo elemento nel cestino." href="' . get_delete_post_link ( $post->ID ) . '">Cestina</a> | </span><span class="view"><a href="http://test456.wpitaly.it/?p=1" title="Visualizza &quot;Ciao mondo!!&quot;" rel="permalink">Visualizza</a></span></div>
                    
                    </span></td>
                    <td><span>' . wpit_ppe_reversedate($expiredate) . '</span></td>
                    <td class="date column-date"><abbr title="' . $post->post_date . '">' . mysql2date('d/m/Y', $post->post_date, true) . '</abbr><br>' . $post_status->label . '</td>
                    <td class="date column-date"><abbr title="' . $post->post_modified . '">' . mysql2date('d/m/Y', $post->post_modified, true) . '</abbr></td>
                    <td><span>' . $type . '</span></td>
                    <td><span>
                        <a href="' . get_edit_post_link( $post->ID ) . '" title="modifica">Modifica<a>
                    </span></td>
                </tr>';
	}

	//table footer 
	$th .= '
    <tfoot>' . $head_foot_content . '</tfoot>
    </table>
    </form>';
    
    echo $th;

}

function wpit_ppe_reversedate( $date ) {

    if (preg_match('%([\d]{2,4})/??([\d]{2,2})/??([\d]{2,4})%', $date, $regs)) {
    	if ( 4 == strlen($regs[1]) ) {
            $reversedate = $regs[3] . '/' . $regs[2] . '/' . $regs[1];
        } else {
            $reversedate = $regs[3] . $regs[1] . $regs[2];
        }
    } else {
        $reversedate = $date;
    }
    
    return $reversedate;
}

