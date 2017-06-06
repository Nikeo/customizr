<?php
/**
* Defines filters and actions used in several templates/classes
*
*/
/**
* hook : after_setup_theme
* @package Customizr
* @since Customizr 3.3.0
*/
function czr_fn_wp_filters() {
    add_filter( 'the_content'     , 'czr_fn_fancybox_content_filter'  );
    /*
    * Smartload disabled for content retrieved via ajax
    */
    if ( apply_filters( 'czr_globally_enable_img_smart_load', !czr_fn_is_ajax() && esc_attr( czr_fn_opt( 'tc_img_smart_load' ) ) ) ) {
        add_filter( 'the_content'    , 'czr_fn_parse_imgs', PHP_INT_MAX );
        add_filter( 'czr_thumb_html' , 'czr_fn_parse_imgs'  );
    }
    add_filter( 'wp_title'        , 'czr_fn_wp_title' , 10, 2 );
}





/**
* This function returns the filtered global layout defined in CZR_init
*
* @package Customizr
* @since Customizr 4.0
*/
function czr_fn_get_global_layout() {
  return apply_filters( 'tc_global_layout' , CZR_init::$instance -> global_layout );
}

/**
* This function returns the CSS class to apply to content's element based on the layout
* @return array
*
*
* @package Customizr
* @since Customizr 4.0
*/
function czr_fn_get_in_content_width_class() {
  $global_sidebar_layout                 = czr_fn_get_layout( czr_fn_get_id() , 'sidebar' );

  switch ( $global_sidebar_layout ) {
    case 'b': $_class = 'narrow';
              break;
    case 'f': $_class = 'full';
              break;
    default : $_class = 'semi-narrow';
  }

  return apply_filters( 'czr_in_content_width_class' , array( $_class ) );
}

/**
* This function returns the layout (sidebar(s), or full width) to apply to a context
*
* @package Customizr
* @since Customizr 1.0
*/
function czr_fn_get_layout( $post_id , $sidebar_or_class = 'class' ) {
      global $post;
      //Article wrapper class definition
      $global_layout                 = czr_fn_get_global_layout();

      /* Force 404 layout */
      if ( is_404() ) {
            $czr_screen_layout = array(
                'sidebar' => false,
                'class'   => 'col-12 col-md-8 push-md-2'
            );
            return apply_filters( 'czr_screen_layout' , $czr_screen_layout[$sidebar_or_class], $post_id , $sidebar_or_class );
      }


      /* DEFAULT LAYOUTS */
      //what is the default layout we want to apply? By default we apply the global default layout
      $czr_sidebar_default_layout    = esc_attr( czr_fn_opt('tc_sidebar_global_layout') );
      $czr_sidebar_force_layout      = esc_attr( czr_fn_opt('tc_sidebar_force_layout') );

      //checks if the 'force default layout' option is checked and return the default layout before any specific layout
      if( $czr_sidebar_force_layout ) {
            $class_tab  = $global_layout[$czr_sidebar_default_layout];
            $class_tab  = $class_tab['content'];
            $czr_screen_layout = array(
                'sidebar' => $czr_sidebar_default_layout,
                'class'   => $class_tab
            );
            return apply_filters( 'czr_screen_layout' , $czr_screen_layout[$sidebar_or_class], $post_id , $sidebar_or_class );
      }

      global $wp_query, $post;
      $is_singular_layout          = false;


      if ( apply_filters( 'czr_is_post_layout', is_single( $post_id ), $post_id ) ) {
            $_czr_sidebar_default_layout  = esc_attr( czr_fn_opt('tc_sidebar_post_layout') );
            $is_singular_layout           = true;
      } if ( apply_filters( 'czr_is_page_layout', is_page( $post_id ), $post_id ) ) {
            $_czr_sidebar_default_layout  = esc_attr( czr_fn_opt('tc_sidebar_page_layout') );
            $is_singular_layout           = true;
      }

      $czr_sidebar_default_layout     = empty($_czr_sidebar_default_layout) ? $czr_sidebar_default_layout : $_czr_sidebar_default_layout;

      //builds the default layout option array including layout and article class
      $class_tab  = $global_layout[$czr_sidebar_default_layout];
      $class_tab  = $class_tab['content'];
      $czr_screen_layout             = array(
                  'sidebar' => $czr_sidebar_default_layout,
                  'class'   => $class_tab
      );

      //The following lines set the post specific layout if any, and if not keeps the default layout previously defined
      $czr_specific_post_layout    = false;

      //if we are displaying an attachement, we use the parent post/page layout
      if ( isset($post) && is_singular() && 'attachment' == $post->post_type ) {
            $czr_specific_post_layout  = esc_attr( get_post_meta( $post->post_parent , $key = 'layout_key' , $single = true ) );
      }

      //for a singular post or page OR for the posts page
      elseif ( $is_singular_layout || is_singular() || $wp_query -> is_posts_page ) {
            $czr_specific_post_layout  = esc_attr( get_post_meta( $post_id, $key = 'layout_key' , $single = true ) );
      }

      //checks if we display home page, either posts or static page and apply the customizer option
      global $wp_the_query;
      if( ($wp_the_query->is_home() && 'posts' == get_option( 'show_on_front' ) ) || $wp_the_query->is_front_page()) {
            $czr_specific_post_layout = czr_fn_opt('tc_front_layout');
      }

      if ( $czr_specific_post_layout ) {

            $class_tab  = $global_layout[$czr_specific_post_layout];
            $class_tab  = $class_tab['content'];
            $czr_screen_layout = array(
                'sidebar' => $czr_specific_post_layout,
                'class'   => $class_tab
            );

      }

      return apply_filters( 'czr_screen_layout' , $czr_screen_layout[$sidebar_or_class], $post_id , $sidebar_or_class );
}


/**
* This function returns the column content wrapper class
*
* @package Customizr
* @since Customizr 3.5
*/
function czr_fn_get_column_content_wrapper_class() {
    return apply_filters( 'czr_column_content_wrapper_classes' , array('row', 'column-content-wrapper') );
}

/**
* This function returns the main container class
*
* @package Customizr
* @since Customizr 3.5
*/
function czr_fn_get_main_container_class() {
    return apply_filters( 'czr_main_container_classes' , array('container') );
}

/**
* This function returns the article container class
*
* @package Customizr
* @since Customizr 3.5
*/
function czr_fn_get_article_container_class() {
    return apply_filters( 'czr_article_container_class' , array( czr_fn_get_layout( czr_fn_get_id() , 'class' ) , 'article-container' ) );
}




/**
 * Add an optional rel="tc-fancybox[]" attribute to all images embedded in a post.
 *
 * @package Customizr
 * @since Customizr 2.0.7
 */
function czr_fn_fancybox_content_filter( $content) {
    $tc_fancybox = esc_attr( czr_fn_opt( 'tc_fancybox' ) );

    if ( 1 != $tc_fancybox )
      return $content;

    global $post;
    if ( ! isset($post) )
      return $content;

    //same as smartload ones
    $allowed_image_extentions = apply_filters( 'tc_lightbox_allowed_img_extensions', array(
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
      return $content;
    }


    $img_extensions_pattern = sprintf( "(?:%s)", implode( '|', $allowed_image_extentions ) );
    $pattern                = '#<a([^>]+?)href=[\'"]?([^\'"\s>]+\.'.$img_extensions_pattern.'[^\'"\s>]*)[\'"]?([^>]*)>#i';


    $replacement = '<a$1href="$2" data-lb-type="grouped-post"$3>';

    $r_content   = preg_replace( $pattern, $replacement, $content);

    $content     = $r_content ? $r_content : $content;

    return apply_filters( 'czr_fancybox_content_filter', $content );
}






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

    $_socials         = czr_fn_opt('tc_social_links');
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

    //if the size is the default one, do not add the inline style css
    $social_size_css  = empty( $_social_opts['social-size'] ) || $_default_size == $_social_opts['social-size'] ? '' : "font-size:{$_social_opts['social-size']}px";

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

        array_push( $_social_links, sprintf('<a rel="nofollow" class="social-icon%6$s" %1$s title="%2$s" href="%3$s"%4$s%7$s><i class="fa %5$s"></i></a>',
          //do we have an id set ?
          //Typically not if the user still uses the old options value.
          //So, if the id is not present, let's build it base on the key, like when added to the collection in the customizer

          // Put them together
            !czr_fn_is_customizing() ? '' : sprintf( 'data-model-id="%1$s"', ! isset( $item['id'] ) ? 'czr_socials_'. $key : $item['id'] ),
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





//hook : czr_dev_notice
function czr_fn_print_r($message) {
    if ( ! is_user_logged_in() || ! current_user_can( 'edit_theme_options' ) || is_feed() )
      return;
    ?>
      <pre><h6 style="color:red"><?php echo $message ?></h6></pre>
    <?php
}




/* FMK MODEL / VIEW / COLLECTION HELPERS */
function czr_fn_stringify_array( $array, $sep = ' ' ) {
    if ( is_array( $array ) )
      $array = join( $sep, array_unique( array_filter( $array ) ) );
    return $array;
}


//A callback helper
//a callback can be function or a method of a class
//the class can be an instance!
function czr_fn_fire_cb( $cb, $params = array(), $return = false ) {
    $to_return = false;
    //method of a class => look for an array( 'class_name', 'method_name')
    if ( is_array($cb) && 2 == count($cb) ) {
      if ( is_object($cb[0]) ) {
        $to_return = call_user_func( array( $cb[0] ,  $cb[1] ), $params );
      }
      //instantiated with an instance property holding the object ?
      else if ( class_exists($cb[0]) && isset($cb[0]::$instance) && method_exists($cb[0]::$instance, $cb[1]) ) {
        $to_return = call_user_func( array( $cb[0]::$instance ,  $cb[1] ), $params );
      }
      else {
        $_class_obj = new $cb[0]();
        if ( method_exists($_class_obj, $cb[1]) )
          $to_return = call_user_func( array( $_class_obj, $cb[1] ), $params );
      }
    } else if ( is_string($cb) && function_exists($cb) ) {
      $to_return = call_user_func($cb, $params);
    }

    if ( $return )
      return $to_return;
}


function czr_fn_return_cb_result( $cb, $params = array() ) {
    return czr_fn_fire_cb( $cb, $params, $return = true );
}




/* Same as helpers above but passing the param argument as an exploded array of params*/
//A callback helper
//a callback can be function or a method of a class
//the class can be an instance!
function czr_fn_fire_cb_array( $cb, $params = array(), $return = false ) {
    $to_return = false;
    //method of a class => look for an array( 'class_name', 'method_name')
    if ( is_array($cb) && 2 == count($cb) ) {
      if ( is_object($cb[0]) ) {
        $to_return = call_user_func_array( array( $cb[0] ,  $cb[1] ), $params );
      }
      //instantiated with an instance property holding the object ?
      else if ( class_exists($cb[0]) && isset($cb[0]::$instance) && method_exists($cb[0]::$instance, $cb[1]) ) {
        $to_return = call_user_func_array( array( $cb[0]::$instance ,  $cb[1] ), $params );
      }
      else {
        $_class_obj = new $cb[0]();
        if ( method_exists($_class_obj, $cb[1]) )
          $to_return = call_user_func_array( array( $_class_obj, $cb[1] ), $params );
      }
    } else if ( is_string($cb) && function_exists($cb) ) {
      $to_return = call_user_func_array($cb, $params);
    }

    if ( $return )
      return $to_return;
}

function czr_fn_return_cb_result_array( $cb, $params = array() ) {
    return czr_fn_fire_cb_array( $cb, $params, $return = true );
}




/**
* helper
* returns the actual page id if we are displaying the posts page
* @return  boolean
*
*/
function czr_fn_is_slider_active( $queried_id = null ) {
  $queried_id = $queried_id ? $queried_id : czr_fn_get_real_id();
  //is the slider set to on for the queried id?
  if ( czr_fn_is_home() && czr_fn_opt( 'tc_front_slider' ) )
    return apply_filters( 'czr_slider_active_status', true , $queried_id );

  $_slider_on = esc_attr( get_post_meta( $queried_id, $key = 'post_slider_check_key' , $single = true ) );

  if ( ! empty( $_slider_on ) && $_slider_on )
    return apply_filters( 'czr_slider_active_status', true , $queried_id );

  return apply_filters( 'czr_slider_active_status', false , $queried_id );
}

/**
* helper
* returns the slider name id
* @return  string
*
*/
function czr_fn_get_current_slider( $queried_id = null ) {
  $queried_id = $queried_id ? $queried_id : czr_fn_get_real_id();
  //gets the current slider id
  $_home_slider     = czr_fn_opt( 'tc_front_slider' );
  $slider_name_id   = ( czr_fn_is_home() && $_home_slider ) ? $_home_slider : esc_attr( get_post_meta( $queried_id, $key = 'post_slider_key' , $single = true ) );
  return apply_filters( 'czr_slider_name_id', $slider_name_id , $queried_id );
}


function czr_fn_post_has_title() {
    return ! in_array(
      get_post_format(),
      apply_filters( 'czr_post_formats_with_no_heading', array( 'aside' , 'status' , 'link' , 'quote' ) )
    );
}

/* TODO: caching system */
function czr_fn_get_logo_atts( $logo_type = '', $backward_compatibility = true ) {
    $logo_type_sep      = $logo_type ? '_sticky_' : '_';

    $_cache_key         = "czr{$logo_type_sep}logo_atts";
    $_logo_atts         = wp_cache_get( $_cache_key );

    if ( false !== $_logo_atts )
      return $_logo_atts;

    $_logo_atts = array();

    $accepted_formats   = apply_filters( 'czr_logo_img_formats' , array('jpg', 'jpeg', 'png' ,'gif', 'svg', 'svgz' ) );

    //check if the logo is a path or is numeric
    //get src for both cases
    $_logo_src          = '';
    $_width             = false;
    $_height            = false;
    $_attachment_id     = false;
    $_logo_option       = esc_attr( czr_fn_opt( "tc{$logo_type_sep}logo_upload") );
    //check if option is an attachement id or a path (for backward compatibility)
    if ( is_numeric($_logo_option) ) {
      $_attachment_id   = $_logo_option;
      $_attachment_data = apply_filters( "tc{$logo_type_sep}logo_attachment_img" , wp_get_attachment_image_src( $_logo_option , 'full' ) );
      $_logo_src        = $_attachment_data[0];
      $_width           = ( isset($_attachment_data[1]) && $_attachment_data[1] > 1 ) ? $_attachment_data[1] : $_width;
      $_height          = ( isset($_attachment_data[2]) && $_attachment_data[2] > 1 ) ? $_attachment_data[2] : $_height;
    } elseif ( $backward_compatibility ) { //old treatment
      //rebuild the logo path : check if the full path is already saved in DB. If not, then rebuild it.
      $upload_dir       = wp_upload_dir();
      $_saved_path      = esc_url ( czr_fn_opt( "tc{$logo_type_sep}logo_upload") );
      $_logo_src        = ( false !== strpos( $_saved_path , '/wp-content/' ) ) ? $_saved_path : $upload_dir['baseurl'] . $_saved_path;
    }
    //hook + makes ssl compliant
    $_logo_src          = apply_filters( "tc{$logo_type_sep}logo_src" , is_ssl() ? str_replace('http://', 'https://', $_logo_src) : $_logo_src ) ;
    $filetype           = czr_fn_check_filetype ($_logo_src);

    if( ! empty($_logo_src) && in_array( $filetype['ext'], $accepted_formats ) )
      $_logo_atts = array(
                'logo_src'           => $_logo_src,
                'logo_attachment_id' => $_attachment_id,
                'logo_width'         => $_width,
                'logo_height'        => $_height,
                'logo_type'          => trim($logo_type_sep,'_')
      );

    //cache this
    wp_cache_set( $_cache_key, $_logo_atts );

    return $_logo_atts;
}

















//back compat
if ( ! class_exists( 'CZR_utils' ) ) :
  class CZR_utils {
    //Access any method or var of the class with classname::$instance -> var or method():
    static $inst;
    static $instance;

    function __construct () {
      self::$inst =& $this;
      self::$instance =& $this;
    }

    /**
    * Returns an option from the options array of the theme.
    *
    * @package Customizr
    * @since Customizr 1.0
    */
    function czr_fn_opt( $option_name , $option_group = null, $use_default = true ) {
      return czr_fn_opt( $option_name, $option_group, $use_default );
    }
  }

  new CZR_utils;

endif;

?>