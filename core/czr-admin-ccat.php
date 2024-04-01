<?php
/**
* Init admin actions : loads the meta boxes,
*
*/
if ( !class_exists( 'CZR_admin_init' ) ) :
  class CZR_admin_init {
    static $instance;
    function __construct () {

      self::$instance =& $this;
      //enqueue additional styling for admin screens
      add_action( 'admin_init'            , array( $this, 'czr_fn_admin_style' ) );

      //refresh the post / CPT / page thumbnail on save. Since v3.3.2.
      add_action ( 'save_post'            , array( $this, 'czr_fn_refresh_thumbnail') , 10, 2);

      //Load the editor-style specific (post formats and RTL), the user style.css, the active skin
      //add user defined fonts in the editor style (@see the query args add_editor_style below)
      //The hook used to be after_setup_theme, but, don't know from whic WP version, is_rtl() always returns false at that stage.
      add_action( 'init'                  , array( $this, 'czr_fn_add_editor_style') );

      add_filter( 'tiny_mce_before_init'  , array( $this, 'czr_fn_user_defined_tinymce_css') );


      //refresh the terms array (categories/tags pickers options) on term deletion
      add_action ( 'delete_term'          , array( $this, 'czr_fn_refresh_terms_pickers_options_cb'), 10, 3 );

      add_action( 'admin_footer'                  , array( $this , 'czr_fn_write_ajax_dismis_script' ) );

      //UPDATE NOTICE
      add_action( 'admin_notices'         , array( $this, 'czr_fn_may_be_display_update_notice') );
      //always add the ajax action
      add_action( 'wp_ajax_dismiss_customizr_update_notice'    , array( $this , 'czr_fn_dismiss_update_notice_action' ) );

      // custom call to dismiss wp pointer
      add_action( 'wp_ajax_custom_wp_dismiss_pointer'    , array( $this , 'czr_fn_custom_wp_dismiss_pointer_action' ) );


      /* beautify admin notice text using some defaults the_content filter callbacks */
      foreach ( array( 'wptexturize', 'convert_smilies', 'wpautop') as $callback ) {
        add_filter( 'czr_update_notice', $callback );
      }
    }




    /**
   * hook : 'admin_init'
   * enqueue additional styling for admin screens
   * @package Customizr
   * @since Customizr 3.0.4
   */
    function czr_fn_admin_style() {
      wp_enqueue_style(
        'tc-admincss',
        sprintf('%1$sback/css/tc_admin%2$s.css' ,
          CZR_BASE_URL . CZR_ASSETS_PREFIX,
          ( defined('WP_DEBUG') && true === WP_DEBUG ) ? '' : '.min'
        ),
        array(),
        ( defined('WP_DEBUG') && true === WP_DEBUG ) ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER
      );
    }


    /*
    * @return void
    * updates the tc-thumb-fld post meta with the relevant thumb id and type
    * @package Customizr
    * @since Customizr 3.3.2
    */
    function czr_fn_refresh_thumbnail( $post_id, $post ) {
      // If this is just a revision, don't send the email.
      if ( wp_is_post_revision( $post_id ) || ( !empty($post) && 'auto-draft' == $post->post_status ) )
        return;

      //if czr4
      if ( czr_fn_is_ms() ) {

        if ( function_exists( 'czr_fn_set_thumb_info' ) )
          czr_fn_set_thumb_info( $post_id );

      }
      else {

        if ( !class_exists( 'CZR_post_thumbnails' ) || !is_object(CZR_post_thumbnails::$instance) ) {
          CZR___::$instance->czr_fn_req_once( 'inc/czr-front-ccat.php' );
          new CZR_post_thumbnails();
        }

        CZR_post_thumbnails::$instance->czr_fn_set_thumb_info( $post_id );

      }

    }



    /*
    * hook : 'delete_term'
    * @return void
    * updates the term pickers related options
    * @package Customizr
    * @since Customizr 3.4.10
    */
    function czr_fn_refresh_terms_pickers_options_cb( $term, $tt_id, $taxonomy ) {
      switch ( $taxonomy ) {

        //delete categories based options
        case 'category':
          $this->czr_fn_refresh_term_picker_options( $term, $option_name = 'tc_blog_restrict_by_cat' );
          break;
      }
    }


    function czr_fn_refresh_term_picker_options( $term, $option_name, $option_group = null ) {
       // czr_fn_get_opt and czr_fn_set_option in core/utils/ class-fire-utils_option
       //home/blog posts category picker
       $_option = czr_fn_opt( $option_name, $option_group, $use_default = false );
       if ( is_array( $_option ) && !empty( $_option ) && in_array( $term, $_option ) )
         //update the option
         czr_fn_set_option( $option_name, array_diff( $_option, (array)$term ) );

       //alternative, cycle throughout the cats and keep just the existent ones
       /*if ( is_array( $blog_cats ) && !empty( $blog_cats ) ) {
         //update the option
         czr_fn_set_option( 'tc_blog_restrict_by_cat', array_filter( $blog_cats, 'czr_fn_category_id_exists' ) );
       }*/
    }


    /*
    * hook : 'czr_add_custom_fonts_to_editor'
    * @return css string
    *
    * @package Customizr
    * @since Customizr 3.2.10
    */
    function czr_fn_maybe_add_gfonts_to_editor() {
      $_font_pair         = esc_attr( czr_fn_opt('tc_fonts') );
      $_all_font_pairs    = CZR___::$instance->font_pairs;
      if ( false === strpos($_font_pair,'_g_') )
        return;
      //Commas in a URL need to be encoded before the string can be passed to add_editor_style.
      //czr_fn_get_font defined in core/utils/class-fire-utils
      return array(
        str_replace(
          ',',
          '%2C',
          sprintf( '//fonts.googleapis.com/css?family=%s', czr_fn_get_font( 'single' , $_font_pair ) )
        )
      );
    }




    /**
   * Extract changelog of latest version from readme.txt file
   * @package Customizr
   * @since Customizr 3.0.5
   */
    function czr_fn_extract_changelog() {

      if( !file_exists(CZR_BASE."readme.txt") ) {
        return;
      }
      if( !is_readable(CZR_BASE."readme.txt") ) {
        echo '<p>The changelog in readme.txt is not readable.</p>';
        return;
      }

      $stylelines = explode("\n", implode('', file(CZR_BASE."readme.txt")));
      $read = false;
      $i = 0;

      foreach ($stylelines as $line) {
        //echo 'i = '.$i.'|read = '.$read.'pos = '.strpos($line, '= ').'|line :'.$line.'<br/>';
        //we stop reading if we reach the next version change
        if ($i == 1 && strpos($line, '= ') === 0 ) {
          $read = false;
          $i = 0;
        }
        //we write the line if between current and previous version
        if ($read) {
          echo $line.'<br/>';
        }
        //we skip all lines before the current version changelog
        if ($line != strpos($line, '= '.CUSTOMIZR_VER)) {
          if ($i == 0) {
            $read = false;
          }
        }
        //we begin to read after current version title
        else {
          $read = true;
          $i = 1;
        }
      }
    }


    /**
    * Customizr styles the visual editor to resemble the theme style,
    * Loads the editor-style specific (post formats and RTL), the active skin, the user style.css, the user_defined fonts
    * @package Customizr
    * @since Customizr 3.2.11
    *
    */
    function czr_fn_add_editor_style() {
      //array_filter to remove empty array items is not needed as wp function get_editor_stylesheets() (since WP 4.0)
      //will do that for us

      //we need only the relative path, otherwise get_editor_stylesheets() will treat this as external CSS
      //which means:
      //a) child-themes cannot override it
      //b) no check on the file existence will be made (producing the rtl error, for instance : https://github.com/presscustomizr/customizr/issues/926)

      //as of v4.0.10 the editor-style.css is the classic editor style for the Customizr classic style
      //4.1.23 block editor style introduced for the Customizr modern style only

      //as of 4.1.38 block editor style introduced for the Customizr modern style too
      $_style_suffix = CZR_DEBUG_MODE || CZR_DEV_MODE ? '.css' : '.min.css' ;
      $_stylesheets = czr_fn_is_ms() ? array( CZR_ASSETS_PREFIX . 'back/css/block-editor-style' . $_style_suffix ) : array( CZR_ASSETS_PREFIX . 'back/css/editor-style' . $_style_suffix, CZR_ASSETS_PREFIX . 'back/css/block-editor-style-cs' . $_style_suffix );

      $_stylesheets[] = 'style.css';
      if ( !czr_fn_is_ms() ) {
        $_stylesheets[] = 'inc/assets/css/' . esc_attr( czr_fn_opt( 'tc_skin' ) );
      }

      if ( apply_filters( 'czr_add_custom_fonts_to_editor' , false != $this->czr_fn_maybe_add_gfonts_to_editor() ) )
        $_stylesheets = array_merge( $_stylesheets , $this->czr_fn_maybe_add_gfonts_to_editor() );
      add_editor_style( $_stylesheets );

    }




    /**
    * Extend TinyMCE config with a setup function.
    * See http://www.tinymce.com/wiki.php/API3:event.tinymce.Editor.onInit
    * http://wordpress.stackexchange.com/questions/120831/how-to-add-custom-css-theme-option-to-tinymce
    * @package Customizr
    * @since Customizr 3.2.11
    *
    */
    function czr_fn_user_defined_tinymce_css( $init ) {

      if ( !apply_filters( 'czr_add_custom_fonts_to_editor' , true ) )
        return $init;

      if ( 'tinymce' != wp_default_editor() )
        return $init;

      $_css = '';
      //maybe add rtl class
      $_mce_body_context = is_rtl() ? 'mce-content-body.rtl' : 'mce-content-body';

      //if modern
      if ( czr_fn_is_ms() ) {
        //some plugins fire tiny mce editor in the customizer
        //in this case, the CZR_resources_fonts class has to be loaded
        if ( !class_exists('CZR_resources_fonts') || !is_object(CZR_resources_fonts::$instance) )
          CZR()->czr_fn_load( array('fire' => array( array('core' , 'resources_fonts') ) ), true );

        if ( class_exists('CZR_resources_fonts') && is_object(CZR_resources_fonts::$instance) ) {
          //fonts
          $_css  .= CZR_resources_fonts::$instance->czr_fn_write_fonts_inline_css( '', $_mce_body_context );
        }

        //skin
        //some plugins fire tiny mce editor in the customizer
        //in this case, the CZR_resources_styles class has to be loaded
        if ( !class_exists('CZR_resources_styles') || !is_object(CZR_resources_styles::$instance) )
          CZR()->czr_fn_load( array('fire' => array( array('core' , 'resources_styles') ) ), true );

        if ( class_exists('CZR_resources_styles') && is_object(CZR_resources_styles::$instance) ) {

          //dynamic skin
          $_css  .= CZR_resources_styles::$instance->czr_fn_maybe_write_skin_inline_css( '' );

        }

      }
      //classic
      else {

        //some plugins fire tiny mce editor in the customizer
        //in this case, the CZR_resource class has to be loaded
        if ( !class_exists('CZR_resources') || !is_object(CZR_resources::$instance) ) {
          CZR___::$instance->czr_fn_req_once( 'inc/czr-init-ccat.php' );
          new CZR_resources();
        }


        //fonts
        $_css = CZR_resources::$instance->czr_fn_write_fonts_inline_css( '', $_mce_body_context );

      }

      if ( !empty($_css) )
        $init['content_style'] = trim(preg_replace('/\s+/', ' ', $_css ) );

      return $init;

    }



    /**
    * hook : admin_footer
    */
    function czr_fn_write_ajax_dismis_script() {
      ?>
      <script id="tc-dismiss-update-notice">
        ( function($){
          var _ajax_action = function( $_el ) {
              var AjaxUrl = "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                  _query  = {
                      action  : 'dismiss_customizr_update_notice',
                      dismissUpdateNoticeNonce :  "<?php echo wp_create_nonce( 'dismiss-update-notice-nonce' ); ?>"
                  },
                  $ = jQuery,
                  request = $.post( AjaxUrl, _query );

              request.fail( function ( response ) {
                //console.log('response when failed : ', response);
              });
              request.done( function( response ) {
                //console.log('RESPONSE DONE', $_el, response);
                // Check if the user is logged out.
                if ( '0' === response )
                  return;
                // Check for cheaters.
                if ( '-1' === response )
                  return;
              });
          };//end of fn

          //on load
          $( function($) {
            $('.tc-dismiss-update-notice').on('click', function( e ) {
              e.preventDefault();
              $(this).closest('.czr-update-notice').slideToggle('fast');
              _ajax_action( $(this) );
            });
          });

        } )( jQuery );


      </script>
      <?php
    }


    /**********************************************************************************
    * UPDATE NOTICE
    * User gets notified when the version stored in the db option 'last_update_notice'
    * is < current version of the theme (CUSTOMIZR_VER)
    * User can dismiss the notice and the option get updated by ajax to the current version
    * The notice will be displayed a maximum of 5 times and will be automatically dismissed until the next update.
    * => users won't be notified again until the next update.
    **********************************************************************************/
    /**
    * hook : admin_notices
    */
    function czr_fn_may_be_display_update_notice() {
      //don't display update notification for a list of versions
      //typically useful when several versions are released in a short time interval
      //to avoid hammering the wp admin dashboard with a new admin notice each time
      if ( ( defined('DISPLAY_UPDATE_NOTIFICATION') && !DISPLAY_UPDATE_NOTIFICATION ) || ( defined('DISPLAY_PRO_UPDATE_NOTIFICATION') && !DISPLAY_PRO_UPDATE_NOTIFICATION ) )
        return;
      $screen = get_current_screen();
      if ( is_object($screen) && 'appearance_page_welcome' === $screen-> id )
        return;

      $opt_name                   = CZR_IS_PRO ? 'last_update_notice_pro' : 'last_update_notice';
      $last_update_notice_values  = czr_fn_opt($opt_name);
      $show_new_notice = false;
      $display_ct = 10;

      if ( !$last_update_notice_values || !is_array($last_update_notice_values) ) {
        //first time user of the theme, the option does not exist
        // 1) initialize it => set it to the current Customizr version, displayed 0 times.
        // 2) update in db
        $last_update_notice_values = array( "version" => CUSTOMIZR_VER, "display_count" => 0 );
        czr_fn_set_option( $opt_name, $last_update_notice_values );
        //already user of the theme ?
        if ( czr_fn_user_started_before_version( CUSTOMIZR_VER, CUSTOMIZR_VER ) )
          $show_new_notice = true;
      }

      $_db_version          = $last_update_notice_values["version"];
      $_db_displayed_count  = $last_update_notice_values["display_count"];

      // user who just upgraded the theme will be notified until he clicks on the dismiss link
      // when clicking on the dismiss link OR when the notice has been displayed n times.
      // - version will be set to CUSTOMIZR_VER
      // - display_count reset to 0
      if ( version_compare( CUSTOMIZR_VER, $_db_version , '>' ) ) {
          //CASE 1 : displayed less than n times
          if ( $_db_displayed_count < $display_ct ) {
              $show_new_notice = true;
              //increments the counter
              (int) $_db_displayed_count++;
              $last_update_notice_values["display_count"] = $_db_displayed_count;
              //updates the option val with the new count
              czr_fn_set_option( $opt_name, $last_update_notice_values );
          }
          //CASE 2 : displayed n times => automatic dismiss
          else {
              //reset option value with new version and counter to 0
              $new_val  = array( "version" => CUSTOMIZR_VER, "display_count" => 0 );
              czr_fn_set_option( $opt_name, $new_val );
          }//end else
      }//end if
      $show_new_notice = ( defined('CZR_DEV') && CZR_DEV ) || $show_new_notice;
      if ( !$show_new_notice )
        return;

      // prefixed CZR_Plugin_Activation because of the possible issue : https://github.com/presscustomizr/customizr/issues/1603
      if ( !czr_fn_is_plugin_active('nimble-builder/nimble-builder.php') && class_exists('CZR_Plugin_Activation') && !CZR_Plugin_Activation::get_instance()->czr_fn_is_notice_dismissed() )
        return;

      ob_start();
        ?>
        <div class="notice notice-info czr-update-notice" style="position:relative">
          <?php
            echo apply_filters(
              'czr_update_notice',
              sprintf('<h3>➡️ %1$s %2$s %3$s %4$s. <strong><a href="%5$s" title="%6$s">%6$s %7$s</a></strong></h3>',
                __( "You have recently updated to", "customizr"),
                CZR_IS_PRO ? 'Customizr Pro' : 'Customizr',
                __( "version", "customizr"),
                CUSTOMIZR_VER,
                admin_url() .'themes.php?page=welcome.php',
                __( "Make sure to read the changelog" , "customizr" ),
                is_rtl() ? '&laquo;' : '&raquo;'
              )
            );
          ?>
          <p style="text-align:right;position: absolute;font-size: 1.1em;<?php echo is_rtl()? 'left' : 'right';?>: 7px;bottom: -5px;">
            <?php printf('<a href="#" title="%1$s" class="tc-dismiss-update-notice"> ( %1$s <strong>X</strong> ) </a>',
                __('close' , 'customizr')
              );
            ?>
          </p>
        </div>
        <?php
      $_html = ob_get_contents();
      if ($_html) ob_end_clean();
      echo $_html;
    }


    /**
    * hook : wp_ajax_dismiss_customizr_update_notice
    * => sets the last_update_notice to the current Customizr version when user click on dismiss notice link
    */
    function czr_fn_dismiss_update_notice_action() {
      check_ajax_referer( 'dismiss-update-notice-nonce', 'dismissUpdateNoticeNonce' );
      $opt_name = CZR_IS_PRO ? 'last_update_notice_pro' : 'last_update_notice';
      //reset option value with new version and counter to 0
      $new_val  = array( "version" => CUSTOMIZR_VER, "display_count" => 0 );
      czr_fn_set_option( $opt_name, $new_val );
      wp_die();
    }

    /**
     * Handles custom dismissing a WordPress pointer via AJAX.
     */
    function czr_fn_custom_wp_dismiss_pointer_action() {

      if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'custom_wp_dismiss_pointer' ) ) {
        // Nonce verification failed
        wp_send_json_error( 'Invalid nonce.' );
      }
      $pointer = $_POST['pointer'];

      if ( sanitize_key( $pointer ) != $pointer ) {
        wp_die( 0 );
      }

      //  check_ajax_referer( 'dismiss-pointer_' . $pointer );

      $dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );

      if ( in_array( $pointer, $dismissed, true ) ) {
        wp_die( 0 );
      }

      $dismissed[] = $pointer;
      $dismissed   = implode( ',', $dismissed );

      update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissed );
      wp_die( 1 );
    }


  }//end of class
endif;

?><?php
/**
* Init admin page actions : Welcome, help page
*
*/
if ( !class_exists( 'CZR_admin_page' ) ) :
  class CZR_admin_page {
    static $instance;
    public $support_url;

    function __construct () {
      self::$instance =& $this;
      //add welcome page in menu
      add_action( 'admin_menu'             , array( $this , 'czr_fn_add_welcome_page' ));
      //build the support url
      $this->support_url = CZR_IS_PRO ? esc_url( sprintf('%ssupport' , CZR_WEBSITE ) ) : esc_url('wordpress.org/support/theme/customizr');
      //fix #wpfooter absolute positioning in the welcome and about pages
      add_action( 'admin_print_styles'     , array( $this, 'czr_fn_fix_wp_footer_link_style') );
    }



    /**
   * Extract changelog of latest version from readme.txt file
   *
   * @package Customizr
   * @since Customizr 3.0.5
   */
    function czr_fn_print_changelog() {
      if( !file_exists( CZR_BASE . "readme.txt" ) ) {
        return;
      }
      if( !is_readable( CZR_BASE . "readme.txt" ) ) {
        echo '<p>The changelog in readme.txt is not readable.</p>';
        return;
      }

      $html = '';
      $stylelines = explode("\n", implode('', file( CZR_BASE . "readme.txt" ) ) );
      $read = false;
      $is_title = false;
      
      foreach ($stylelines as $line) {
          $is_title = 0 === strpos($line, '= ');

          //we start reading after current version title
          if ( 0 === strpos($line, '= '. CUSTOMIZR_VER) ) {
            $read = true;
          }

          if ( !$read )
            continue;

          if ( $is_title ) {
            $html .= sprintf( '<strong>%1$s</strong><br/>', esc_attr( $line ) );
          } else {
            $html .= sprintf( '<i>%1$s</i><br/>', esc_attr( $line ) );
          }
      }
      ?>
      <div id="customizr-changelog" class="">
        <h3><?php printf( __( 'Changelog in version %1$s' , 'customizr' ) , CUSTOMIZR_VER ); ?></h3>
          <p><?php echo $html ?></p>
          <p><strong><?php printf('<a href="%1$s" title="%2$s" target="_blank" rel="noopener noreferrer">%2$s %3$s</a>',
                    CZR_WEBSITE . "category/customizr-releases/",
                    __( "Read the latest release notes" , "customizr" ),
                    is_rtl() ? '&laquo;' : '&raquo;'
          ); ?></strong></p>
      </div>
      <?php
    }















    /*
    * Inspired by Easy Digital Download plugin by Pippin Williamson
    * @since 3.2.1
    */
    function czr_fn_print_config_infos() {
      global $wpdb;
      $theme_data   = wp_get_theme();
      $theme        = $theme_data->Name . ' ' . $theme_data->Version;
      $parent_theme = $theme_data->Template;
      if ( !empty( $parent_theme ) ) {
        $parent_theme_data = wp_get_theme( $parent_theme );
        $parent_theme      = $parent_theme_data->Name . ' ' . $parent_theme_data->Version;
      }
      ?>
<div class="tc-config-info">
<h3><?php _e( 'System Informations', 'customizr' ); ?></h3>
<h4 style="text-align: left"><?php _e( 'Please include the following informations when posting support requests' , 'customizr' ) ?></h4>
<textarea readonly="readonly" onclick="this.focus();this.select()" id="system-info-textarea" name="tc-sysinfo" title="<?php _e( 'To copy the system infos, click below then press Ctrl + C (PC) or Cmd + C (Mac).', 'customizr' ); ?>" style="width: 100%;min-height: 800px;font-family: Menlo,Monaco,monospace;background: 0 0;white-space: pre;overflow: auto;display:block;">
<?php do_action( '__system_config_before' ); ?>
# SITE_URL:                 <?php echo esc_url( site_url() ) . "\n"; ?>
# HOME_URL:                 <?php echo esc_url( home_url() ) . "\n"; ?>
# IS MULTISITE :            <?php echo is_multisite() ? 'Yes' . "\n" : 'No' . "\n" ?>

# ACTIVE THEME :            <?php echo $theme . "\n"; ?>
<?php if ( $parent_theme !== $theme ) : ?>
# PARENT THEME :            <?php echo $parent_theme . "\n"; ?>
<?php endif; ?>
# WP VERSION :              <?php echo get_bloginfo( 'version' ) . "\n"; ?>
# PERMALINK STRUCTURE :     <?php echo get_option( 'permalink_structure' ) . "\n"; ?>

# ACTIVE PLUGINS :
<?php
$plugins = get_plugins();
$active_plugins = get_option( 'active_plugins', array() );

foreach ( $plugins as $plugin_path => $plugin ) {
  // If the plugin isn't active, don't show it.
  if ( !in_array( $plugin_path, $active_plugins ) )
    continue;

  echo $plugin['Name'] . ': ' . $plugin['Version'] ."\n";
}

if ( is_multisite() ) :
?>
#  NETWORK ACTIVE PLUGINS:
<?php
$plugins = wp_get_active_network_plugins();
$active_plugins = get_site_option( 'active_sitewide_plugins', array() );

foreach ( $plugins as $plugin_path ) {
  $plugin_base = plugin_basename( $plugin_path );

  // If the plugin isn't active, don't show it.
  if ( !array_key_exists( $plugin_base, $active_plugins ) )
    continue;

  $plugin = get_plugin_data( $plugin_path );

  echo $plugin['Name'] . ' :' . $plugin['Version'] ."\n";
}
endif;
//GET MYSQL VERSION
global $wpdb;
$mysql_ver =  ( !empty( $wpdb->use_mysqli ) && $wpdb->use_mysqli ) ? @mysqli_get_server_info( $wpdb->dbh ) : '';
?>

PHP Version:              <?php echo PHP_VERSION . "\n"; ?>
MySQL Version:            <?php echo $mysql_ver . "\n"; ?>
Web Server Info:          <?php echo $_SERVER['SERVER_SOFTWARE'] . "\n"; ?>

WordPress Memory Limit:   <?php echo ( $this->czr_fn_let_to_num( WP_MEMORY_LIMIT )/( 1024 ) )."MB"; ?><?php echo "\n"; ?>
PHP Memory Limit:         <?php echo ini_get( 'memory_limit' ) . "\n"; ?>
PHP Upload Max Size:      <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Post Max Size:        <?php echo ini_get( 'post_max_size' ) . "\n"; ?>
PHP Upload Max Filesize:  <?php echo ini_get( 'upload_max_filesize' ) . "\n"; ?>
PHP Time Limit:           <?php echo ini_get( 'max_execution_time' ) . "\n"; ?>
PHP Max Input Vars:       <?php echo ini_get( 'max_input_vars' ) . "\n"; ?>
PHP Arg Separator:        <?php echo ini_get( 'arg_separator.output' ) . "\n"; ?>
PHP Allow URL File Open:  <?php echo ini_get( 'allow_url_fopen' ) ? "Yes" : "No\n"; ?>

WP_DEBUG:                 <?php echo defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' . "\n" : 'Disabled' . "\n" : 'Not set' . "\n" ?>

Show On Front:            <?php echo get_option( 'show_on_front' ) . "\n" ?>
Page On Front:            <?php $id = get_option( 'page_on_front' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>
Page For Posts:           <?php $id = get_option( 'page_for_posts' ); echo get_the_title( $id ) . ' (#' . $id . ')' . "\n" ?>
<?php do_action( '__system_config_after' ); ?>
</textarea>
</div>
      <?php
      }//end of function


      /**
       * TC Let To Num
       *
       * Does Size Conversions
       *
       *
       * @since 3.2.2
       */
      function czr_fn_let_to_num( $v ) {
        $l   = substr( $v, -1 );
        $ret = substr( $v, 0, -1 );

        switch ( strtoupper( $l ) ) {
          case 'P': // fall-through
          case 'T': // fall-through
          case 'G': // fall-through
          case 'M': // fall-through
          case 'K': // fall-through
            $ret *= 1024;
            break;
          default:
            break;
        }

        return $ret;
      }

    /**
    * hook : admin_print_styles
    * fix the absolute positioning of the wp footer admin link in the welcome pages
    * @return void
    */
    function czr_fn_fix_wp_footer_link_style() {
      $screen = get_current_screen();
      if ( !is_object($screen) )
        return;
      if ( 'appearance_page_welcome' != $screen-> id )
        return;
      ?>
        <style id="tc-fix-wp-footer-position">
          .wp-admin #wpfooter {bottom: inherit;}
        </style>
      <?php
    }



   /**
   * Add fallback admin page.
   * @package Customizr
   * @since Customizr 1.1
   */
    function czr_fn_add_welcome_page() {
      $_name = __( 'About Customizr' , 'customizr' );
      $_name = CZR_IS_PRO ? sprintf( '%s Pro', $_name ) : $_name;

      $theme_page = add_theme_page(
          $_name,   // Name of page
          $_name,   // Label in menu
          'edit_theme_options' ,          // Capability required
          'welcome.php' ,             // Menu slug, used to uniquely identify the page
          array( $this , 'czr_fn_welcome_panel' )         //function to be called to output the content of this page
      );
  }



    /**
   * Render welcome admin page.
   * @package Customizr
   * @since Customizr 3.0.4
   */
    function czr_fn_welcome_panel() {
      $_theme_name    = CZR_IS_PRO ? 'Customizr Pro' : 'Customizr';

      ?>
      <div class="customizr-admin-panel">
        <div class="about-text tc-welcome">
          <?php
            $title = sprintf( '<h1 class="czr-welcome-title">%1$s %2$s %3$s :)</h1>',
              __( "Thank you for using", "customizr" ),
              $_theme_name,
              CUSTOMIZR_VER
            );
            echo convert_smilies( $title );
          ?>

          <?php
            if ( !CZR_IS_PRO ) {
              printf( '<h4>%1$s ❤️.</h4><h4>%2$s</h4><h4>%3$s 🙏</h4><h3 style="font-weight:bold">%4$s</h3>',
                sprintf( __( "If you enjoy using the Customizr theme for your website, you will love %s", "customizr"),
                  sprintf( '<a style="color:#d87f00" href="%1$s" title="%2$s" target="_blank" rel="noopener noreferrer">%2$s</a>', 'https://presscustomizr.com/customizr-pro/', __("Customizr Pro", "customizr") )
                ),
                __("With Customizr Pro, you get premium features like infinite scrolling, footer and header customization, font customizer and many more. In addition, our premium support will be there to help you resolve any issue you may have with the theme. When installing Customizr Pro, all your previous options used in Customizr free are kept.", 'customizr'),
                __('And of course your support allows us to keep the theme at the highest level for your website. Thank you!', 'customizr'),
                'Limited offer : get 25% off with code HELLO2022.' . ' <a class="tc-pro-link-in-dashboard" href="https://presscustomizr.com/customizr-pro/" rel="noopener noreferrer" title="Go Pro" target="_blank">Go Pro</a> <span style="color: #f07829;font-size: 26px;" class="dashicons dashicons-external"></span>'
              );
            }
          ?>
        </div>
          
        <?php echo $this->czr_fn_print_changelog(); ?>

        
        <div class="czr-col-50 first-col">
          <h3 style="font-size:1.3em;"><?php _e( 'Knowledge base','customizr' ); ?></h3>
          <p><?php _e( "We have prepared a complete online documentation of the theme.",'customizr' ) ?></br>
          <a class="button-primary review-customizr" href="<?php echo 'https://docs.presscustomizr.com/' ?>" target="_blank"><?php _e('Customizr Documentation','customizr'); ?></a></p>
          <!-- Place this tag where you want the widget to render. -->
        </div>
        
        <div class="czr-col-50">
          <h3 style="font-size:1.3em;"><?php _e( 'Share your feedback','customizr' ); ?></h3>
          <p><?php _e( 'If you are happy with the theme, say it on wordpress.org and give Customizr a nice review!','customizr' ) ?></br>
          <a class="button-primary review-customizr" href="<?php echo esc_url('wordpress.org/support/view/theme-reviews/customizr') ?>" target="_blank"><?php _e('Share a review','customizr'); ?></a></p>
        </div>

      <?php echo $this->czr_fn_print_config_infos() ?>
    </div><!-- //#customizr-admin-panel -->
    <?php
  }



  }//end of class
endif;

?><?php
/**
* Posts, pages and attachment actions and filters
*
*/
if ( !class_exists( 'CZR_meta_boxes' ) ) :
   class CZR_meta_boxes {
      static $instance;

      public $mixed_meta_boxes_map;
      public $post_meta_boxes_map;

      public $_minify_resources;
      public $_resouces_version;


      function __construct () {
         self::$instance =& $this;

         $this->_resouces_version  = CZR_DEBUG_MODE || CZR_DEV_MODE ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER;

         $this->_minify_resources  = CZR_DEBUG_MODE || CZR_DEV_MODE ? false : true ;


         //mixed ( layout, slider ) displayed in various types of posts
         add_action( 'add_meta_boxes'                     , array( $this , 'czr_fn_mixed_meta_boxes' )) ;

         //post ( post formats ) displayed only in post types
         add_action( 'add_meta_boxes_post'                , array( $this , 'czr_fn_post_formats_meta_boxes' )) ;

         //attachment
         add_action( 'add_meta_boxes_attachment'          , array( $this , 'czr_fn_attachment_meta_box' ));


         add_action( '__post_slider_infos'                , array( $this , 'czr_fn_get_post_slider_infos' ));

         add_action( 'save_post'                          , array( $this , 'czr_fn_post_fields_save' ) );

         add_action( '__attachment_slider_infos'          , array( $this , 'czr_fn_get_attachment_slider_infos' ));

         add_action( 'edit_attachment'                    , array( $this , 'czr_fn_slide_save' ));
         add_action( 'edit_attachment'                    , array( $this , 'czr_fn_post_fields_save' ));

         add_action( '__show_slides'                      , array( $this , 'czr_fn_show_slides' ), 10, 2);

         add_action( 'wp_ajax_slider_action'              , array( $this , 'czr_fn_slider_cb' ));

         //enqueue slider scripts when needed (will be in the footer)
         //czr_slider_metabox_added is fired when
         //a) the slider attachment metabox is printed: czr_fn_attachment_meta_box
         //b) the slider post metabox is printed: czr_fn_post_slider_box
         add_action( 'czr_slider_metabox_added'            , array( $this,  'czr_fn_slider_admin_scripts') );

         //enqueue post format script
         add_action( 'czr_post_formats_metabox_added'      , array( $this , 'czr_fn_post_formats_admin_scripts' ) );


        /**
         * checks if WP version strictly < 3.5
         * before 3.5, attachments were not managed as posts. But two filter hooks can are very useful
         * @package Customizr
         * @since Customizr 2.0
         */
        global $wp_version;
        if (version_compare( $wp_version, '3.5' , '<' ) ) {
           add_filter( 'attachment_fields_to_edit'          , array( $this , 'czr_fn_attachment_filter' ), 11, 2 );
           add_filter( 'attachment_fields_to_save'          , array( $this , 'czr_fn_attachment_save_filter' ), 11, 2 );
         }

      }//end of __construct


      function czr_fn_get_mixed_meta_boxes_map( $_cache = true ) {
         $_meta_boxes_map = $this->mixed_meta_boxes_map;

         if ( !isset($this->mixed_meta_boxes_map) ) {

            $_meta_boxes_map = array (
               //metabox      => disallowed screens
               'layout_section' => array(),
               //The slider section (slider in posts/pages) metabox MUST NOT be added in attachments
               'slider_section' => array( 'attachment' )
            );

            if ( $_cache )
               $this->mixed_meta_boxes_map = $_meta_boxes_map;

         }

         return apply_filters( 'czr_mixed_meta_boxes_map', $_meta_boxes_map );
      }


      function czr_fn_get_post_meta_boxes_map( $_cache = true ) {
         $_meta_boxes_map = $this->post_meta_boxes_map;

         if ( !isset($this->post_meta_boxes_map) ) {

            $_meta_boxes_map = array (
               //Post formats
               'audio_section',
               'video_section',
               'quote_section',
               'link_section'
            );

            if ( $_cache )
               $this->post_meta_boxes_map = $_meta_boxes_map;

         }

         return apply_filters( 'czr_meta_boxes_map', $_meta_boxes_map );
      }



       /*
       ----------------------------------------------------------------
       -------- DEFINE POST/PAGE LAYOUT AND SLIDER META BOXES ---------
       ----------------------------------------------------------------
       */
      function czr_add_metabox( $meta_box_key, $screen ) {

         if ( !method_exists( $this , "czr_fn_{$meta_box_key}_metabox" ) )
            return;

         call_user_func_array( 'add_meta_box',
            $this->czr_fn_build_metabox_arguments (
               "{$meta_box_key}id",
               call_user_func( array( $this, "czr_fn_{$meta_box_key}_metabox" ), $screen )
            )
         );

      }

    /**
     * Adds layout and slider metaboxes to pages and posts
     * hook : add_meta_boxes
     * @package Customizr
     * @since Customizr 1.0
     */
      function czr_fn_mixed_meta_boxes( $id ) {//id, title, callback, post_type, context, priority, callback_args
         /***
          Determines which screens we display the box
         **/
         //1 - retrieves the custom post types
         $args                = array(
            //we want our metaboxes added only to those custom post types that can be seen on front
            //the parameter 'publicly_queryable' should ensure this.
            //Example:
            // - In WooCommerce product post type our metaboxes are visibile while they're not in WooCommerce orders/coupons ...
            //   that cannot be seen in front.
            // - They're visible in Tribe Events Calendar's event post type
            // - They're not visible in ACF(-pro) screens
            // - They're not visbile in Ultime Responsive image slider post type
            'publicly_queryable' => true,
            '_builtin'           => false
         );

         $custom_post_types    = apply_filters( 'czr_post_metaboxes_cpt', get_post_types($args) );

         //2 - Merging with the builtin post types, pages and posts
         $builtin_post_types   = array(
            'page' => 'page',
            'post' => 'post',
            'attachment' => 'attachment'
         );

         $screens                   = array_merge( $custom_post_types, $builtin_post_types );

         $mixed_meta_boxes          = $this->czr_fn_get_mixed_meta_boxes_map();


         //3- Adding the meta-boxes to those screens
         foreach ( $screens as $key => $screen) {
            foreach ( $mixed_meta_boxes as $meta_box_key => $disallowed_screens_array ) {
               if ( in_array( $screen, $disallowed_screens_array ) ) {
                  continue;
               }
               $this->czr_add_metabox( $meta_box_key, $screen );
               $_metabox_added       = true;
            }//end foreach

         }//end foreach

      }

      //hook : add_meta_boxes_post
      function czr_fn_post_formats_meta_boxes( $post ) {
         //if not czr4 return
         if ( !( defined( 'CZR_IS_MODERN_STYLE' ) && CZR_IS_MODERN_STYLE ) )
            return;

         $post_meta_boxes          = $this->czr_fn_get_post_meta_boxes_map();

         $_metabox_added           = false;

         foreach ( $post_meta_boxes as $meta_box_key ) {
            $this->czr_add_metabox( $meta_box_key, 'post' );
            $_metabox_added        = true;
         }//end foreach

         if ( $_metabox_added )
            do_action( 'czr_post_formats_metabox_added', $post );

      }




      //helper
      function czr_fn_build_metabox_arguments( $id, $args ) {
         //order matters!
         //'cause we use call_user_func_array to pass args with a certain order to add_metabox
         $defaults = array(
            'id'            => $id,
            'title'         => '',
            'callback'       => null,
            'screen'         => null,
            'context'        => 'advanced',
            'priority'       => 'high',
            'callback_args'  => null,
         );

         $args = wp_parse_args( $args, $defaults );

         //Filtering
         $args[ 'screen'  ]    = apply_filters( "czr_fn_{$id}_metabox_screen", apply_filters( 'czr_fn_metaboxes_screen', $args['screen'], $args['id'] ), $args[ 'screen' ] );
         $args[ 'context' ]    = apply_filters( "czr_fn_{$id}_metabox_context", apply_filters( 'czr_fn_metaboxes_context', $args['context'], $args['id'] ), $args[ 'context' ] );
         $args[ 'priority'  ]  = apply_filters( "czr_fn_{$id}_metabox_priority", apply_filters( 'czr_fn_metaboxes_priority', $args['priority'], $args['id'] ), $args[ 'priority' ] );

         return $args;
      }





      function czr_fn_layout_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Layout Options' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_layout_box' ),
            'screen'   => $screen,
            'context'  => in_array( $screen, array( 'page', 'post', 'attachment' ) ) ? 'side' : 'normal',//displays meta box below editor for custom post types
            'priority' => 'high',
         );

      }


      function czr_fn_slider_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Slider Options' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_slider_box' ),
            'screen'   => $screen,
            'context'  => 'normal',//displays meta box below editor for custom post types
            'priority' => 'high'
         );

      }

      function czr_fn_link_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Format: link' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_format_link_box' ),
            'screen'   => 'post',
            'context'  => 'normal',//displays meta box below editor for custom post types
            'priority' => 'high'
         );

      }

      function czr_fn_quote_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Format: quote' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_format_quote_box' ),
            'screen'   => 'post',
            'context'  => 'normal',//displays meta box below editor for custom post types
            'priority' => 'high'
         );

      }

      function czr_fn_video_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Format: video' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_format_video_box' ),
            'screen'   => 'post',
            'context'  => 'normal',//displays meta box below editor for custom post types
            'priority' => 'high'
         );

      }

      function czr_fn_audio_section_metabox( $screen ) {

         return array(
            'title'    => __( 'Format: audio' , 'customizr' ),
            'callback' => array( $this , 'czr_fn_post_format_audio_box' ),
            'screen'   => 'post',
            'context'  => 'normal',//displays meta box below editor for custom post types
            'priority' => 'high'
         );

      }



      //Build metabox html
      function czr_fn_post_format_link_box( $post, $args ) {

         // Use nonce for verification
         wp_nonce_field( plugin_basename( __FILE__ ), 'format_link_noncename' );

         // The actual field for data entry
         $link       = get_post_meta( $post->ID, $key = 'czr_link_meta' , $single = true );

         $link_title = esc_attr( isset( $link['link_title'] ) ? $link['link_title'] : '' );
         $link_url   = esc_url( isset( $link['link_url'] ) ? $link['link_url'] : '' );


         CZR_meta_boxes::czr_fn_generic_input_view( array(
            'input_name'  => 'czr_link_title',
            'custom_args' => 'style="max-width:50%"',
            'title'       => array(

                            'title_text'  => __( 'Link title', 'customizr'),
                            'title_tag'   => 'h3',

            ),
            'content_before' => CZR_meta_boxes::czr_fn_title_view( array(
                                 'title_text'  => __( 'Enter the title', 'customizr'),
                                 'title_tag'   => 'h4',
                                 'echo'        => false,
                                 'boxed'       => false
                              )
            ),
            'input_value' => $link_title

         ));

         CZR_meta_boxes::czr_fn_generic_input_view( array(

            'input_name'  => 'czr_link_url',
            'input_type'  => 'url',
            'custom_args' => 'style="max-width:50%"',
            'title'       => array(
                                 'title_text'  => __( 'Link URL', 'customizr'),
                                 'title_tag'   => 'h3',
            ),

            'content_before' => CZR_meta_boxes::czr_fn_title_view( array(
                                 'title_text'  => __( 'Enter the URL', 'customizr'),
                                 'title_tag'   => 'h4',
                                 'echo'        => false,
                                 'boxed'       => false
                              )
            ),
           'input_value' => $link_url

         ));

      }

      function czr_fn_post_format_quote_box( $post, $args ) {

         // Use nonce for verification
         wp_nonce_field( plugin_basename( __FILE__ ), 'format_quote_noncename' );

         // The actual field for data entry
         $quote        = get_post_meta( $post->ID, $key = 'czr_quote_meta' , $single = true );

         $quote_text   = esc_attr( isset( $quote['quote_text'] ) ? $quote['quote_text'] : '' );
         $quote_author = esc_attr( isset( $quote['quote_author'] ) ? $quote['quote_author'] : '' );

         CZR_meta_boxes::czr_fn_textarea_view( array(

            'input_name'  =>  'czr_quote_text',
            'title'       =>  array(
                                 'title_text'  => __( 'Quote text', 'customizr'),
                                 'title_tag'   => 'h3',
            ),
            'custom_args'    => 'style="max-width:50%"',
            'content_before' =>  CZR_meta_boxes::czr_fn_title_view( array(
                                 'title_text'  => __( 'Enter the text', 'customizr'),
                                 'title_tag'   => 'h4',
                                 'echo'        => false,
                                 'boxed'       => false
                              )
            ),

            'input_value' => $quote_text

         ));

         CZR_meta_boxes::czr_fn_generic_input_view( array(

            'input_name'  =>  'czr_quote_author',
            'title'       =>  array(
                                 'title_text'  => __( 'Quote author', 'customizr'),
                                 'title_tag'   => 'h3',
            ),

            'custom_args' => 'style="max-width:50%"',
            'content_before' => CZR_meta_boxes::czr_fn_title_view( array(
                                 'title_text'  => __( 'Enter the author', 'customizr'),
                                 'title_tag'   => 'h4',
                                 'echo'        => false,
                                 'boxed'       => false
                              )
            ),

            'input_value' => $quote_author
         ));
      }


      function czr_fn_post_format_audio_box( $post, $args ) {

         // Use nonce for verification
         wp_nonce_field( plugin_basename( __FILE__ ), 'format_audio_noncename' );

         // The actual field for data entry
         $audio        = get_post_meta( $post->ID, $key = 'czr_audio_meta' , $single = true );

         $audio_url   = esc_url( isset( $audio['audio_url'] ) ? $audio['audio_url'] : '' );

         CZR_meta_boxes::czr_fn_generic_input_view( array(

            'input_name'  => 'czr_audio_url',
            'custom_args' => 'style="max-width:50%"',
            'title'       => array(
                                 'title_text'  => __( 'Audio url', 'customizr'),
                                 'title_tag'   => 'h3',
            ),
            'content_before' => CZR_meta_boxes::czr_fn_title_view( array(
                                    'title_text'  => __( 'Enter the audio url', 'customizr'),
                                    'title_tag'   => 'h4',
                                    'echo'        => false,
                                    'boxed'       => false
                              )
            ),
            'input_value' => $audio_url,
            'input_type'  => 'url'

         ));

      }



      function czr_fn_post_format_video_box( $post, $args ) {

         // Use nonce for verification
         wp_nonce_field( plugin_basename( __FILE__ ), 'format_video_noncename' );

         // The actual field for data entry
         $video        = get_post_meta( $post->ID, $key = 'czr_video_meta' , $single = true );

         $video_url   = esc_url( isset( $video['video_url'] ) ? $video['video_url'] : '' );

         CZR_meta_boxes::czr_fn_generic_input_view( array(

            'input_name'  => 'czr_video_url',
            'custom_args' => 'style="max-width:50%"',
            'title'       => array(
                                 'title_text'  => __( 'Video url', 'customizr'),
                                 'title_tag'   => 'h3',
            ),
            'content_before' => CZR_meta_boxes::czr_fn_title_view( array(
                                 'title_text'  => __( 'Enter the video url', 'customizr'),
                                 'title_tag'   => 'h4',
                                 'echo'        => false,
                                 'boxed'       => false
                              )
            ),
            'input_value' => $video_url,
            'input_type'  => 'url'

         ));

      }




      /**
       * Prints the box content
       * @package Customizr
       * @since Customizr 1.0
       */
      function czr_fn_post_layout_box( $post ) {
           // Use nonce for verification
           wp_nonce_field( plugin_basename( __FILE__ ), 'post_layout_noncename' );

           // The actual fields for data entry
           // Use get_post_meta to retrieve an existing value from the database and use the value for the form
           //Layout name setup
           $layout_id           = 'layout_field';

           $layout_value         = esc_attr(get_post_meta( $post->ID, $key = 'layout_key' , $single = true ));

           //Generates layouts select list array
           $layouts                    = array();
           $global_layout              = apply_filters( 'tc_global_layout' , CZR_init::$instance->global_layout );
           foreach ( $global_layout as $key => $value ) {
             $layouts[$key]            = call_user_func( '__' , $value['metabox'] , 'customizr' );
           }

           //by default we apply the global default layout
           $tc_sidebar_default_context_layout  = esc_attr( czr_fn_opt( 'page' == $post->post_type ? 'tc_sidebar_page_layout' : 'tc_sidebar_post_layout' ) );


           ?>
           <div class="meta-box-item-content">
             <?php if( $layout_value == null) : ?>
               <p><?php printf(__( 'Default %1$s layout is set to : %2$s' , 'customizr' ), 'page' == $post->post_type ? __( 'pages' , 'customizr' ):__( 'posts' , 'customizr' ), '<strong>'.$layouts[$tc_sidebar_default_context_layout].'</strong>' ) ?></p>
             <?php endif; ?>

                 <i><?php printf(__( 'You can define a specific layout for %1$s by using the pre-defined left and right sidebars. The default layouts can be defined in the WordPress customizer screen %2$s.<br />' , 'customizr' ),
                  $post->post_type == 'page' ? __( 'this page' , 'customizr' ):__( 'this post' , 'customizr' ),
                   '<a href="'.admin_url( 'customize.php' ).'" target="_blank">'.__( 'here' , 'customizr' ).'</a>'
                  ); ?>
                 </i>
                 <h4><?php printf(__( 'Select a specific layout for %1$s' , 'customizr' ),
                 $post->post_type == 'page' ? __( 'this page' , 'customizr' ):__( 'this post' , 'customizr' )); ?></h4>
                 <select name="<?php echo $layout_id; ?>" id="<?php echo $layout_id; ?>">
                 <?php //no layout selected ?>
                  <option value="" <?php selected( $layout_value, $current = null, $echo = true ) ?>> <?php printf(__( 'Default layout %1s' , 'customizr' ),
                        '( '.$layouts[$tc_sidebar_default_context_layout].' )'
                       );
                    ?></option>
                  <?php foreach( $layouts as $key => $l) : ?>
                    <option value="<?php echo $key; ?>" <?php selected( $layout_value, $current = $key, $echo = true ) ?>><?php echo $l; ?></option>
                  <?php endforeach; ?>
                 </select>

         </div>

         <?php

         do_action( 'czr_post_metabox_added', $post );
         do_action( 'czr_post_layout_metabox_added', $post );
      }






      /*
      ----------------------------------------------------------------
      ------------------ POST/PAGE SLIDER BOX ------------------------
      ----------------------------------------------------------------
      */


      /**
       * Prints the slider box content
       * @package Customizr
       * @since Customizr 2.0
       */
        function czr_fn_post_slider_box( $post ) {
           // Use nonce for verification
           wp_nonce_field( plugin_basename( __FILE__ ), 'post_slider_noncename' );

           // The actual fields for data entry
           //title check field setup
           $post_slider_check_id       = 'post_slider_check_field';
           $post_slider_check_value    = esc_attr(get_post_meta( $post->ID, $key = 'post_slider_check_key' , $single = true ));

           ?>
          <input name="tc_post_id" id="tc_post_id" type="hidden" value="<?php echo $post-> ID ?>"/>
          <div class="meta-box-item-title">
            <h4><label for="<?php echo $post_slider_check_id; ?>"><?php _e( 'Add a slider to this post/page' , 'customizr' ); ?></label></h4>
           </div>
           <div class="meta-box-item-content">
               <?php
                  $post_slider_checked = false;
                  if ( $post_slider_check_value == 1) {
                     $post_slider_checked = true;
                  }
                  CZR_meta_boxes::czr_fn_checkbox_view( array(
                     'input_name'   => $post_slider_check_id,
                     'input_state'  => $post_slider_checked,
                  ));
               ?>
           </div>
           <div id="slider-fields-box">
             <?php do_action( '__post_slider_infos' , $post->ID ); ?>
           </div>
         <?php

         do_action( 'czr_post_metabox_added', $post );
         do_action( 'czr_slider_metabox_added', $post );

      }//end of function





    /**
     * Display post slider dynamic content
     * This function is also called by the ajax call back
     * @package Customizr
     * @since Customizr 2.0
     */
      function czr_fn_get_post_slider_infos( $postid ) {
         //check value is ajax saved ?
         $post_slider_check_value   = esc_attr(get_post_meta( $postid, $key = 'post_slider_check_key' , $single = true ));

         //retrieve all sliders in option array
         $options                  = get_option( 'tc_theme_options' );
         if ( isset($options['tc_sliders']) ) {
           $sliders                  = $options['tc_sliders'];
         }else
           $sliders                  = array();

         //post slider fields setup
         $post_slider_id           = 'post_slider_field';

         //get current post slider
         $current_post_slider       = esc_attr(get_post_meta( $postid, $key = 'post_slider_key' , $single = true ));
         if ( isset( $sliders[$current_post_slider])) {
           $current_post_slides     = $sliders[$current_post_slider];
         }

         //Delay field setup
         $delay_id                 = 'slider_delay_field';
         $delay_value              = esc_attr(get_post_meta( $postid, $key = 'slider_delay_key' , $single = true ));

         //Layout field setup
         $layout_id                = 'slider_layout_field';
         $layout_value             = esc_attr(get_post_meta( $postid, $key = 'slider_layout_key' , $single = true ));

         //overlay field setup
         $overlay_id               = 'slider_overlay_field';
         $overlay_value            = esc_attr(get_post_meta( $postid, $key = 'slider_overlay_key' , $single = true ));

         //dots field setup
         $dots_id                  = 'slider_dots_field';
         $dots_value               = esc_attr(get_post_meta( $postid, $key = 'slider_dots_key' , $single = true ));

         //sliders field
         $slider_id                = 'slider_field';

         if( $post_slider_check_value == true ):
             $selectable_sliders    = apply_filters( 'czr_post_selectable_sliders', $sliders );
             if ( isset( $selectable_sliders ) && !empty( $selectable_sliders ) ):

         ?>
             <div class="meta-box-item-title">
               <h4><?php _e("Choose a slider", 'customizr' ); ?></h4>
             </div>
         <?php
             //build selectable slider->ID => label
             //Default in head
             $selectable_sliders = array_merge( array(
               -1 => __( '&mdash; Select a slider &mdash; ' , 'customizr' )
             ), $selectable_sliders );

             //in case of sliders of images we set the label as the slider_id
             if ( isset($sliders) && !empty( $sliders) )
               foreach ( $sliders as $key => $value) {
                 if ( is_array( $value ) )
                  $selectable_sliders[ $key ] = $key;
               }
         ?>
               <div class="meta-box-item-content">
                 <span class="spinner" style="float: left;visibility:visible;display: none;"></span>
                 <select name="<?php echo $post_slider_id; ?>" id="<?php echo $post_slider_id; ?>">
                 <?php //sliders select choices
                  foreach ( $selectable_sliders as $id => $label ) {
                    printf( '<option value="%1$s" %2$s> %3$s</option>',
                        esc_attr( $id ),
                        selected( $current_post_slider, esc_attr( $id ), $echo = false ),
                        $label
                    );
                  }
                 ?>
                 </select>
                  <i><?php _e( 'To create a new slider : open the media library, edit your images and add them to your new slider.' , 'customizr' ) ?></i>
               </div>

               <div class="meta-box-item-title">
                 <h4><?php _e("Delay between each slides in milliseconds (default : 5000 ms)", 'customizr' ); ?></h4>
               </div>
               <div class="meta-box-item-content">
                  <input name="<?php echo esc_attr( $delay_id) ; ?>" id="<?php echo esc_attr( $delay_id); ?>" value="<?php if (empty( $delay_value)) { echo '5000';} else {echo esc_attr( $delay_value);} ?>"/>
               </div>

               <div class="meta-box-item-title">
                  <h4><?php _e("Slider Layout : set the slider in full width", 'customizr' );  ?></h4>
               </div>
               <div class="meta-box-item-content">
                  <?php
                  if ( $layout_value ==null || $layout_value ==1 )
                  {
                    $layout_check_value = true;
                  }
                  else {
                    $layout_check_value = false;
                  }
                  CZR_meta_boxes::czr_fn_checkbox_view( array(
                     'input_name'   => $layout_id,
                     'input_state'  => $layout_check_value,
                  ));
                  ?>
               </div>
               <?php if ( CZR_IS_MODERN_STYLE ) : ?>
                   <div class="meta-box-item-title">
                      <h4><?php _e("Apply a dark overlay on your slider's images", 'customizr' );  ?></h4>
                   </div>
                   <div class="meta-box-item-content">
                      <?php
                      if ( $overlay_value == null || 'on' == $overlay_value || 1 === $overlay_value || true === $overlay_value )
                      {
                        $overlay_check_value = true;
                      }
                      else {
                        $overlay_check_value = false;
                      }
                      CZR_meta_boxes::czr_fn_checkbox_view( array(
                         'input_name'   => $overlay_id,
                         'input_state'  => $overlay_check_value,
                      ));
                      ?>
                   </div>

                   <div class="meta-box-item-title">
                      <h4><?php _e("Display navigation dots at the bottom of your slider.", 'customizr' );  ?></h4>
                   </div>
                   <div class="meta-box-item-content">
                      <?php
                      if ( $dots_value == null || 'on' == $dots_value || 1 === $dots_value || true === $dots_value ) {
                        $dots_check_value = true;
                      }
                      else {
                        $dots_check_value = false;
                      }
                      CZR_meta_boxes::czr_fn_checkbox_view( array(
                         'input_name'   => $dots_id,
                         'input_state'  => $dots_check_value,
                      ));
                      ?>
                   </div>
              <?php endif; ?>
               <?php if (isset( $current_post_slides)) : ?>
                    <div style="z-index: 1000;position: relative;">
                      <p style="display: inline-block;float: right;"><a href="#TB_inline?width=350&height=100&inlineId=post_slider-warning-message" class="thickbox"><?php _e( 'Delete this slider' , 'customizr' ) ?></a></p>
                    </div>
                    <div id="post_slider-warning-message" style="display:none;">
                      <div style="text-align:center">
                         <p>
                           <?php _e( 'The slider will be deleted permanently (images, call to actions and link will be kept).' , 'customizr' ) ?>
                        </p>
                          <br/>
                           <a class="button-secondary" id="delete-slider" href="#" title="<?php _e( 'Delete slider' , 'customizr' ); ?>" onClick="javascript:window.parent.tb_remove()"><?php _e( 'Delete slider' , 'customizr' ); ?></a>
                      </div>
                    </div>
                  <?php  do_action( '__show_slides' , $current_post_slides, $current_attachement_id = null); ?>
               <?php else: //there are no slides
                 do_action( '__no_slides', $postid, $current_post_slider );
               ?>
             <?php endif; //slides? ?>
           <?php else://if no slider created yet and no slider of posts addon?>

                <div class="meta-box-item-content">
                  <p class="description"> <?php _e("You haven't create any slider yet. Go to the media library, edit your images and add them to your sliders.", "customizr" ) ?><br/>
                  </p>
                  <br />
               </div>
             <?php endif; //sliders? ?>
           <?php endif; //check slider? ?>
        <?php
      }






      /*
      ----------------------------------------------------------------
      ------- SAVE POST/PAGE FIELDS (LAYOUT AND SLIDER FIELDS) -------
      ----------------------------------------------------------------
      */
      /**
       * When the post/page is saved, saves our custom data for slider and layout options
       * @package Customizr
       * @since Customizr 1.0
       */
      function czr_fn_post_fields_save( $post_id, $post_object = null ) {
        // verify if this is an auto save routine.
        // If it is our form has not been submitted, so we dont want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
           return;

        // Check permissions
        if ( isset( $_POST['post_type']) && 'page' == $_POST['post_type'] )
        {
         if ( !current_user_can( 'edit_page' , $post_id ) )
             return;
        }
        else
        {
         if ( !current_user_can( 'edit_post' , $post_id ) )
             return;
        }

        //LINK
        $this->czr_fn_link_save( $post_id, $post_object );


        //QUOTE
        $this->czr_fn_quote_save( $post_id, $post_object );

        //AUDIO
        $this->czr_fn_audio_save( $post_id, $post_object );

        //VIDEO
        $this->czr_fn_video_save( $post_id, $post_object );

        ################# LAYOUT BOX #################
        // verify this came from our screen and with proper authorization,
        if ( !isset( $_POST['post_layout_noncename']) || !wp_verify_nonce( $_POST['post_layout_noncename'], plugin_basename( __FILE__ ) ) )
           return;

        // OK, we're authenticated: we need to find and save the data
        //set up the fields array
        $tc_post_layout_fields = array(
            'layout_field'             =>  'layout_key'
           );

        //if saving in a custom table, get post_ID
       if ( isset( $_POST['post_ID'])) {
         $post_ID = $_POST['post_ID'];
         //sanitize user input by looping on the fields
         foreach ( $tc_post_layout_fields as $tcid => $tckey) {
             if ( isset( $_POST[$tcid])) {
               $mydata = sanitize_text_field( $_POST[$tcid] );

               // Do something with $mydata
               // either using
               add_post_meta( $post_ID, $tckey, $mydata, true) or
                 update_post_meta( $post_ID, $tckey , $mydata);
               // or a custom table (see Further Reading section below)
             }
            }
        }

        ################# SLIDER BOX #################
        // verify this came from our screen and with proper authorization,
        if ( !isset( $_POST['post_slider_noncename']) || !wp_verify_nonce( $_POST['post_slider_noncename'], plugin_basename( __FILE__ ) ) )
           return;


        // OK, we're authenticated: we need to find and save the data
        //set up the fields array
        $tc_post_slider_fields = array(
            'post_slider_check_field'   => 'post_slider_check_key',
            'slider_delay_field'        => 'slider_delay_key',
            'slider_layout_field'       => 'slider_layout_key',
            'slider_overlay_field'      => 'slider_overlay_key',
            'slider_dots_field'         => 'slider_dots_key',
            'post_slider_field'         => 'post_slider_key',
           );

        //if saving in a custom table, get post_ID
       if ( isset( $_POST['post_ID'])) {
         do_action( '__before_save_post_slider_fields', $_POST, $tc_post_slider_fields );
         $post_ID = $_POST['post_ID'];
         //sanitize user input by looping on the fields
         foreach ( $tc_post_slider_fields as $tcid => $tckey) {
           if ( isset( $_POST[$tcid])) {
               if ( in_array( $tcid, array( 'slider_overlay_field', 'slider_dots_field' ) ) ) {
                  $mydata = 0 == $_POST[$tcid] ? 'off' : 'on';
                  $mydata = sanitize_text_field( $mydata );
               } else {
                  $mydata = sanitize_text_field( $_POST[$tcid] );
              }

               // Do something with $mydata
               // either using
               add_post_meta( $post_ID, $tckey, $mydata, true) or
                 update_post_meta( $post_ID, $tckey , $mydata);
               // or a custom table (see Further Reading section below)
           }
         }
         do_action( '__after_save_post_slider_fields', $_POST, $tc_post_slider_fields );
        }


      }



      /**
      * When the post/page is saved, saves our custom data for link
      */
      function czr_fn_link_save( $post_id ) {

         // verify if this is an auto save routine.
         // If it is our form has not been submitted, so we dont want to do anything
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
           return $post_id;


         //check field existence
         if ( !( isset( $_POST[ 'czr_link_title' ] ) && isset( $_POST[ 'czr_link_url' ] ) ) )
           return $post_id;

         // Check permissions
         if ( !isset($post_id) || !isset( $_POST['post_type'] ) || !isset( $_POST['format_link_noncename'] ) )
           return $post_id;

         if ( !wp_verify_nonce( $_POST['format_link_noncename'], plugin_basename( __FILE__ ) ) )
           return $post_id;

         if ( !current_user_can( 'edit_post' , $post_id ) )
           return $post_id;


         if ( 'post' != $_POST[ 'post_type' ] )
           return $post_id;

         if ( 'link' != get_post_format( $post_id ) )
           return $post_id;


         //build custom post meta
         $czr_link_format_meta = array(
            'link_title' => sanitize_text_field( $_POST[ 'czr_link_title' ] ),
            'link_url'   => esc_url( $_POST[ 'czr_link_url' ] )
         );

         //update
         add_post_meta( $post_id, 'czr_link_meta', $czr_link_format_meta, true ) or
          update_post_meta( $post_id, 'czr_link_meta', $czr_link_format_meta );

      }



      /**
      * When the post/page is saved, saves our custom data for quote
      */
      function czr_fn_quote_save( $post_id ) {

         // verify if this is an auto save routine.
         // If it is our form has not been submitted, so we dont want to do anything
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
           return $post_id;

         // Check permissions
         if ( !isset($post_id) || !isset( $_POST['post_type'] ) || !isset( $_POST['format_quote_noncename'] ) )
           return $post_id;

         if ( !wp_verify_nonce( $_POST['format_quote_noncename'], plugin_basename( __FILE__ ) ) )
           return $post_id;

         if ( !current_user_can( 'edit_post' , $post_id ) )
           return $post_id;

         //check field existence
         if ( !( isset( $_POST[ 'czr_quote_text' ] ) && isset( $_POST[ 'czr_quote_author' ] ) ) )
           return $post_id;

         if ( 'post' != $_POST[ 'post_type' ] )
           return $post_id;

         if ( 'quote' != get_post_format( $post_id ) )
           return $post_id;

         //build custom post meta
         $czr_quote_format_meta = array(
            'quote_text'   => sanitize_text_field( $_POST[ 'czr_quote_text' ] ),
            'quote_author' => sanitize_text_field( $_POST[ 'czr_quote_author' ] )
         );

         //update
         add_post_meta( $post_id, 'czr_quote_meta', $czr_quote_format_meta, true ) or
          update_post_meta( $post_id, 'czr_quote_meta', $czr_quote_format_meta );

      }

      /**
      * When the post/page is saved, saves our custom data for audio
      */
      function czr_fn_audio_save( $post_id ) {

         // verify if this is an auto save routine.
         // If it is our form has not been submitted, so we dont want to do anything
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
           return $post_id;

         // Check permissions
         if ( !isset($post_id) || !isset( $_POST['post_type'] ) || !isset( $_POST['format_audio_noncename'] ) )
           return $post_id;

         if ( !wp_verify_nonce( $_POST['format_audio_noncename'], plugin_basename( __FILE__ ) ) )
           return $post_id;

         if ( !current_user_can( 'edit_post' , $post_id ) )
           return $post_id;

         //check field existence
         if ( !( isset( $_POST[ 'czr_audio_url' ] ) ) )
           return $post_id;

         if ( 'post' != $_POST[ 'post_type' ] )
           return $post_id;

         if ( 'audio' != get_post_format( $post_id ) )
           return $post_id;


         //build custom post meta
         $czr_audio_format_meta = array(
            'audio_url'   => esc_url( $_POST[ 'czr_audio_url' ] )
         );

         //update
         add_post_meta( $post_id, 'czr_audio_meta', $czr_audio_format_meta, true ) or
          update_post_meta( $post_id, 'czr_audio_meta', $czr_audio_format_meta );

      }



      /**
      * When the post/page is saved, saves our custom data for video
      */
      function czr_fn_video_save( $post_id ) {

         // verify if this is an auto save routine.
         // If it is our form has not been submitted, so we dont want to do anything
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
           return $post_id;

         // Check permissions
         if ( !isset($post_id) || !isset( $_POST['post_type'] ) || !isset( $_POST['format_video_noncename'] ) )
           return $post_id;

         if ( !wp_verify_nonce( $_POST['format_video_noncename'], plugin_basename( __FILE__ ) ) )
           return $post_id;

         if ( !current_user_can( 'edit_post' , $post_id ) )
           return $post_id;

         //check field existence
         if ( !( isset( $_POST[ 'czr_video_url' ] ) ) )
           return $post_id;

         if ( 'post' != $_POST[ 'post_type' ] )
           return $post_id;

         if ( 'video' != get_post_format( $post_id ) )
           return $post_id;


         //build custom post meta
         $czr_video_format_meta = array(
            'video_url'   => esc_url( $_POST[ 'czr_video_url' ] )
         );

         //update
         add_post_meta( $post_id, 'czr_video_meta', $czr_video_format_meta, true ) or
         update_post_meta( $post_id, 'czr_video_meta', $czr_video_format_meta );

      }


      /*
      ----------------------------------------------------------------
      ------------------ ATTACHMENT SLIDER META BOX ------------------
      ----------------------------------------------------------------
      */
      /**
       * Add a slider metabox to attachments
       * @package Customizr
       * @since Customizr 2.0
       */
      function czr_fn_attachment_meta_box( $id ) {//id, title, callback, post_type, context, priority, callback_args
         if ( !wp_attachment_is_image( $id ) )
            return;

         add_meta_box(
            'slider_sectionid' ,
            __( 'Slider Options' , 'customizr' ),
            array( $this , 'czr_fn_attachment_slider_box' )
         );

      }







      /**
       * Prints the slider box content
       * @package Customizr
       * @since Customizr 2.0
       */
        function czr_fn_attachment_slider_box( $post ) {
           // Use nonce for verification
           wp_nonce_field( plugin_basename( __FILE__ ), 'slider_noncename' );

           // The actual fields for data entry
           //title check field setup
           $slider_check_id       = 'slider_check_field';
           $slider_check_value    = esc_attr(get_post_meta( $post->ID, $key = 'slider_check_key' , $single = true ));

           ?>
          <div class="meta-box-item-title">
             <h4><label for="<?php echo $slider_check_id; ?>"><?php _e( 'Add to a slider (create one if needed)' , 'customizr' ) ?></label></h4>
           </div>
           <div class="meta-box-item-content">
             <input name="tc_post_id" id="tc_post_id" type="hidden" value="<?php echo $post->ID ?>"/>
              <?php
                  $slider_checked = false;
                  if ( $slider_check_value == 1) {
                     $slider_checked = true;
                  }
                  CZR_meta_boxes::czr_fn_checkbox_view( array(
                     'input_name'   => $slider_check_id,
                     'input_state'  => $slider_checked,
                  ));
               ?>
           </div>
          <div id="slider-fields-box">
            <?php do_action( '__attachment_slider_infos' , $post->ID); ?>
          </div>
         <?php

         do_action( 'czr_attachment_metabox_added', $post );
         do_action( 'czr_slider_metabox_added', $post );
      }







      /**
       * Display attachment slider dynamic content
       * This function is also called by the ajax call back function
       * @package Customizr
       * @since Customizr 2.0
       */
        function czr_fn_get_attachment_slider_infos( $postid ) {
         //check value is ajax saved ?
         $slider_check_value     = esc_attr(get_post_meta( $postid, $key = 'slider_check_key' , $single = true ));

         //post slider fields setup
         $post_slider_id         = 'post_slider_field';

         //sliders field
         $slider_id             = 'slider_field';

         //retrieve all sliders in option array
         $options               = get_option( 'tc_theme_options' );
         $sliders               = array();
         if ( isset( $options['tc_sliders'])) {
           $sliders             = $options['tc_sliders'];
         }

         //get_attachment details for default slide values
         $attachment            = get_post( $postid);
         $default_title         = $attachment->post_title;
         $default_description    = $attachment->post_excerpt;

         //title field setup
         $title_id              = 'slide_title_field';
         $title_value           = esc_attr(get_post_meta( $postid, $key = 'slide_title_key' , $single = true ));
         //we define a filter for the slide_text_length
         $default_title_length   = apply_filters( 'tc_slide_title_length', apply_filters( 'czr_slide_title_length', 80 ) );

         //check if we already have a custom key created for this field, if not apply default value
         if(!in_array( 'slide_title_key' ,get_post_custom_keys( $postid))) {
           $title_value = $default_title;
         }
         $title_value = esc_html( czr_fn_text_truncate( $title_value, $default_title_length, '...' ) );


         //text_field setup : sanitize and limit length
         $text_id        = 'slide_text_field';
         $text_value     = esc_html(get_post_meta( $postid, $key = 'slide_text_key' , $single = true ));
          //we define a filter for the slide_title_length
         $default_text_length   = apply_filters( 'tc_slide_text_length', apply_filters( 'czr_slide_text_length', 250 ) );

          //check if we already have a custom key created for this field, if not apply default value
         if(!in_array( 'slide_text_key' ,get_post_custom_keys( $postid)))
           $text_value = $default_description;
         $text_value = czr_fn_text_truncate( $text_value, $default_text_length, '...' );


          //Color field setup
         $color_id       = 'slide_color_field';
         $color_value    = esc_attr(get_post_meta( $postid, $key = 'slide_color_key' , $single = true ));

         //button field setup
         $button_id      = 'slide_button_field';
         $button_value   = esc_attr(get_post_meta( $postid, $key = 'slide_button_key' , $single = true ));

         //we define a filter for the slide text_button length
         $default_button_length   = apply_filters( 'tc_slide_button_length', apply_filters( 'czr_slide_button_length', 80 ) );
         $button_value   = czr_fn_text_truncate( $button_value, $default_button_length, '...' );



         //link field setup
         $link_id        = 'slide_link_field';
         $link_value     = esc_attr(get_post_meta( $postid, $key = 'slide_link_key' , $single = true ));

         //retrieve post, pages and custom post types (if any) and generate the ordered select list for the button link
         $post_types     = get_post_types(array( 'public' => true));
         $excludes       = array( 'attachment' );


         foreach ( $post_types as $t) {
             if (!in_array( $t, $excludes)) {
              //get the posts a tab of types
              $tc_all_posts[$t] = get_posts(  array(
                  'numberposts'     =>  100,
                  'orderby'         =>  'date' ,
                  'order'          =>  'DESC' ,
                  'post_type'       =>  $t,
                  'post_status'     =>  'publish' )
               );
             }
           };

         //custom link field setup
         $custom_link_id    = 'slide_custom_link_field';
         $custom_link_value = esc_url( get_post_meta( $postid, $key = 'slide_custom_link_key', $single = true ) );

         //link target setup
         $link_target_id    = 'slide_link_target_field';
         $link_target_value = esc_attr( get_post_meta( $postid, $key = 'slide_link_target_key', $single = true ) ) ;

         //link whole slide setup
         $link_whole_slide_id    = 'slide_link_whole_slide_field';
         $link_whole_slide_value = esc_attr( get_post_meta( $postid, $key = 'slide_link_whole_slide_key', $single = true ) ) ;

         //display fields if slider button is checked
         if ( $slider_check_value == true )  {
            ?>
           <div class="meta-box-item-title">
               <h4><?php _e( 'Title text (80 char. max length)' , 'customizr' ); ?></h4>
           </div>
           <div class="meta-box-item-content">
               <input class="widefat" name="<?php echo esc_attr( $title_id); ?>" id="<?php echo esc_attr( $title_id); ?>" value="<?php echo esc_attr( $title_value); ?>" style="width:50%">
           </div>

           <div class="meta-box-item-title">
               <h4><?php _e( 'Description text (below the title, 250 char. max length)' , 'customizr' ); ?></h4>
           </div>
           <div class="meta-box-item-content">
               <textarea name="<?php echo esc_attr( $text_id); ?>" id="<?php echo esc_attr( $text_id); ?>" style="width:50%"><?php echo esc_html( $text_value); ?></textarea>
           </div>

            <div class="meta-box-item-title">
               <h4><?php _e("Title and text color", 'customizr' );  ?></h4>
           </div>
           <div class="meta-box-item-content">
               <input id="<?php echo esc_attr( $color_id); ?>" name="<?php echo esc_attr( $color_id); ?>" value="<?php echo esc_attr( $color_value); ?>"/>
               <div id="colorpicker"></div>
           </div>

            <div class="meta-box-item-title">
               <h4><?php _e( 'Button text (80 char. max length)' , 'customizr' ); ?></h4>
           </div>
           <div class="meta-box-item-content">
               <input class="widefat" name="<?php echo esc_attr( $button_id); ?>" id="<?php echo esc_attr( $button_id); ?>" value="<?php echo esc_attr( $button_value); ?>" style="width:50%">
           </div>

           <div class="meta-box-item-title">
               <h4><?php _e("Choose a linked page or post (among the last 100).", 'customizr' ); ?></h4>
           </div>
           <div class="meta-box-item-content">
               <select name="<?php echo esc_attr( $link_id); ?>" id="<?php echo esc_attr( $link_id); ?>">
                 <?php //no link option ?>
                 <option value="" <?php selected( $link_value, $current = null, $echo = true ) ?>> <?php _e( 'No link' , 'customizr' ); ?></option>
                 <?php foreach( $tc_all_posts as $type) : ?>
                    <?php foreach ( $type as $key => $item) : ?>
                  <option value="<?php echo esc_attr( $item->ID); ?>" <?php selected( $link_value, $current = $item->ID, $echo = true ) ?>>{<?php echo esc_attr( $item->post_type) ;?>}&nbsp;<?php echo esc_attr( $item->post_title); ?></option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
               </select><br />
           </div>
           <div class="meta-box-item-title">
               <h4><?php _e("or a custom link (leave this empty if you already selected a page or post above)", 'customizr' ); ?></h4>
           </div>
           <div class="meta-box-item-content">
               <input class="widefat" name="<?php echo $custom_link_id; ?>" id="<?php echo $custom_link_id; ?>" value="<?php echo $custom_link_value; ?>" style="width:50%">
           </div>
           <div class="meta-box-item-title">
               <h4><?php _e("Open link in a new browser tab", 'customizr' );  ?></h4>
           </div>
           <div class="meta-box-item-content">
               <?php
                  CZR_meta_boxes::czr_fn_checkbox_view( array(
                     'input_name'   => $link_target_id,
                     'input_state'  => $link_target_value,
                  ));
               ?>
           </div>
           <div class="meta-box-item-title">
               <h4><?php _e("Link the whole slide", 'customizr' );  ?></h4>
           </div>
           <div class="meta-box-item-content">
               <?php
                  CZR_meta_boxes::czr_fn_checkbox_view( array(
                     'input_name'   => $link_whole_slide_id,
                     'input_state'  => $link_whole_slide_value,
                  ));
               ?>
           </div>
           <div class="meta-box-item-title">
             <h4><?php _e("Choose a slider", 'customizr' ); ?></h4>
           </div>
           <?php if (!empty( $sliders)) : ?>
             <div class="meta-box-item-content">
                 <?php //get current post slider
                  $current_post_slider = null;
                  foreach( $sliders as $slider_name => $slider_posts) {
                     if (in_array( $postid, $slider_posts)) {
                          $current_post_slider = $slider_name;
                          $current_post_slides = $slider_posts;
                      }
                  }
                 ?>
                 <select name="<?php echo esc_attr( $post_slider_id); ?>" id="<?php echo esc_attr( $post_slider_id); ?>">
                  <?php //no link option ?>
                  <option value="" <?php selected( $current_post_slider, $current = null, $echo = true ) ?>> <?php _e( '&mdash; Select a slider &mdash; ' , 'customizr' ); ?></option>
                     <?php foreach( $sliders as $slider_name => $slider_posts) : ?>
                          <option value="<?php echo $slider_name ?>" <?php selected( $slider_name, $current = $current_post_slider, $echo = true ) ?>><?php echo $slider_name?></option>
                     <?php endforeach; ?>
                 </select>
                 <input name="<?php echo $slider_id  ?>" id="<?php echo $slider_id ?>" value=""/>
                 <span class="button-primary" id="tc_create_slider"><?php _e( 'Add a slider' , 'customizr' ) ?></span>
                 <span class="spinner" style="float: left;visibility:visible;display: none;"></span>
                 <?php if (isset( $current_post_slides)) : ?>
                    <p style="text-align:right"><a href="#TB_inline?width=350&height=100&inlineId=slider-warning-message" class="thickbox"><?php _e( 'Delete this slider' , 'customizr' ) ?></a></p>
                    <div id="slider-warning-message" style="display:none;">
                      <div style="text-align:center">
                         <p>
                           <?php _e( 'The slider will be deleted permanently (images, call to actions and link will be kept).' , 'customizr' ) ?>
                        </p>
                          <br/>
                           <a class="button-secondary" id="delete-slider" href="#" title="<?php _e( 'Delete slider' , 'customizr' ); ?>" onClick="javascript:window.parent.tb_remove()"><?php _e( 'Delete slider' , 'customizr' ); ?></a>
                      </div>
                    </div>
                 <?php endif; ?>
               </div>


               <?php
                 if ( isset( $current_post_slides) ) {
                  $current_attachement_id = $postid;
                  do_action( '__show_slides' ,$current_post_slides, $current_attachement_id);
                 }
               ?>

           <?php else : //if no slider created yet ?>

                <div class="meta-box-item-content">
                  <p class="description"> <?php _e("You haven't create any slider yet. Write a slider name and click on the button to add you first slider.", "customizr" ) ?><br/>
                  <input name="<?php echo $slider_id  ?>" id="<?php echo $slider_id ?>" value=""/>
                  <span class="button-primary" id="tc_create_slider"><?php _e( 'Add a slider' , 'customizr' ) ?></span>
                  <span class="spinner" style="float: left; diplay:none;"></span>
                  </p>
                  <br />
               </div>
           <?php endif; ?>
             <?php
         }//endif slider checked (used for ajax call back!)
      }





      /*
      ----------------------------------------------------------------
      -------------------- SAVE ATTACHMENT FIELDS --------------------
      ----------------------------------------------------------------
      */

      /**
       * When the attachment is saved, saves our custom slider data
       * @package Customizr
       * @since Customizr 2.0
       */
        function czr_fn_slide_save( $post_id ) {
         // verify if this is an auto save routine.
         // If it is our form has not been submitted, so we dont want to do anything


         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
             return;

         // verify this came from our screen and with proper authorization,
         // because save_post can be triggered at other times

         if ( !isset( $_POST['slider_noncename']) || !wp_verify_nonce( $_POST['slider_noncename'], plugin_basename( __FILE__ ) ) )
             return;

         // Check permissions
         if ( !current_user_can( 'edit_post' , $post_id ) )
               return;

         // OK, we're authenticated: we need to find and save the data

         //set up the fields array
         $tc_slider_fields = array(
              'slide_title_field'            => 'slide_title_key' ,
              'slide_text_field'             => 'slide_text_key' ,
              'slide_color_field'            => 'slide_color_key' ,
              'slide_button_field'           => 'slide_button_key' ,
              'slide_link_field'             => 'slide_link_key' ,
              'slide_custom_link_field'       => 'slide_custom_link_key',
              'slide_link_target_field'       => 'slide_link_target_key',
              'slide_link_whole_slide_field'  => 'slide_link_whole_slide_key'
         );

         //if saving in a custom table, get post_ID
         if ( $post_id == null)
           return;

           //sanitize user input by looping on the fields
           foreach ( $tc_slider_fields as $tcid => $tckey) {
               if ( isset( $_POST[$tcid])) {
                 $mydata = sanitize_text_field( $_POST[$tcid] );

                  switch ( $tckey) {
                    //different sanitizations
                    case 'slide_text_key':
                        $default_text_length = apply_filters( 'tc_slide_text_length', apply_filters( 'czr_slide_text_length', 250 ) );
                        $mydata = esc_html( czr_fn_text_truncate( $mydata, $default_text_length, '...' ) );
                    break;

                    case 'slide_title_key':
                        $default_title_length = apply_filters( 'tc_slide_title_length', apply_filters( 'czr_slide_title_length', 80 ) );
                        $mydata = esc_html( czr_fn_text_truncate( $mydata, $default_title_length, '...' ) );
                    break;

                    case 'slide_button_key':
                        $default_button_text_length = apply_filters( 'tc_slide_button_length', apply_filters( 'czr_slide_button_length', 80 ) );
                        $mydata = esc_html( czr_fn_text_truncate( $mydata, $default_button_text_length, '...' ) );
                    break;

                    case 'slide_custom_link_key':
                        $mydata = esc_url( $_POST[$tcid] );
                    break;

                    case 'slide_link_target_key';
                    case 'slide_link_whole_slide_key':
                        $mydata = esc_attr( $mydata );
                    break;

                    default://for color, post link field (actually not a link but an id)
                        $mydata = esc_attr( $mydata );
                      break;
                  }//end switch
                 //write in DB
                 add_post_meta( $post_id, $tckey, $mydata, true) or
                 update_post_meta( $post_id, $tckey , $mydata);
               }//end if isset $tckey
           }//end foreach
        }






      /*
      ----------------------------------------------------------------
      ---------- DISPLAY SLIDES TABLE (post and attachment) ----------
      ----------------------------------------------------------------
      */

      /**
       * Display slides table dynamic content for the selected slider
       * @package Customizr
       * @since Customizr 2.0
       */
      function czr_fn_show_slides ( $current_post_slides,$current_attachement_id) {
         //check if we have slides to show
         ?>
         <?php if(empty( $current_post_slides)) : ?>
           <div class="meta-box-item-content">
              <p class="description"> <?php _e("This slider has not slides to show. Go to the media library and start adding images to it.", "customizr" ) ?><br/>
              </p>
             <br />
           </div>
         <?php else : // render?>
           <div id="tc_slides_table">
             <div id="update-status"></div>
                 <table class="wp-list-table widefat fixed media" cellspacing="0">
                  <thead>
                      <tr>
                        <th scope="col"><?php _e( 'Slide Image' , 'customizr' ) ?></th>
                        <th scope="col"><?php _e( 'Title' , 'customizr' ) ?></th>
                        <th scope="col" style="width: 35%"><?php _e( 'Slide Text' , 'customizr' ) ?></th>
                        <th scope="col"><?php _e( 'Button Text' , 'customizr' ) ?></th>
                        <th scope="col"><?php _e( 'Link' , 'customizr' ) ?></th>
                        <th scope="col"><?php _e( 'Edit' , 'customizr' ) ?></th>
                      </tr>
                    </thead>
                  <tbody id="sortable">
                    <?php
                    //loop on the slides and render if the selected slider is checked
                    foreach ( $current_post_slides as $index => $slide) {
                      //get the attachment object
                      $tc_slide = get_post( $slide );

                      //check if $tc_slide object exists otherwise go to the next iteration
                      if (!isset( $tc_slide))
                        continue;

                      //check if slider is checked for this attachment => otherwise go to the next iteration
                      $slider_check_value     = esc_attr(get_post_meta( $tc_slide->ID, $key = 'slider_check_key' , $single = true ));
                      if ( $slider_check_value == false)
                        continue;

                      //set up variables
                      $id                   = $tc_slide->ID;
                      $slide_src             = wp_get_attachment_image_src( $id, 'thumbnail' );
                      $slide_url             = $slide_src[0];
                      $title                 = esc_attr(get_post_meta( $id, $key = 'slide_title_key' , $single = true ));
                      $text                  = esc_html(get_post_meta( $id, $key = 'slide_text_key' , $single = true ));
                      $text_color            = esc_attr(get_post_meta( $id, $key = 'slide_color_key' , $single = true ));
                      $button_text           = esc_attr(get_post_meta( $id, $key = 'slide_button_key' , $single = true ));
                      $link                  = esc_url(get_post_meta( $id, $key = 'slide_custom_link_key' , $single = true ));
                      $button_link           = esc_attr(get_post_meta( $id, $key = 'slide_link_key' , $single = true ));

                      //check if $text_color is set and create an html style attribute
                      $color_style ='';
                      if( $text_color != null) {
                        $color_style = 'style="color:'.$text_color.'"';
                      }
                      ?>
                      <tr id="<?php echo $index ?>" class="ui-state-default" valign="middle">
                        <td style="vertical-align:middle" class="column-icon">
                           <?php if( $slide_url != null) : ?>
                             <img width="100" height="100" src="<?php echo $slide_url; ?>" class="attachment-80x60" alt="Hydrangeas">
                           <?php else : ?>
                             <div style="height:100px;width:100px;background:#eee;text-align:center;line-height:100px;vertical-align:middle">
                               <?php _e( 'No Image Selected' , 'customizr' ); ?>
                             </div>
                           <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle" class="">
                           <?php if( $title != null) : ?>
                             <p <?php echo $color_style ?>><strong><?php echo $title ?></strong></p>
                           <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle" class="">
                            <?php if( $text != null) : ?>
                             <p <?php echo $color_style ?> class="lead"><?php echo $text ?></p>
                           <?php endif; ?>
                        </td>
                        <td style="vertical-align:middle" class="">
                           <?php if( $button_text != null) : ?>
                             <p class="btn btn-large btn-primary"><?php echo $button_text; ?></p>
                           <?php endif; ?>
                        </td>
                         <td style="vertical-align:middle" class="">
                           <?php if( $button_link != null || $link != null ) : ?>
                             <p class="btn btn-large btn-primary" href="<?php echo $link ? $link : get_permalink( $button_link); ?>"><?php echo $link ? $link : get_the_title( $button_link); ?></p>
                           <?php endif; ?>
                        </td>
                         <td style="vertical-align:middle" class="">
                           <?php if( $id != $current_attachement_id) : ?>
                             <a class="button-primary" href="<?php echo admin_url( 'post.php?post='.$id.'&action=edit' ) ?>" target="_blank"><?php _e( 'Edit this slide' , 'customizr' )?></a>
                           <?php else : ?>
                             <span style="color:#999898"><?php _e( 'Current slide' , 'customizr' )?></span>
                           <?php endif; ?>
                        </td>
                      </tr>
                      <?php
                    }//end foreach
                  echo '</tbody></table><br/>';
                  ?>
                  <div class="tc-add-slide-notice">
                    <?php
                      printf('<p>%1$s</p><p>%2$s <a href="%3$s" title="%4$s" target="_blank">%4$s &raquo;</a>.</p>',
                        __('To add another slide : navigate to your media library (click on Media), open the edit screen of an image ( or add a new image ), and add it to your desired slider by using the dedicated option block at the bottom of the page.' , 'customizr'),
                        __('For more informations about sliders, check the documentation page :' , 'customizr'),
                        esc_url('http://docs.presscustomizr.com/search?query=slider'),
                        __('Slider documentation' , 'customizr')
                      );
                    ?>
                  </div>
             </div><!-- //#tc_slides_table -->
         <?php endif; // empty( $current_post_slides? ?>
        <?php
      }





      /*
      ----------------------------------------------------------------
      ---------------- AJAX SAVE (post and attachment) ---------------
      ----------------------------------------------------------------
      */
      /**
       * Ajax saving of options and meta fields in DB for post and attachement screens
       * works along with tc_ajax_slider.js
       * @package Customizr
       * @since Customizr 2.0
       */
      function czr_fn_slider_ajax_save( $post_id ) {

           //We check the ajax nonce (common for post and attachment)
           if ( !isset( $_POST['SliderCheckNonce']) || !wp_verify_nonce( $_POST['SliderCheckNonce'], 'tc-slider-check-nonce' ) )
               return;

           // Check permissions
           if ( !current_user_can( 'edit_post' , $post_id ) )
               return;

           // Do we have a post_id?
           if ( !isset( $_POST['tc_post_id'])) {
               return;
           }
           else {
               $post_ID = $_POST['tc_post_id'];
           }

           //OPTION FIELDS
           //get options and some useful $_POST vars
           $czr_options                = get_option( 'tc_theme_options' );

           if (isset( $_POST['tc_post_type']))
             $tc_post_type            = esc_attr( $_POST['tc_post_type']);
           if (isset( $_POST['currentpostslider']))
             $current_post_slider      = esc_attr( $_POST['currentpostslider']);
           if (isset( $_POST['new_slider_name']))
             $new_slider_name         = esc_attr( $_POST['new_slider_name'] );

           //Save user input by looping on the fields
           foreach ( $_POST as $tckey => $tcvalue) {
               switch ( $tckey) {
                 //delete slider
                 case 'delete_slider':
                  //first we delete the meta fields related to the deleted slider
                  //which screen are we coming from?
                  if( $tc_post_type == 'attachment' ) {
                    query_posts( 'meta_key=post_slider_key&meta_value='.$current_post_slider);
                    //we loop the posts with the deleted slider meta key
                      if(have_posts()) {
                        while ( have_posts() ) : the_post();
                           //delete the post meta
                           delete_post_meta(get_the_ID(), $key = 'post_slider_key' );
                        endwhile;
                      }
                    wp_reset_query();
                  }

                  //we delete from the post/page screen
                  else {
                    $post_slider_meta = esc_attr(get_post_meta( $post_ID, $key = 'post_slider_key' , $single = true ));
                    if(!empty( $post_slider_meta)) {
                      delete_post_meta( $post_ID, $key = 'post_slider_key' );
                    }
                  }

                  //in all cases, delete DB option
                  unset( $czr_options['tc_sliders'][$current_post_slider]);
                  //update DB with new slider array
                  update_option( 'tc_theme_options' , $czr_options );
                 break;


                 //reorder slides
                 case 'newOrder':
                    //turn new order into array
                    if(!empty( $tcvalue))

                    $neworder = explode( ',' , esc_attr( $tcvalue ));

                    //initialize the newslider array
                    $newslider = array();

                    foreach ( $neworder as $new_key => $new_index) {
                        $newslider[$new_index] =  $czr_options['tc_sliders'][$current_post_slider][$new_index];
                    }

                    $czr_options['tc_sliders'][$current_post_slider] = $newslider;

                     //update DB with new slider array
                    update_option( 'tc_theme_options' , $czr_options );
                  break;




                 //sliders are added in options
                 case 'new_slider_name':
                    //check if we have something to save
                    $new_slider_name                               = esc_attr( $tcvalue );
                    $delete_slider                                 = false;
                    if ( isset( $_POST['delete_slider']))
                        $delete_slider                             = $_POST['delete_slider'];

                    //prevent saving if we delete
                    if (!empty( $new_slider_name) && $delete_slider != true) {
                        $new_slider_name                           = wp_filter_nohtml_kses( $tcvalue );
                        //remove spaces and special char
                        $new_slider_name                           = strtolower(preg_replace("![^a-z0-9]+!i", "-", $new_slider_name));

                        $czr_options['tc_sliders'][$new_slider_name]      = array( $post_ID);
                        //adds the new slider name in DB options
                        update_option( 'tc_theme_options' , $czr_options );
                      //associate the current post with the new saved slider

                      //looks for a previous slider entry and delete it
                      foreach ( $czr_options['tc_sliders'] as $slider_name => $slider) {

                        foreach ( $slider as $key => $tc_post) {
                           //clean empty values if necessary
                           if ( is_null( $czr_options['tc_sliders'][$slider_name][$key]))
                             unset( $czr_options['tc_sliders'][$slider_name][$key]);

                           //delete previous slider entries for this post
                           if ( $tc_post == $post_ID )
                             unset( $czr_options['tc_sliders'][$slider_name][$key]);
                          }
                        }

                        //update DB with clean option table
                        update_option( 'tc_theme_options' , $czr_options );

                        //push new post value for the new slider and write in DB
                        array_push( $czr_options['tc_sliders'][$new_slider_name], $post_ID);
                        update_option( 'tc_theme_options' , $czr_options );

                      }

                  break;

                  //post slider value
                  case 'post_slider_name':
                      //check if we display the attachment screen
                      if (!isset( $_POST['slider_check_field'])) {
                        break;
                      }
                      //we are in the attachment screen and we uncheck slider options checkbox
                      elseif ( $_POST['slider_check_field'] == 0) {
                        break;
                      }

                      //if we are in the slider creation case, the selected slider has to be the new one!
                      if (!empty( $new_slider_name))
                        break;

                      //check if we have something to save
                      $post_slider_name                  = esc_attr( $tcvalue );

                      //check if we have an input and if we are not in the slider creation case
                      if (!empty( $post_slider_name)) {

                         $post_slider_name               = wp_filter_nohtml_kses( $post_slider_name );
                          //looks for a previous slider entry and delete it.
                         //Important : we check if the slider has slides first!
                           foreach ( $czr_options['tc_sliders'] as $slider_name => $slider) {
                             foreach ( $slider as $key => $tc_post) {

                               //clean empty values if necessary
                               if ( is_null( $czr_options['tc_sliders'][$slider_name][$key])) {
                                   unset( $czr_options['tc_sliders'][$slider_name][$key]);
                               }

                               //clean slides with no images
                               $slide_img = wp_get_attachment_image( $czr_options['tc_sliders'][$slider_name][$key]);
                               if (isset($slide_img) && empty($slide_img)) {
                                   unset( $czr_options['tc_sliders'][$slider_name][$key]);
                               }

                              //delete previous slider entries for this post
                              if ( $tc_post == $post_ID ) {
                                 unset( $czr_options['tc_sliders'][$slider_name][$key]);
                               }

                             }//end for each
                           }
                           //update DB with clean option table
                           update_option( 'tc_theme_options' , $czr_options );

                          //check if the selected slider is empty and set it as array
                          if( empty( $czr_options['tc_sliders'][$post_slider_name]) ) {
                           $czr_options['tc_sliders'][$post_slider_name] = array();
                          }

                          //push new post value for the slider and write in DB
                           array_push( $czr_options['tc_sliders'][$post_slider_name], $post_ID);
                           update_option( 'tc_theme_options' , $czr_options );
                      }//end if !empty( $post_slider_name)

                      //No slider selected
                      else {
                        //looks for a previous slider entry and delete it
                          foreach ( $czr_options['tc_sliders'] as $slider_name => $slider) {
                           foreach ( $slider as $key => $tc_post) {
                              //clean empty values if necessary
                              if ( is_null( $czr_options['tc_sliders'][$slider_name][$key]))
                                 unset( $czr_options['tc_sliders'][$slider_name][$key]);
                              //delete previous slider entries for this post
                              if ( $tc_post == $post_ID )
                                 unset( $czr_options['tc_sliders'][$slider_name][$key]);
                           }
                          }
                          //update DB with clean option table
                          update_option( 'tc_theme_options' , $czr_options );
                      }
                    break;
                 }//end switch
              }//end foreach

             //POST META FIELDS
             //set up the fields array
             $tc_slider_fields = array(
               //posts & pages
                'post_slider_name'           => 'post_slider_key' ,
                'post_slider_check_field'     => 'post_slider_check_key' ,
               //attachments
                'slider_check_field'         => 'slider_check_key' ,
             );

             do_action( "__before_ajax_save_slider_{$tc_post_type}", $_POST, $tc_slider_fields );
               //sanitize user input by looping on the fields
               foreach ( $tc_slider_fields as $tcid => $tckey) {
                  if ( isset( $_POST[$tcid])) {
                      switch ( $tckey) {
                        //different sanitizations
                        //the slider name custom field for a post/page
                        case 'post_slider_key' :
                           $mydata = esc_attr( $_POST[$tcid] );
                           //Does the selected slider still exists in options? (we first check if the selected slider is not empty)
                           if(!empty( $mydata) && !isset( $czr_options['tc_sliders'][$mydata]))
                             break;

                           //write in DB
                           add_post_meta( $post_ID, $tckey, $mydata, true) or
                             update_post_meta( $post_ID, $tckey , $mydata);
                        break;


                        //inserted/updated in all cases
                        case 'post_slider_check_key':
                        case 'slider_check_key':
                           $mydata = esc_attr( $_POST[$tcid] );
                           //write in DB
                           add_post_meta( $post_ID, $tckey, $mydata, true) or
                             update_post_meta( $post_ID, $tckey , $mydata);

                           //check if we are in the attachment screen AND slider unchecked
                           if( $tckey == 'slider_check_key' && esc_attr( $_POST[$tcid] ) == 0) {

                               //if we uncheck the attachement slider, looks for a previous entry and delete it.
                               //Important : we check if the slider has slides first!
                               if ( isset( $czr_options['tc_sliders'])) {
                                 foreach ( $czr_options['tc_sliders'] as $slider_name => $slider) {
                                   foreach ( $slider as $key => $tc_post) {
                                     //clean empty values if necessary
                                     if ( is_null( $czr_options['tc_sliders'][$slider_name][$key]))
                                        unset( $czr_options['tc_sliders'][$slider_name][$key]);
                                     //delete previous slider entries for this post
                                     if ( $tc_post == $post_ID )
                                        unset( $czr_options['tc_sliders'][$slider_name][$key]);
                                   }
                                 }
                               }
                               //update DB with clean option table
                               update_option( 'tc_theme_options' , $czr_options );

                           }//endif;

                        break;
                      }//end switchendif;
                  }//end if ( isset( $_POST[$tcid])) {
               }//end foreach
               //attachments
               if( $tc_post_type == 'attachment' )
                 $this->czr_fn_slide_save( $post_ID );

               do_action( "__after_ajax_save_slider_{$tc_post_type}", $_POST, $tc_slider_fields );
           }//function






  /*
  ----------------------------------------------------------------
  -------- AJAX CALL BACK FUNCTION (post and attachment) ---------
  ----------------------------------------------------------------
  */

  /**
   * Global slider ajax call back function : 1-Saves options and fields, 2-Renders
   * Used in post or attachment context => uses post_slider var to check the context
   * Works along with tc_ajax_slider.js
   * @package Customizr
   * @since Customizr 2.0
   */
     function czr_fn_slider_cb() {
      if ( !isset( $_POST['SliderCheckNonce']) ) {
        die();
      }
      $nonce = $_POST['SliderCheckNonce'];
      // check if the submitted nonce matches with the generated nonce we created earlier
      if ( !wp_verify_nonce( $nonce, 'tc-slider-check-nonce' ) ) {
        die();
      }

        Try{
        //get the post_id with the hidden input field
        $tc_post_id         = $_POST['tc_post_id'];

        //save $_POST var in DB
        $this->czr_fn_slider_ajax_save( $tc_post_id);

        //check if we are in the post or attachment screen and select the appropriate rendering
        //we use the post_slider var defined in tc_ajax_slider.js
        if ( isset( $_POST['tc_post_type'])) {
         if( $_POST['tc_post_type'] == 'post' ) {
           $this->czr_fn_get_post_slider_infos( $tc_post_id );
         }
         else {
           $this->czr_fn_get_attachment_slider_infos( $tc_post_id );
         }
        }
        //echo $_POST['slider_id'];
       } catch (Exception $e){
         exit;
       }
       exit;
     }






      /**
       * Loads the necessary scripts and stylesheets to display slider options
       * @package Customizr
       * @since Customizr 1.0
       * @hook czr_slider_metabox_added
       */
      function czr_fn_slider_admin_scripts( $post ) {

         $_min_version = ( !$this->_minify_resources ) ? '' : '.min';


         //load scripts only for creating and editing slides options in pages and posts
         if ( did_action( 'tc_attachment_metabox_added' ) ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
         }


         do_action( 'tc_enqueue_ajax_slider_before' );

         //ajax refresh for slider options
         wp_enqueue_script( 'czr_ajax_slider' ,
            sprintf('%1$sback/js/tc_ajax_slider%2$s.js' , CZR_BASE_URL . CZR_ASSETS_PREFIX, $_min_version ),
            array( 'jquery' ),
            ( defined('WP_DEBUG') && true === WP_DEBUG ) ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER,
            true
         );

         // Tips to declare javascript variables http://www.garyc40.com/2010/03/5-tips-for-using-ajax-in-wordpress/#bad-ways
         wp_localize_script( 'czr_ajax_slider' , 'SliderAjax' , array(
            // URL to wp-admin/admin-ajax.php to process the request
            //'ajaxurl'         => admin_url( 'admin-ajax.php' ),
            // generate a nonce with a unique ID "myajax-post-comment-nonce"
            // so that you can check it later when an AJAX request is sent
               'SliderNonce' => wp_create_nonce( 'tc-slider-nonce' ),
               'SliderCheckNonce' => wp_create_nonce( 'tc-slider-check-nonce' ),
            )
         );

         //thickbox
         wp_admin_css( 'thickbox' );
         add_thickbox();

         //sortable stuffs
         wp_enqueue_style( 'sortablecss' ,
            sprintf('%1$sback/css/tc_sortable%2$s.css' , CZR_BASE_URL . CZR_ASSETS_PREFIX, $_min_version )
         );

         //wp built-in color picker style and script
         //Access the global $wp_version variable to see which version of WordPress is installed.
         global $wp_version;

         //If the WordPress version is greater than or equal to 3.5, then load the new WordPress color picker.
         if ( 3.5 <= $wp_version ){
            //Both the necessary css and javascript have been registered already by WordPress, so all we have to do is load them with their handle.
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
             // load the minified version of custom script
            wp_enqueue_script( 'cp_demo-custom' ,
               sprintf('%1$sback/js/color-picker%2$s.js' , CZR_BASE_URL . CZR_ASSETS_PREFIX , $_min_version ),
               array( 'jquery' , 'wp-color-picker' ),
               true
            );
         }
         //If the WordPress version is less than 3.5 load the older farbtasic color picker.
         else {
            //As with wp-color-picker the necessary css and javascript have been registered already by WordPress, so all we have to do is load them with their handle.
            wp_enqueue_style( 'farbtastic' );
            wp_enqueue_script( 'farbtastic' );
            // load the minified version of custom script
            wp_enqueue_script(
              'cp_demo-custom' ,
              sprintf('%1$sback/js/color-picker%2$s.js' ,  CZR_BASE_URL . CZR_ASSETS_PREFIX, $_min_version ),
              array( 'jquery' , 'farbtastic' ),
              ( defined('WP_DEBUG') && true === WP_DEBUG ) ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER,
              true
            );
         }

         do_action( 'tc_enqueue_ajax_slider_after' );

      }

      /**
       * Loads the necessary scripts for the post formats metaboxes
       * @package Customizr
       * @since Customizr 4.0
       * @hook czr_post_formats_metabox_added
       */

      function czr_fn_post_formats_admin_scripts( $post ) {

         $_ext = $this->_minify_resources ? '.min.js' : '.js';

         wp_enqueue_script( 'czr-post-formats' ,
            sprintf('%1$sback/js/czr_post_formats%2$s' , CZR_BASE_URL . CZR_ASSETS_PREFIX, $_ext ),
            array( 'jquery', 'underscore' ),
            $this->_resouces_version,
            $in_footer = true

         );

         wp_localize_script( 'czr-post-formats',
            'CZRPostFormatsParams' ,
            array(
               'postFormatSections' => $this->czr_fn_get_post_meta_boxes_map(),
               'currentPostFormat' => get_post_format( $post ),
            )
         );

      }





  /*
  ----------------------------------------------------------------
  ------------- ATTACHMENT FIELDS FILTER IF WP < 3.5 -------------
  ----------------------------------------------------------------
  */
   function czr_fn_attachment_filter( $form_fields, $post = null) {
      $this->czr_fn_attachment_slider_box ( $post);
      return $form_fields;
   }


   function czr_fn_attachment_save_filter( $post, $attachment ) {
      if ( isset( $_POST['tc_post_id']))
      $postid = $_POST['tc_post_id'];

      $this->czr_fn_slide_save( $postid );

      return $post;
   }



   /*
   ----------------------------------------------------------------
   ---------------------- STATIC FIELDS VIEWS ---------------------
   ----------------------------------------------------------------
   */
      /**
      * Build title element html
      *
      * @package Customizr
      */
      public static function czr_fn_title_view( $args ) {

         $defaults = array(
            'title_tag'     => 'h4',
            'wrapper_class' => 'meta-box-item-title',
            'wrapper_tag'   => 'div',
            'title_text'    => '',
            'echo'          => 1,
            'boxed'         => 1,
         );

         $args    = wp_parse_args( $args, $defaults );
         extract($args);

         $content = sprintf( '<%1$s>%2$s</%1$s>', $title_tag, $title_text );

         $html    = $boxed ? CZR_meta_boxes::czr_fn_wrapper_view(
                        compact( 'content', 'wrapper_tag', 'wrapper_class')
                    ) : $content;

         if ( !$echo )
            return $html;

         echo $html;

      }


      /**
      * Build checkbox element html
      *
      * @package Customizr
      */
      public static function czr_fn_checkbox_view( $args ) {

         $defaults = array(
            'input_name'     => '',
            'input_class'    => 'czr-toggle-check__input',
            'input_state'    => '',
            'echo'          => 1,
            'boxed'         => 1,
            'input_type'     => 'checkbox',
            'input_value'    => '1',
            'content_before' => '',
         );

         $args = wp_parse_args( $args, $defaults );
         extract( $args );

         CZR_meta_boxes::czr_fn_generic_input_view( array_merge( $args, array(
            'content_before' => $content_before . '<input name="'. $input_name .'" type="hidden" value = "0" /><span class="czr-toggle-check">',
            'custom_args'    => checked( $input_state, $current = true, $c_echo = false),
            'content_after'  => '<span class="czr-toggle-check__track"></span><span class="czr-toggle-check__thumb"></span></span>'
         )));
      }



      /**
      * Build selectbox element html
      *
      * @package Customizr
      */
      public static function czr_fn_selectbox_view( $args ) {
         $defaults = array(
            'select_name'    => '',
            'select_class'   => '',
            'echo'          => 1,
            'boxed'         => 1,
            'content_before' => '',
            'content_after'  => '',
            'choices'        => array(),
            'selected'       => '',
            'wrapper_tag'   => 'div',
            'wrapper_class' => 'meta-box-item-content',
         );

         $args = wp_parse_args( $args, $defaults );
         extract($args);

         if ( !$choices ) return;

         $select_id = isset($select_id) ? $select_id : $select_name;

         $options_html = '';

         foreach( $choices as $key => $label )
            $options_html .= sprintf('<option value=%1$s %2$s>%3$s</option>',
            esc_attr( $key ),
            selected( $selected, esc_attr( $key ), $s_echo = false ),
            $label
         );

         $content = sprintf('<select name="%1$s" id ="%2$s">%3$s</select>',
            $select_name,
            $select_id,
            $options_html
         );

         $content = $content_before . $content . $content_after;

         $html    = $boxed ? CZR_meta_boxes::czr_fn_wrapper_view(
                        compact( 'content', 'wrapper_tag', 'wrapper_class')
                    ) : $content;

        $html     = !( isset($title) && is_array( $title ) && !empty( $title ) ) ? $html :
                        sprintf( "%s%s",
                           CZR_meta_boxes::czr_fn_title_view( array_merge($title, array( 'echo' => 0 ) ) ),
                           $html
                        );

        if ( !$echo )
         return $html;

        echo $html ;
      }


      /**
      * Build generic input element html
      *
      * @package Customizr
      */
      public static function czr_fn_generic_input_view( $args ) {
        $defaults = array(
         'input_name'     => '',
         'input_class'    => 'widefat',
         'input_type'     => 'text',
         'input_value'    => '0',
         'custom_args'    => '',
         'echo'          => 1,
         'boxed'         => 1,
         'content_before' => '',
         'content_after'  => '',
         'wrapper_tag'   => 'div',
         'wrapper_class' => 'meta-box-item-content',
        );

        $args = wp_parse_args( $args, $defaults );
        extract($args);

        $input_id = isset($input_id) ? $input_id : $input_name;

        $content = sprintf('<input name="%1$s" id="%2$s" value="%3$s" %4$s class="%5$s" type="%6$s" />',
            esc_attr( $input_name ),
            esc_attr( $input_id ),
            esc_attr( $input_value ),
            $custom_args,
            $input_class,
            $input_type
        );

        $content = $content_before . $content . $content_after;

        $html = $boxed ? CZR_meta_boxes::czr_fn_wrapper_view(
         compact( 'content', 'wrapper_tag', 'wrapper_class')
        ) : $content;

        $html = !( isset($title) && is_array( $title ) && !empty( $title ) ) ? $html :
           sprintf( "%s%s",
             CZR_meta_boxes::czr_fn_title_view( array_merge($title, array( 'echo' => 0 ) ) ),
             $html
         );

        if ( !$echo )
         return $html;

        echo $html;
      }


      /**
      * Build generic input element html
      *
      * @package Customizr
      */
      public static function czr_fn_textarea_view( $args ) {
        $defaults = array(
         'input_name'     => '',
         'input_class'    => 'widefat',
         'input_value'    => '0',
         'custom_args'    => '',
         'echo'          => 1,
         'boxed'         => 1,
         'content_before' => '',
         'content_after'  => '',
         'rows'          => '5',
         'cols'          => '40',
         'wrapper_tag'   => 'div',
         'wrapper_class' => 'meta-box-item-content',
        );

        $args = wp_parse_args( $args, $defaults );
        extract($args);

        $input_id = isset($input_id) ? $input_id : $input_name;

        $content = sprintf('<textarea name="%1$s" d="%2$s" %4$s class="%5$s" type="%6$s" rows="%6$s" cols="%7$s">%3$s</textarea>',
            esc_attr( $input_name ),
            esc_attr( $input_id ),
            esc_attr( $input_value ),
            $custom_args,
            $input_class,
            $rows,
            $cols
        );

        $content = $content_before . $content . $content_after;

        $html = $boxed ? CZR_meta_boxes::czr_fn_wrapper_view(
         compact( 'content', 'wrapper_tag', 'wrapper_class')
        ) : $content;

        $html = !( isset($title) && is_array( $title ) && !empty( $title ) ) ? $html :
           sprintf( "%s%s",
             CZR_meta_boxes::czr_fn_title_view( array_merge($title, array( 'echo' => 0 ) ) ),
             $html
         );

        if ( !$echo )
         return $html;

        echo $html ;
      }


      /**
      * Build generic content wrapper html
      *
      * @package Customizr
      */
      public static function czr_fn_wrapper_view( $args ) {
        $defaults = array(
         'wrapper_tag'   => 'div',
         'wrapper_class' => 'meta-box-item-content',
         'echo'         => false,
         'content'       => ''
        );

        $args = wp_parse_args( $args, $defaults );
        extract($args);

        $html = sprintf('<%1$s class="%2$s">%3$s</%1$s>',
         $wrapper_tag,
         $wrapper_class,
         $content
        );

        if ( !$echo )
         return $html;
        echo $html;
      }

   }//end of class
endif;

?>