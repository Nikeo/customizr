<?php
/**
* Defines filters and actions used in several templates/classes
*
*
* @package      Customizr
* @subpackage   classes
* @since        3.0
* @author       Nicolas GUILLAUME <nicolas@presscustomizr.com>
* @copyright    Copyright (c) 2013-2015, Nicolas GUILLAUME
* @link         http://presscustomizr.com/customizr
* @license      http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
if ( ! class_exists( 'CZR_utils' ) ) :
  class CZR_utils {

      //Access any method or var of the class with classname::$instance -> var or method():
      static $inst;
      static $instance;
      public $default_options;
      public $db_options;
      public $options;//not used in customizer context only
      public $is_customizing;
      public $tc_options_prefixes;

      public static $_theme_setting_list;

      function __construct () {
        self::$inst =& $this;
        self::$instance =& $this;

        //init properties
        add_action( 'after_setup_theme'       , array( $this , 'czr_fn_init_properties') );

        //IMPORTANT : this callback needs to be ran AFTER hu_init_properties.
        add_action( 'after_setup_theme'       , array( $this, 'czr_fn_cache_theme_setting_list' ), 100 );

        //Various WP filters for
        //content
        //thumbnails => parses image if smartload enabled
        //title
        add_action( 'wp_head'                 , array( $this , 'czr_fn_wp_filters') );

        //get all options
        add_filter( '__options'               , array( $this , 'czr_fn_get_theme_options' ), 10, 1);
        //get single option
        add_filter( '__get_option'            , array( $this , 'czr_fn_opt' ), 10, 2 );//deprecated

        //some useful filters
        add_filter( '__ID'                    , array( $this , 'czr_fn_id' ));//deprecated
        add_filter( '__screen_layout'         , array( $this , 'czr_fn_get_layout' ) , 10 , 2 );//deprecated
        add_filter( '__is_home'               , array( $this , 'czr_fn_is_home' ) );
        add_filter( '__is_home_empty'         , array( $this , 'czr_fn_is_home_empty' ) );
        add_filter( '__post_type'             , array( $this , 'czr_fn_get_post_type' ) );
        add_filter( '__is_no_results'         , array( $this , 'czr_fn_is_no_results') );
        add_filter( '__article_selectors'     , array( $this , 'czr_fn_article_selectors' ) );

        //social networks
        add_filter( '__get_socials'           , array( $this , 'czr_fn_get_social_networks' ), 10, 0 );

        //refresh the theme options right after the _preview_filter when previewing
        add_action( 'customize_preview_init'  , array( $this , 'czr_fn_customize_refresh_db_opt' ) );
      }

      /***************************
      * EARLY HOOKS
      ****************************/
      /**
      * Init CZR_utils class properties after_setup_theme
      * Fixes the bbpress bug : Notice: bbp_setup_current_user was called incorrectly. The current user is being initialized without using $wp->init()
      * czr_fn_get_default_options uses is_user_logged_in() => was causing the bug
      * hook : after_setup_theme
      *
      * @package Customizr
      * @since Customizr 3.2.3
      */
      function czr_fn_init_properties() {
        //all customizr theme options start by "tc_" by convention
        $this -> tc_options_prefixes = apply_filters('tc_options_prefixes', array('tc_') );
        $this -> is_customizing   = CZR___::$instance -> czr_fn_is_customizing();
        $this -> db_options       = false === get_option( CZR___::$tc_option_group ) ? array() : (array)get_option( CZR___::$tc_option_group );
        $this -> default_options  = $this -> czr_fn_get_default_options();
        $_trans                   = CZR___::czr_fn_is_pro() ? 'started_using_customizr_pro' : 'started_using_customizr';

        //What was the theme version when the user started to use Customizr?
        //new install = no options yet
        //very high duration transient, this transient could actually be an option but as per the themes guidelines, too much options are not allowed.
        if ( 1 >= count( $this -> db_options ) || ! esc_attr( get_transient( $_trans ) ) ) {
          set_transient(
            $_trans,
            sprintf('%s|%s' , 1 >= count( $this -> db_options ) ? 'with' : 'before', CUSTOMIZR_VER ),
            60*60*24*9999
          );
        }
      }


      /* ------------------------------------------------------------------------- *
       *  CACHE THE LIST OF THEME SETTINGS ONLY
      /* ------------------------------------------------------------------------- */
      //Fired in __construct()
      //Note : the 'sidebar-areas' setting is not listed in that list because registered specifically
      function czr_fn_cache_theme_setting_list() {
          if ( is_array(self::$_theme_setting_list) && ! empty( self::$_theme_setting_list ) )
            return;
          $_settings_map = CZR_utils_settings_map::$instance -> czr_fn_get_customizer_map( null, 'add_setting_control' );
          $_settings = array();
          foreach ( $_settings_map as $_id => $data ) {
              $_settings[] = $_id;
          }

          self::$_theme_setting_list = $_settings;
      }


      /**
      * hook : wp_head
      * @package Customizr
      * @since Customizr 3.3.0
      */
      function czr_fn_wp_filters() {
        add_filter( 'the_content'                         , array( $this , 'czr_fn_fancybox_content_filter' ) );
        /*
        * Smartload disabled for content retrieved via ajax
        */
        if ( apply_filters( 'tc_globally_enable_img_smart_load', ! $this -> czr_fn_is_ajax() && esc_attr( $this->czr_fn_opt( 'tc_img_smart_load' ) ) ) ) {
          add_filter( 'the_content'                       , array( $this , 'czr_fn_parse_imgs' ), PHP_INT_MAX );
          add_filter( 'tc_thumb_html'                     , array( $this , 'czr_fn_parse_imgs' ) );
        }
        add_filter( 'wp_title'                            , array( $this , 'czr_fn_wp_title' ), 10, 2 );
      }


      /**
      * hook : the_content
      * Inspired from Unveil Lazy Load plugin : https://wordpress.org/plugins/unveil-lazy-load/ by @marubon
      *
      * @return string
      * @package Customizr
      * @since Customizr 3.3.0
      */
      function czr_fn_parse_imgs( $_html ) {
        $_bool = is_feed() || is_preview() || ( wp_is_mobile() && apply_filters('tc_disable_img_smart_load_mobiles', false ) );

        if ( apply_filters( 'tc_disable_img_smart_load', $_bool, current_filter() ) )
          return $_html;

        $allowed_image_extentions = apply_filters( 'tc_smartload_allowed_img_extensions', array(
          'bmp',
          'gif',
          'jpeg',
          'jpg',
          'jpe',
          'tif',
          'tiff',
          'ico',
          'png',
          'svg',
          'svgz'
        ) );

        if ( empty( $allowed_image_extentions ) || ! is_array( $allowed_image_extentions ) ) {
          return $_html;
        }

        $img_extensions_pattern = sprintf( "[%s]", implode( '|', $allowed_image_extentions ) );

        return preg_replace_callback('#<img([^>]+?)src=[\'"]?([^\'"\s>]+.'.$img_extensions_pattern.'[^\'"\s>]*)[\'"]?([^>]*)>#i', array( $this , 'czr_fn_regex_callback' ) , $_html);
      }


      /**
      * callback of preg_replace_callback in tc_parse_imgs
      * Inspired from Unveil Lazy Load plugin : https://wordpress.org/plugins/unveil-lazy-load/ by @marubon
      *
      * @return string
      * @package Customizr
      * @since Customizr 3.3.0
      */
      private function czr_fn_regex_callback( $matches ) {
        $_placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        if ( false !== strpos( $matches[0], 'data-src' ) || preg_match('/ data-smartload *= *"false" */', $matches[0]) ) {
          return $matches[0];
        } else {
          return apply_filters( 'tc_img_smartloaded',
            str_replace( array('srcset=', 'sizes='), array('data-srcset=', 'data-sizes='),
                sprintf('<img %1$s src="%2$s" data-src="%3$s" %4$s>',
                    $matches[1],
                    $_placeholder,
                    $matches[2],
                    $matches[3]
                )
            )
          );
        }
      }




      /**
      * Returns the current skin's primary color
      *
      * @package Customizr
      * @since Customizr 3.1.23
      */
      function czr_fn_get_skin_color( $_what = null ) {
        $_color_map    = CZR_init::$instance -> skin_color_map;
        $_color_map    = ( is_array($_color_map) ) ? $_color_map : array();

        $_active_skin =  str_replace('.min.', '.', basename( CZR_init::$instance -> czr_fn_get_style_src() ) );
        //falls back to grey.css array( '#5A5A5A', '#343434' ) if not defined
        $_to_return = array( '#5A5A5A', '#343434' );

        switch ($_what) {
          case 'all':
            $_to_return = $_color_map;
          break;

          case 'pair':
            $_to_return = ( false != $_active_skin && array_key_exists( $_active_skin, $_color_map ) && is_array( $_color_map[$_active_skin] ) ) ? $_color_map[$_active_skin] : $_to_return;
          break;

          default:
            $_to_return = ( false != $_active_skin && isset($_color_map[$_active_skin][0]) ) ? $_color_map[$_active_skin][0] : $_to_return[0];
          break;
        }
        return apply_filters( 'tc_get_skin_color' , $_to_return , $_what );
      }




      /**
      * Helper
      * Returns whether or not the option is a theme/addon option
      *
      * @return bool
      *
      * @package Customizr
      * @since Customizr 3.4.9
      */
      function czr_fn_is_customizr_option( $option_key ) {
        $_is_tc_option = in_array( substr( $option_key, 0, 3 ), $this -> tc_options_prefixes );
        return apply_filters( 'tc_is_customizr_option', $_is_tc_option , $option_key );
      }



     /**
      * Returns the default options array
      *
      * @package Customizr
      * @since Customizr 3.1.11
      */
      function czr_fn_get_default_options() {
        $_db_opts     = empty($this -> db_options) ? $this -> czr_fn_cache_db_options() : $this -> db_options;
        $def_options  = isset($_db_opts['defaults']) ? $_db_opts['defaults'] : array();

        //Don't update if default options are not empty + customizing context
        //customizing out ? => we can assume that the user has at least refresh the default once (because logged in, see conditions below) before accessing the customizer
        //customzing => takes into account if user has set a filter or added a new customizer setting
        if ( ! empty($def_options) && $this -> is_customizing )
          return apply_filters( 'tc_default_options', $def_options );

        //Always update/generate the default option when (OR) :
        // 1) current user can edit theme options
        // 2) they are not defined
        // 3) theme version not defined
        // 4) versions are different
        if ( current_user_can('edit_theme_options') || empty($def_options) || ! isset($def_options['ver']) || 0 != version_compare( $def_options['ver'] , CUSTOMIZR_VER ) ) {
          $def_options          = $this -> czr_fn_generate_default_options( CZR_utils_settings_map::$instance -> czr_fn_get_customizer_map( $get_default_option = 'true' ) , 'tc_theme_options' );
          //Adds the version in default
          $def_options['ver']   =  CUSTOMIZR_VER;

          //writes the new value in db (merging raw options with the new defaults ).
          $this -> czr_fn_set_option( 'defaults', $def_options, 'tc_theme_options' );
        }
        return apply_filters( 'tc_default_options', $def_options );
      }




      /**
      * Generates the default options array from a customizer map + add slider option
      *
      * @package Customizr
      * @since Customizr 3.0.3
      */
      function czr_fn_generate_default_options( $map, $option_group = null ) {
        //do we have to look in a specific group of option (plugin?)
        $option_group   = is_null($option_group) ? 'tc_theme_options' : $option_group;

        //initialize the default array with the sliders options
        $defaults = array();

        foreach ($map['add_setting_control'] as $key => $options) {
          //check it is a customizr option
          if(  ! $this -> czr_fn_is_customizr_option( $key ) )
            continue;

          $option_name = $key;
          //write default option in array
          if( isset($options['default']) )
            $defaults[$option_name] = ( 'checkbox' == $options['type'] ) ? (bool) $options['default'] : $options['default'];
          else
            $defaults[$option_name] = null;
        }//end foreach

        return $defaults;
      }




      /**
      * Get the saved options in Customizer Screen, merge them with the default theme options array and return the updated global options array
      * @package Customizr
      * @since Customizr 1.0
      *
      */
      function czr_fn_get_theme_options ( $option_group = null ) {
          //do we have to look in a specific group of option (plugin?)
          $option_group       = is_null($option_group) ? CZR___::$tc_option_group : $option_group;
          $saved              = empty($this -> db_options) ? $this -> czr_fn_cache_db_options() : $this -> db_options;
          $defaults           = $this -> default_options;
          $__options          = wp_parse_args( $saved, $defaults );
          //$__options        = array_intersect_key( $__options, $defaults );
        return $__options;
      }


      /**
      * Returns an option from the options array of the theme.
      *
      * @package Customizr
      * @since Customizr 1.0
      */
      function czr_fn_opt( $option_name , $option_group = null, $use_default = true ) {
        //do we have to look for a specific group of option (plugin?)
        $option_group = is_null($option_group) ? CZR___::$tc_option_group : $option_group;
        //when customizing, the db_options property is refreshed each time the preview is refreshed in 'customize_preview_init'
        $_db_options  = empty($this -> db_options) ? $this -> czr_fn_cache_db_options() : $this -> db_options;

        //do we have to use the default ?
        $__options    = $_db_options;
        $_default_val = false;
        if ( $use_default ) {
          $_defaults      = $this -> default_options;
          if ( isset($_defaults[$option_name]) )
            $_default_val = $_defaults[$option_name];
          $__options      = wp_parse_args( $_db_options, $_defaults );
        }

        //assign false value if does not exist, just like WP does
        $_single_opt    = isset($__options[$option_name]) ? $__options[$option_name] : false;

        //ctx retro compat => falls back to default val if ctx like option detected
        //important note : some options like tc_slider are not concerned by ctx
        if ( ! $this -> czr_fn_is_option_excluded_from_ctx( $option_name ) ) {
          if ( is_array( $_single_opt ) && ! class_exists( 'CZR_contx' ) )
            $_single_opt = $_default_val;
        }

        //allow contx filtering globally
        $_single_opt = apply_filters( "tc_opt" , $_single_opt , $option_name , $option_group, $_default_val );

        //allow single option filtering
        return apply_filters( "tc_opt_{$option_name}" , $_single_opt , $option_name , $option_group, $_default_val );
      }



      /**
      * The purpose of this callback is to refresh and store the theme options in a property on each customize preview refresh
      * => preview performance improvement
      * 'customize_preview_init' is fired on wp_loaded, once WordPress is fully loaded ( after 'init', before 'wp') and right after the call to 'customize_register'
      * This method is fired just after the theme option has been filtered for each settings by the WP_Customize_Setting::_preview_filter() callback
      * => if this method is fired before this hook when customizing, the user changes won't be taken into account on preview refresh
      *
      * hook : customize_preview_init
      * @return  void
      *
      * @since  v3.4+
      */
      function czr_fn_customize_refresh_db_opt(){
        $this -> db_options = false === get_option( CZR___::$tc_option_group ) ? array() : (array)get_option( CZR___::$tc_option_group );
      }



      /**
      * Set an option value in the theme option group
      * @param $option_name : string ( like tc_skin )
      * @param $option_value : sanitized option value, can be a string, a boolean or an array
      * @param $option_group : string ( like tc_theme_options )
      * @return  void
      *
      * @package Customizr
      * @since Customizr 3.4+
      */
      function czr_fn_set_option( $option_name , $option_value, $option_group = null ) {
        $option_group           = is_null($option_group) ? CZR___::$tc_option_group : $option_group;
        /*
        * Get raw theme options:
        * avoid filtering
        * avoid merging with defaults
        */
        $_options               = czr_fn_get_raw_option( $option_group );
        $_options[$option_name] = $option_value;

        update_option( $option_group, $_options );
      }



      /**
      * In live context (not customizing || admin) cache the theme options
      *
      * @package Customizr
      * @since Customizr 3.2.0
      */
      function czr_fn_cache_db_options($opt_group = null) {
        $opts_group = is_null($opt_group) ? CZR___::$tc_option_group : $opt_group;
        $this -> db_options = false === get_option( $opt_group ) ? array() : (array)get_option( $opt_group );
        return $this -> db_options;
      }




      /**
      * Returns the "real" queried post ID or if !isset, get_the_ID()
      * Checks some contextual booleans
      *
      * @package Customizr
      * @since Customizr 1.0
      */
      public static function czr_fn_id()  {
        if ( in_the_loop() ) {
          $tc_id            = get_the_ID();
        } else {
          global $post;
          $queried_object   = get_queried_object();
          $tc_id            = ( ! empty ( $post ) && isset($post -> ID) ) ? $post -> ID : null;
          $tc_id            = ( isset ($queried_object -> ID) ) ? $queried_object -> ID : $tc_id;
        }
        $tc_id  = ( is_404() || is_search() || is_archive() ) ? null : $tc_id;
        return apply_filters( 'tc_id', $tc_id );
      }




      /**
      * This function returns the layout (sidebar(s), or full width) to apply to a context
      *
      * @package Customizr
      * @since Customizr 1.0
      */
      public static function czr_fn_get_layout( $post_id , $sidebar_or_class = 'class' ) {
          $__options                    = czr_fn__f ( '__options' );

          //Article wrapper class definition
          $global_layout                = apply_filters( 'tc_global_layout' , CZR_init::$instance -> global_layout );

          /* DEFAULT LAYOUTS */
          //what is the default layout we want to apply? By default we apply the global default layout
          $tc_sidebar_default_layout    = esc_attr( $__options['tc_sidebar_global_layout'] );

          //checks if the 'force default layout' option is checked and return the default layout before any specific layout
          if( isset($__options['tc_sidebar_force_layout']) && 1 == $__options['tc_sidebar_force_layout'] ) {
            $class_tab  = $global_layout[$tc_sidebar_default_layout];
            $class_tab  = $class_tab['content'];
            $tc_screen_layout = array(
              'sidebar' => $tc_sidebar_default_layout,
              'class'   => $class_tab
            );
            return $tc_screen_layout[$sidebar_or_class];
          }

          global $wp_query, $post;
          $tc_specific_post_layout    = false;
          $is_singular_layout         = false;

          if ( apply_filters( 'tc_is_post_layout', is_single( $post_id ), $post_id ) ) {
            $tc_sidebar_default_layout  = esc_attr( $__options['tc_sidebar_post_layout'] );
            $is_singular_layout = true;
          }
          elseif ( apply_filters( 'tc_is_page_layout', is_page( $post_id ), $post_id ) ) {
            $tc_sidebar_default_layout  = esc_attr( $__options['tc_sidebar_page_layout'] );
            $is_singular_layout = true;
          }


          //builds the default layout option array including layout and article class
          $class_tab  = $global_layout[$tc_sidebar_default_layout];
          $class_tab  = $class_tab['content'];
          $tc_screen_layout             = array(
                      'sidebar' => $tc_sidebar_default_layout,
                      'class'   => $class_tab
          );

          //The following lines set the post specific layout if any, and if not keeps the default layout previously defined
          $tc_specific_post_layout    = false;

          //if we are displaying an attachement, we use the parent post/page layout
          if ( isset($post) && is_singular() && 'attachment' == $post->post_type ) {
            $tc_specific_post_layout  = esc_attr( get_post_meta( $post->post_parent , $key = 'layout_key' , $single = true ) );
          }
          //for a singular post or page OR for the posts page
          elseif ( $is_singular_layout || is_singular() || $wp_query -> is_posts_page )
            $tc_specific_post_layout  = esc_attr( get_post_meta( $post_id, $key = 'layout_key' , $single = true ) );


          //checks if we display home page, either posts or static page and apply the customizer option
          if( ( is_home() && 'posts' == get_option( 'show_on_front' ) ) || is_front_page() ) {
             $tc_specific_post_layout = $__options['tc_front_layout'];
          }

          if( $tc_specific_post_layout ) {
              $class_tab  = $global_layout[$tc_specific_post_layout];
              $class_tab  = $class_tab['content'];
              $tc_screen_layout = array(
              'sidebar' => $tc_specific_post_layout,
              'class'   => $class_tab
            );
          }

          return apply_filters( 'tc_screen_layout' , $tc_screen_layout[$sidebar_or_class], $post_id , $sidebar_or_class );
      }







      /**
       * Add an optional rel="tc-fancybox[]" attribute to all images embedded in a post.
       *
       * @package Customizr
       * @since Customizr 2.0.7
       */
      function czr_fn_fancybox_content_filter( $content) {
        $tc_fancybox = esc_attr( CZR_utils::$inst->czr_fn_opt( 'tc_fancybox' ) );

        if ( 1 != $tc_fancybox )
          return $content;

        global $post;
        if ( ! isset($post) )
          return $content;

        $pattern ="/<a(.*?)href=( '|\")(.*?).(bmp|gif|jpeg|jpg|png)( '|\")(.*?)>/i";
        $replacement = '<a$1href=$2$3.$4$5 class="grouped_elements" rel="tc-fancybox-group'.$post -> ID.'"$6>';
        $r_content = preg_replace( $pattern, $replacement, $content);
        $content = $r_content ? $r_content : $content;
        return apply_filters( 'tc_fancybox_content_filter', $content );
      }




      /**
      * Title element formating
      *
      * @since Customizr 2.1.6
      *
      */
      function czr_fn_wp_title( $title, $sep ) {
        if ( function_exists( '_wp_render_title_tag' ) )
          return $title;

        global $paged, $page;

        if ( is_feed() )
          return $title;

        // Add the site name.
        $title .= get_bloginfo( 'name' );

        // Add the site description for the home/front page.
        $site_description = get_bloginfo( 'description' , 'display' );
        if ( $site_description && czr_fn__f('__is_home') )
          $title = "$title $sep $site_description";

        // Add a page number if necessary.
        if ( $paged >= 2 || $page >= 2 )
          $title = "$title $sep " . sprintf( __( 'Page %s' , 'customizr' ), max( $paged, $page ) );

        return $title;
      }




      /**
      * Check if we are really on home, all cases covered
      */
      function czr_fn_is_home() {
          //get info whether the front page is a list of last posts or a page
          return ( is_home() && ( 'posts' == get_option( 'show_on_front' ) || 'nothing' == get_option( 'show_on_front' ) ) )
            || ( 0 == get_option( 'page_on_front' ) && 'page' == get_option( 'show_on_front' ) )//<= this is the case when the user want to display a page on home but did not pick a page yet
            || is_front_page();
      }


      /**
      * Check if we show posts or page content on home page
      *
      * @since Customizr 3.0.6
      *
      */
      function czr_fn_is_home_empty() {
        //check if the users has choosen the "no posts or page" option for home page
        return ( ( is_home() || is_front_page() ) && 'nothing' == get_option( 'show_on_front' ) ) ? true : false;
      }




      /**
      * Return object post type
      *
      * @since Customizr 3.0.10
      *
      */
      function czr_fn_get_post_type() {
        global $post;

        if ( ! isset($post) )
          return;

        return $post -> post_type;
      }






      /**
      * Returns the classes for the post div.
      *
      * @param string|array $class One or more classes to add to the class list.
      * @param int $post_id An optional post ID.
      * @package Customizr
      * @since 3.0.10
      */
      function czr_fn_get_post_class( $class = '', $post_id = null ) {
        //Separates classes with a single space, collates classes for post DIV
        return 'class="' . join( ' ', get_post_class( $class, $post_id ) ) . '"';
      }






      /**
      * Boolean : check if we are in the no search results case
      *
      * @package Customizr
      * @since 3.0.10
      */
      function czr_fn_is_no_results() {
        global $wp_query;
        return ( is_search() && 0 == $wp_query -> post_count ) ? true : false;
      }





      /**
      * Displays the selectors of the article depending on the context
      *
      * @package Customizr
      * @since 3.1.0
      */
      function czr_fn_article_selectors() {

        //gets global vars
        global $post;
        global $wp_query;

        //declares selector var
        $selectors                  = '';

        // SINGLE POST
        $single_post_selector_bool  = isset($post) && 'page' != $post -> post_type && 'attachment' != $post -> post_type && is_singular();
        $selectors                  = $single_post_selector_bool ? apply_filters( 'tc_single_post_selectors' ,'id="post-'.get_the_ID().'" '.$this -> czr_fn_get_post_class('row-fluid') ) : $selectors;

        // POST LIST
        $post_list_selector_bool    = ( isset($post) && !is_singular() && !is_404() && !czr_fn__f( '__is_home_empty') ) || ( is_search() && 0 != $wp_query -> post_count );
        $selectors                  = $post_list_selector_bool ? apply_filters( 'tc_post_list_selectors' , 'id="post-'.get_the_ID().'" '.$this -> czr_fn_get_post_class('row-fluid') ) : $selectors;

        // PAGE
        $page_selector_bool         = isset($post) && 'page' == czr_fn__f('__post_type') && is_singular() && !czr_fn__f( '__is_home_empty');
        $selectors                  = $page_selector_bool ? apply_filters( 'tc_page_selectors' , 'id="page-'.get_the_ID().'" '.$this -> czr_fn_get_post_class('row-fluid') ) : $selectors;

        // ATTACHMENT
        //checks if attachement is image and add a selector
        $format_image               = wp_attachment_is_image() ? 'format-image' : '';
        $selectors                  = ( isset($post) && 'attachment' == $post -> post_type && is_singular() ) ? apply_filters( 'tc_attachment_selectors' , 'id="post-'.get_the_ID().'" '.$this -> czr_fn_get_post_class(array('row-fluid', $format_image) ) ) : $selectors;

        // NO SEARCH RESULTS
        $selectors                  = ( is_search() && 0 == $wp_query -> post_count ) ? apply_filters( 'tc_no_results_selectors' , 'id="post-0" class="post error404 no-results not-found row-fluid"' ) : $selectors;

        // 404
        $selectors                  = is_404() ? apply_filters( 'tc_404_selectors' , 'id="post-0" class="post error404 no-results not-found row-fluid"' ) : $selectors;

        echo apply_filters( 'tc_article_selectors', $selectors );

      }//end of function





      /**
      * Gets the social networks list defined in customizer options
      *
      *
      *
      * @package Customizr
      * @since Customizr 3.0.10
      *
      * @since Customizr 3.4.55 Added the ability to retrieve them as array
      * @param $output_type optional. Return type "string" or "array"
      */
      //MODEL LOOKS LIKE THIS
      //(
      //     [0] => Array
      //         (
      //             [is_mod_opt] => 1
      //             [module_id] => tc_social_links_czr_module
      //             [social-size] => 15
      //         )

      //     [1] => Array
      //         (
      //             [id] => czr_social_module_0
      //             [title] => Follow us on Renren
      //             [social-icon] => fa-renren
      //             [social-link] => http://customizr-dev.dev/feed/rss/
      //             [social-color] => #6d4c8e
      //             [social-target] => 1
      //         )
      // )
      function czr_fn_get_social_networks( $output_type = 'string' ) {

          $_socials         = $this -> czr_fn_opt('tc_social_links');
          $_default_color   = array('rgb(90,90,90)', '#5a5a5a'); //both notations
          $_default_size    = '14'; //px

          $_social_opts     = array( 'social-size' => $_default_size );

          if ( empty( $_socials ) )
            return;

          //get the social mod opts
          foreach( $_socials as $key => $item ) {
            if ( ! array_key_exists( 'is_mod_opt', $item ) )
              continue;
            $_social_opts = wp_parse_args( $item, $_social_opts );
          }
          $font_size_value = $_social_opts['social-size'];
          //if the size is the default one, do not add the inline style css
          $social_size_css  = empty( $font_size_value ) || $_default_size == $font_size_value ? '' : "font-size:{$font_size_value}px";

          $_social_links = array();
          foreach( $_socials as $key => $item ) {
              //skip if mod_opt
              if ( array_key_exists( 'is_mod_opt', $item ) )
                continue;

              //get the social icon suffix for backward compatibility (users custom CSS) we still add the class icon-*
              $icon_class            = isset($item['social-icon']) ? esc_attr($item['social-icon']) : '';
              $link_icon_class       = 'fa-' === substr( $icon_class, 0, 3 ) && 3 < strlen( $icon_class ) ?
                      ' icon-' . str_replace( array('rss', 'envelope'), array('feed', 'mail'), substr( $icon_class, 3, strlen($icon_class) ) ) :
                      '';

              /* Maybe build inline style */
              $social_color_css      = isset($item['social-color']) ? esc_attr($item['social-color']) : $_default_color[0];
              //if the color is the default one, do not print the inline style css
              $social_color_css      = in_array( $social_color_css, $_default_color ) ? '' : "color:{$social_color_css}";
              $style_props           = implode( ';', array_filter( array( $social_color_css, $social_size_css ) ) );

              $style_attr            = $style_props ? sprintf(' style="%1$s"', $style_props ) : '';

              array_push( $_social_links, sprintf('<a rel="nofollow" class="social-icon%6$s" %1$s title="%2$s" aria-label="%2$s" href="%3$s" %4$s %7$s><i class="fa %5$s"></i></a>',
                //do we have an id set ?
                //Typically not if the user still uses the old options value.
                //So, if the id is not present, let's build it base on the key, like when added to the collection in the customizer

                // Put them together
                  ! CZR___::$instance -> czr_fn_is_customizing() ? '' : sprintf( 'data-model-id="%1$s"', ! isset( $item['id'] ) ? 'czr_socials_'. $key : $item['id'] ),
                  isset($item['title']) ? esc_attr( $item['title'] ) : '',
                  ( isset($item['social-link']) && ! empty( $item['social-link'] ) ) ? esc_url( $item['social-link'] ) : 'javascript:void(0)',
                  ( isset($item['social-target']) && false != $item['social-target'] ) ? ' target="_blank"' : '',
                  $icon_class,
                  $link_icon_class,
                  $style_attr
              ) );
          }

          /*
          * return
          */
          switch ( $output_type ) :
            case 'array' : return $_social_links;
            default      : return implode( '', $_social_links );
          endswitch;
      }


    /**
    * Retrieve the file type from the file name
    * Even when it's not at the end of the file
    * copy of wp_check_filetype() in wp-includes/functions.php
    *
    * @since 3.2.3
    *
    * @param string $filename File name or path.
    * @param array  $mimes    Optional. Key is the file extension with value as the mime type.
    * @return array Values with extension first and mime type.
    */
    function czr_fn_check_filetype( $filename, $mimes = null ) {
      $filename = basename( $filename );
      if ( empty($mimes) )
        $mimes = get_allowed_mime_types();
      $type = false;
      $ext = false;
      foreach ( $mimes as $ext_preg => $mime_match ) {
        $ext_preg = '!\.(' . $ext_preg . ')!i';
        //was ext_preg = '!\.(' . $ext_preg . ')$!i';
        if ( preg_match( $ext_preg, $filename, $ext_matches ) ) {
          $type = $mime_match;
          $ext = $ext_matches[1];
          break;
        }
      }

      return compact( 'ext', 'type' );
    }

    /**
    * Check whether a category exists.
    * (wp category_exists isn't available in pre_get_posts)
    * @since 3.4.10
    *
    * @see term_exists()
    *
    * @param int $cat_id.
    * @return bool
    */
    public function czr_fn_category_id_exists( $cat_id ) {
      return term_exists( (int) $cat_id, 'category');
    }



    /**
    * @return a date diff object
    * @uses  date_diff if php version >=5.3.0, instantiates a fallback class if not
    *
    * @since 3.2.8
    *
    * @param date one object.
    * @param date two object.
    */
    private function czr_fn_date_diff( $_date_one , $_date_two ) {
      //if version is at least 5.3.0, use date_diff function
      if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0) {
        return date_diff( $_date_one , $_date_two );
      } else {
        $_date_one_timestamp   = $_date_one->format("U");
        $_date_two_timestamp   = $_date_two->format("U");
        return new CZR_DateInterval( $_date_two_timestamp - $_date_one_timestamp );
      }
    }



    /**
    * Return boolean OR number of days since last update OR PHP version < 5.2
    *
    * @package Customizr
    * @since Customizr 3.2.6
    */
    function czr_fn_post_has_update( $_bool = false) {
      //php version check for DateTime
      //http://php.net/manual/fr/class.datetime.php
      if ( version_compare( PHP_VERSION, '5.2.0' ) < 0 )
        return false;

      //first proceed to a date check
      $dates_to_check = array(
        'created'   => get_the_date('Y-m-d g:i:s'),
        'updated'   => get_the_modified_date('Y-m-d g:i:s'),
        'current'   => date('Y-m-d g:i:s')
      );
      //ALL dates must be valid
      if ( 1 != array_product( array_map( array($this , 'czr_fn_is_date_valid') , $dates_to_check ) ) )
        return false;

      //Import variables into the current symbol table
      extract($dates_to_check);

      //Instantiate the different date objects
      $created                = new DateTime( $created );
      $updated                = new DateTime( $updated );
      $current                = new DateTime( $current );

      $created_to_updated     = $this -> czr_fn_date_diff( $created , $updated );
      $updated_to_today       = $this -> czr_fn_date_diff( $updated, $current );

      if ( true === $_bool )
        //return ( 0 == $created_to_updated -> days && 0 == $created_to_updated -> s ) ? false : true;
        return ( $created_to_updated -> s > 0 || $created_to_updated -> i > 0 ) ? true : false;
      else
        //return ( 0 == $created_to_updated -> days && 0 == $created_to_updated -> s ) ? false : $updated_to_today -> days;
        return ( $created_to_updated -> s > 0 || $created_to_updated -> i > 0 ) ? $updated_to_today -> days : false;
    }



    /*
    * @return boolean
    * http://stackoverflow.com/questions/11343403/php-exception-handling-on-datetime-object
    */
    private function czr_fn_is_date_valid($str) {
      if ( ! is_string($str) )
         return false;

      $stamp = strtotime($str);
      if ( ! is_numeric($stamp) )
         return false;

      if ( checkdate(date('m', $stamp), date('d', $stamp), date('Y', $stamp)) )
         return true;

      return false;
    }



    /**
    * @return an array of font name / code OR a string of the font css code
    * @parameter string name or google compliant suffix for href link
    *
    * @package Customizr
    * @since Customizr 3.2.9
    */
    function czr_fn_get_font( $_what = 'list' , $_requested = null ) {
      $_to_return = ( 'list' == $_what ) ? array() : false;
      $_font_groups = apply_filters(
        'tc_font_pairs',
        CZR_init::$instance -> font_pairs
      );
      foreach ( $_font_groups as $_group_slug => $_font_list ) {
        if ( 'list' == $_what ) {
          $_to_return[$_group_slug] = array();
          $_to_return[$_group_slug]['list'] = array();
          $_to_return[$_group_slug]['name'] = $_font_list['name'];
        }

        foreach ( $_font_list['list'] as $slug => $data ) {
          switch ($_requested) {
            case 'name':
              if ( 'list' == $_what )
                $_to_return[$_group_slug]['list'][$slug] =  $data[0];
            break;

            case 'code':
              if ( 'list' == $_what )
                $_to_return[$_group_slug]['list'][$slug] =  $data[1];
            break;

            default:
              if ( 'list' == $_what )
                $_to_return[$_group_slug]['list'][$slug] = $data;
              else if ( $slug == $_requested ) {
                  return $data[1];
              }
            break;
          }
        }
      }
      return $_to_return;
    }



    /**
    * Returns a boolean
    * check if user started to use the theme before ( strictly < ) the requested version
    *
    * @package Customizr
    * @since Customizr 3.2.9
    */
    function czr_fn_user_started_before_version( $_czr_ver, $_pro_ver = null ) {
      $_ispro = CZR___::czr_fn_is_pro();
      //the transient is set in ::czr_fn_init_properties()
      $_trans = $_ispro ? 'started_using_customizr_pro' : 'started_using_customizr';

      if ( ! get_transient( $_trans ) )
        return false;

      $_ver   = $_ispro ? $_pro_ver : $_czr_ver;
      if ( ! is_string( $_ver ) )
        return false;

      $_start_version_infos = explode('|', esc_attr( get_transient( $_trans ) ) );

      if ( ! is_array( $_start_version_infos ) )
        return false;

      switch ( $_start_version_infos[0] ) {
        //in this case with now exactly what was the starting version (most common case)
        case 'with':
          return isset( $_start_version_infos[1] ) ? version_compare( $_start_version_infos[1] , $_ver, '<' ) : true;
        break;
        //here the user started to use the theme before, we don't know when.
        //but this was actually before this check was created
        case 'before':
          return true;
        break;

        default :
          return false;
        break;
      }
    }


    /**
    * Boolean helper to check if the secondary menu is enabled
    * since v3.4+
    */
    function czr_fn_is_secondary_menu_enabled() {
      return (bool) esc_attr( CZR_utils::$inst->czr_fn_opt( 'tc_display_second_menu' ) ) && 'aside' == esc_attr( CZR_utils::$inst->czr_fn_opt( 'tc_menu_style' ) );
    }



    /***************************
    * CTX COMPAT
    ****************************/
    /**
    * Helper : define a set of options not impacted by ctx like tc_slider, last_update_notice.
    * @return  array of excluded option names
    */
    function czr_fn_get_ctx_excluded_options() {
      return apply_filters(
        'tc_get_ctx_excluded_options',
        array(
          'defaults',
          'tc_sliders',
          'tc_social_links',
          'tc_blog_restrict_by_cat',
          'last_update_notice',
          'last_update_notice_pro',
          '__moved_opts'
        )
      );
    }


    /**
    * Boolean helper : tells if this option is excluded from the ctx treatments.
    * @return bool
    */
    function czr_fn_is_option_excluded_from_ctx( $opt_name ) {
      return in_array( $opt_name, $this -> czr_fn_get_ctx_excluded_options() );
    }


    /**
    * Returns the url of the customizer with the current url arguments + an optional customizer section args
    *
    * @param $autofocus(optional) is an array indicating the elements to focus on ( control,section,panel).
    * Ex : array( 'control' => 'tc_front_slider', 'section' => 'frontpage_sec').
    * Wordpress will cycle among autofocus keys focusing the existing element - See wp-admin/customize.php.
    * The actual focused element depends on its type according to this priority scale: control, section, panel.
    * In this sense when specifying a control, additional section and panel could be considered as fall-back.
    *
    * @param $control_wrapper(optional) is a string indicating the wrapper to apply to the passed control. By default is "tc_theme_options".
    * Ex: passing $aufocus = array('control' => 'tc_front_slider') will produce the query arg 'autofocus'=>array('control' => 'tc_theme_options[tc_front_slider]'
    *
    * @return url string
    * @since Customizr 3.4+
    */
    static function czr_fn_get_customizer_url( $autofocus = null, $control_wrapper = 'tc_theme_options' ) {
      $_current_url       = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
      $_customize_url     = add_query_arg( 'url', urlencode( $_current_url ), wp_customize_url() );
      $autofocus  = ( ! is_array($autofocus) || empty($autofocus) ) ? null : $autofocus;

      if ( is_null($autofocus) )
        return $_customize_url;

      $_ordered_keys = array( 'control', 'section', 'panel');

      // $autofocus must contain at least one key among (control,section,panel)
      if ( ! count( array_intersect( array_keys($autofocus), $_ordered_keys ) ) )
        return $_customize_url;

      // $autofocus must contain at least one key among (control,section,panel)
      if ( ! count( array_intersect( array_keys($autofocus), array( 'control', 'section', 'panel') ) ) )
        return $_customize_url;

      // wrap the control in the $control_wrapper if neded
      if ( array_key_exists( 'control', $autofocus ) && ! empty( $autofocus['control'] ) && $control_wrapper ){
        $autofocus['control'] = $control_wrapper . '[' . $autofocus['control'] . ']';
      }

      //Since wp 4.6.1 we order the params following the $_ordered_keys order
      $autofocus = array_merge( array_filter( array_flip( $_ordered_keys ), '__return_false'), $autofocus );

      if ( ! empty( $autofocus ) ) {
        //here we pass the first element of the array
        // We don't really have to care for not existent autofocus keys, wordpress will stash them when passing the values to the customize js
        return add_query_arg( array( 'autofocus' => array_slice( $autofocus, 0, 1 ) ), $_customize_url );
      } else
        return $_customize_url;
    }


    /**
    * Is there a menu assigned to a given location ?
    * Used in class-header-menu and class-fire-placeholders
    * @return bool
    * @since  v3.4+
    */
    function czr_fn_has_location_menu( $_location ) {
      $_all_locations  = get_nav_menu_locations();
      return isset($_all_locations[$_location]) && is_object( wp_get_nav_menu_object( $_all_locations[$_location] ) );
    }

    /**
    * Whether or not we are in the ajax context
    * @return bool
    * @since v3.4.37
    */
    function czr_fn_is_ajax() {

      /*
      * wp_doing_ajax() introduced in 4.7.0
      */
      $wp_doing_ajax = ( function_exists('wp_doing_ajax') && wp_doing_ajax() ) || ( ( defined('DOING_AJAX') && 'DOING_AJAX' ) );

      /*
      * https://core.trac.wordpress.org/ticket/25669#comment:19
      * http://stackoverflow.com/questions/18260537/how-to-check-if-the-request-is-an-ajax-request-with-php
      */
      $_is_ajax      = $wp_doing_ajax || ( ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

      return apply_filters( 'czr_is_ajax', $_is_ajax );
    }

  }//end of class
endif;


//Helper class to build a simple date diff object
//Alternative to date_diff for php version < 5.3.0
//http://stackoverflow.com/questions/9373718/php-5-3-date-diff-equivalent-for-php-5-2-on-own-function
if ( ! class_exists( 'CZR_DateInterval' ) ) :
Class CZR_DateInterval {
    /* Properties */
    public $y = 0;
    public $m = 0;
    public $d = 0;
    public $h = 0;
    public $i = 0;
    public $s = 0;

    /* Methods */
    public function __construct ( $time_to_convert ) {
      $FULL_YEAR = 60*60*24*365.25;
      $FULL_MONTH = 60*60*24*(365.25/12);
      $FULL_DAY = 60*60*24;
      $FULL_HOUR = 60*60;
      $FULL_MINUTE = 60;
      $FULL_SECOND = 1;

      //$time_to_convert = 176559;
      $seconds = 0;
      $minutes = 0;
      $hours = 0;
      $days = 0;
      $months = 0;
      $years = 0;

      while($time_to_convert >= $FULL_YEAR) {
          $years ++;
          $time_to_convert = $time_to_convert - $FULL_YEAR;
      }

      while($time_to_convert >= $FULL_MONTH) {
          $months ++;
          $time_to_convert = $time_to_convert - $FULL_MONTH;
      }

      while($time_to_convert >= $FULL_DAY) {
          $days ++;
          $time_to_convert = $time_to_convert - $FULL_DAY;
      }

      while($time_to_convert >= $FULL_HOUR) {
          $hours++;
          $time_to_convert = $time_to_convert - $FULL_HOUR;
      }

      while($time_to_convert >= $FULL_MINUTE) {
          $minutes++;
          $time_to_convert = $time_to_convert - $FULL_MINUTE;
      }

      $seconds = $time_to_convert; // remaining seconds
      $this->y = $years;
      $this->m = $months;
      $this->d = $days;
      $this->h = $hours;
      $this->i = $minutes;
      $this->s = $seconds;
      $this->days = ( 0 == $years ) ? $days : ( $years * 365 + $months * 30 + $days );
    }
}
endif;

?>