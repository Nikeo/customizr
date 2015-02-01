<?php
/**
* Defines filters and actions used in several templates/classes
*
*
* @package      Customizr
* @subpackage   classes
* @since        3.0
* @author       Nicolas GUILLAUME <nicolas@themesandco.com>
* @copyright    Copyright (c) 2013, Nicolas GUILLAUME
* @link         http://themesandco.com/customizr
* @license      http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
if ( ! class_exists( 'TC_contx' ) ) :
  class TC_contx {

    //Access any method or var of the class with classname::$instance -> var or method():
    static $instance;
    static $customize_context;

    function __construct () {
      self::$instance =& $this;
      //add_action ( 'init'                         , array( $this , 'tc_set_customize_context') );

      //add_action ( 'init'                         , array( $this , 'tc_init_customize_transient') );
      //clean the transient if customizer has been fired without saving
      //add_action ( 'admin_init'                   , array( $this , 'tc_init_customize_transient') );

      add_action ( 'wp_before_admin_bar_render'   , array( $this , 'tc_remove_initial_customize_menu' ));
      add_action ( 'admin_bar_menu'               , array( $this , 'tc_add_customize_menu' ), 100);

      ### LOAD EXTENDED WP SETTING CLASS ###
      add_action ( 'customize_register'           , array( $this , 'tc_load_customize_settings_class' ) ,0,1);

      ### ACTIONS ON CUSTOMIZER SAVE ###
      //Check if customizer has been saved properly before updating settings in DB => avoid cross page customization
      //add_action ( 'customize_save'               , array( $this , 'tc_check_cross_page_customization' ) );

      ### AJAX ACTIONS ###
      //Updates object suffix if needed
      //add_action ( 'wp_ajax_tc_update_context'    , array( $this , 'tc_ajax_update_context' ), 0 );

      ### ADD JS Params to control.js ##
      add_filter( 'tc_js_customizer_control_params' , array( $this , 'tc_add_controljs_params' ) );

      ### FILTER OPTIONS ON GET (OUTSIDE CUSTOMIZER) ###
      add_filter( 'tc_get_option'                 , array( $this , 'tc_contx_option'), 10 , 3 );

      ### Add scripts in admin ###
      add_action( 'admin_footer'                  , array( $this , 'tc_render_contx_script') );

    }//end of construct



    function tc_render_contx_script() {
      //@to do Only render is the href is valid (not a draft post for example or user must have appropriate capabilities )
      if ( ! isset( $_GET['action'] ) || 'edit' != isset( $_GET['action'] ) || TC___::$instance -> tc_is_customizing() )
        return;
      $_html    = __( 'Customize' , 'customizr');
      $_href    = $this -> tc_get_contx_link_attr('href');
      $_target  = '_blank';
      $_title   = __( 'Customize in live preview' , 'customizr' );
      $_lnk_attr = apply_filters('tc_customize_link_attr' , compact("_html", "_href", "_target", "_title") );
      ?>
      <script type="text/javascript">
        jQuery( function($) {
          var _html = "<?php echo $_lnk_attr['_html'] ?>",
              _href = "<?php echo $_lnk_attr['_href'] ?>",
              _target = "<?php echo $_lnk_attr['_target'] ?>",
              _title = "<?php echo $_lnk_attr['_title'] ?>",
              $cust_button = $( '<a>' , { class: 'tc-cust-button',  href : _href, html: _html, target: _target, title : _title } );

          if ( ! $( '.wrap > h2' , '#wpbody-content' ).find('a').length )
            $( '.wrap > h2' , '#wpbody-content' ).append($cust_button);
          else
            $( '.wrap > h2' , '#wpbody-content' ).find('a').before($cust_button);
        });
      </script>
      <?php
    }



    function tc_contx_option( $original , $option_name , $option_group ) {
      $_context = TC_contx::$instance ->tc_get_context();
      //make sure only tc_theme_options are filtered
      //if not an array then back to old way.
      if ( TC___::$tc_option_group != $option_group || ! is_array($original) )
        return $original;

      //do we have a option for this context ?
      if ( isset($original[$_context]) )
        return $original[$_context];
      //@to do add other all like all pages, all posts, all cat, all tags, all_authors
      //do we have all_contexts defined ?
      if ( isset($original['all_contexts']) )
        return $original['all_contexts'];

      return;
    }


    function tc_get_context( $_requesting_wot = null ) {
      //Handle the case when we request it in AJAX => no transient update!
      if ( TC___::$instance -> tc_doing_customizer_ajax() )
        return $this -> tc_build_context( 'from_ajax', $_requesting_wot );

      //Those conditions are important : the customizer_register function is ran several time during the customizer init
      //We want to define the transient only once, on the first run
      //@to do faut il rajouter la condition did_action('after_setup_theme') ?
      if ( TC___::$instance -> tc_is_customizing() && defined('IFRAME_REQUEST') )
        return $this -> tc_build_context( 'from_get', $_requesting_wot );


      //Preview frame or not customizing context
      return $this -> tc_build_context( null, $_requesting_wot);
    }



   public function tc_build_context( $_doing_wot = null, $_requesting_wot = null ) { //$type = null , $obj_id = null
      $parts    = array();
      switch ( $_doing_wot ) {
        case 'from_ajax':
          return isset($_POST['TCContext']) ? $_POST['TCContext'] : null;
        break;

        case 'from_get':
          $parts = $this -> tc_get_get_contx();
        break;

        default:
          $parts = $this -> tc_get_query_contx();
        break;
      }

      if ( is_array( $parts) && ! empty( $parts ) )
        list($meta_type , $type , $obj_id) =  $parts;

      switch ( $_requesting_wot ) {
        case 'meta_type':
          if ( false != $meta_type )
            return "{$meta_type}";
        break;

        case 'type':
          if ( false != $type )
            return "{$type}";
        break;

        case 'id':
          if ( false != $obj_id )
            return "{$obj_id}";
        break;

        // case 'title':
        //   if  ( false !== $type && false !== $obj_id )
        //     return tc_get_obj_title( $type , $obj_id );

        default:
          if  ( false != $meta_type && false != $obj_id )
            return "_{$meta_type}_{$type}_{$obj_id}";
          else if ( false != $meta_type && ! $obj_id )
            return "_{$meta_type}_{$type}";
        break;
      }
      return "";
    }


    public function tc_get_get_contx() {
      $meta_type    = isset( $_GET['meta_type']) ? $_GET['meta_type'] : false;
      $type         = isset( $_GET['type']) ? $_GET['type'] : false;
      $obj_id       = isset( $_GET['obj_id']) ? $_GET['obj_id'] : false;
      return apply_filters( 'tc_get_get_contx' , array( $meta_type, $type , $obj_id ) );
    }


    /*
    * @return array
    */
    public function tc_get_query_contx() {
      //don't call get_queried_object if the $query is not defined yet
      global $wp_query;
      if ( ! isset($wp_query) || empty($wp_query) )
        return array();

      $current_obj  = get_queried_object();
      $meta_type    = false;
      $type         = false;
      $obj_id       = false;

      //post, custom post types, page
      if ( isset($current_obj -> post_type) ) {
          $meta_type  = 'post';
          $type       = $current_obj -> post_type;
          $obj_id     = $current_obj -> ID;
      }

      //taxinomies : tags, categories, custom tax type
      if ( isset($current_obj -> taxonomy) && isset($current_obj -> term_id) ) {
          $meta_type  = 'tax';
          $type       = $current_obj -> taxonomy;
          $obj_id     = $current_obj -> term_id;
      }

      //author page
      if ( isset($current_obj -> data -> user_login ) && isset($current_obj -> ID) ) {
          $meta_type  = 'author';
          $type       = 'user';
          $obj_id     = $current_obj -> ID;
      }

      if ( is_404() )
        $meta_type  = '404';
      if ( is_search() )
        $meta_type  = 'search';
      if ( is_date() )
        $meta_type  = 'date';

      return apply_filters( 'tc_get_query_contx' , array( $meta_type , $type , $obj_id ) , $current_obj );
    }


    //@todo author case not handled
    function tc_get_admin_contx() {
      if ( ! is_admin() )
        return array();

      global $tag;
      $current_screen = get_current_screen();
      $post           = get_post();
      $meta_type      = false;
      $type           = false;
      $obj_id         = false;

      //post case : page, post CPT
      if ( 'post' == $current_screen->base
        && 'add' != $current_screen->action
        && ( $post_type_object = get_post_type_object( $post->post_type ) )
        && current_user_can( 'read_post', $post->ID )
        && ( $post_type_object->public )
        && ( $post_type_object->show_in_admin_bar )
        && ( 'draft' != $post->post_status ) )
      {
        $meta_type  = 'post';
        $type       = $post -> post_type;
        $obj_id     = $post -> ID;
      }
      //tax case : tags, cats, custom tax
      elseif ( 'edit-tags' == $current_screen->base
        && isset( $tag ) && is_object( $tag )
        && ( $tax = get_taxonomy( $tag->taxonomy ) )
        && $tax->public )
      {
        $meta_type  = 'tax';
        $type       = $tag -> taxonomy ;
        $obj_id     = $tag -> term_id;
      }
      return apply_filters( 'tc_get_admin_contx' , array( $meta_type , $type , $obj_id ) );
    }



    function tc_get_obj_title( $type , $obj_id ) {
        switch (variable) {
          case 'value':
            # code...
            break;

          default:
            # code...
            break;
        }
    }



    function tc_load_customize_settings_class() {
      locate_template( 'inc/class-contx-wp-settings.php' , $load = true, $require_once = true );
    }


    function tc_add_controljs_params( $_params ) {
      if ( is_array($_params) )
        return array_merge(
          $_params,
          array(
            'TCContext'     => array(
              'complete'  => $this -> tc_get_context(),
              'type'      => $this -> tc_get_context( 'type' )
              )
            )
        );
    }


    function tc_remove_initial_customize_menu() {
      //@todo //Only render is the href is valid (not a draft post for example or user must have appropriate capabilities )
      if ( ! current_user_can( 'edit_theme_options' ) || is_admin() )
        return;

      global $wp_admin_bar;
      $wp_admin_bar->remove_menu('customize');
    }




    function tc_add_customize_menu() {
      //@todo //Only render is the href is valid (not a draft post for example or user must have appropriate capabilities )
      if ( ! current_user_can( 'edit_theme_options' ) || is_admin() )
        return;

      global $wp_admin_bar;
      //Add it under appearance
      $wp_admin_bar -> add_menu( array(
          'parent' => 'appearance',
          'id'     => 'pc-wp-admin-appeance',
          'title'  => sprintf( '%1$s %2$s' , __( 'Customize' , 'customizr' ), $this -> tc_get_contx_link_attr('title') ),
          'href'   => $this -> tc_get_contx_link_attr('href'),
          'meta'   => array(
              'class' => 'hide-if-no-customize',
              'title'   => sprintf( '%1$s %2$s' , __( 'Customize this context :' , 'customizr' ) , $this -> tc_get_contx_link_attr('title') ),
          ),
      ) );
      //Add it in the wp admin bar
      $wp_admin_bar->add_menu( array(
         'parent'   => false,
         'id'     => 'tc-customize-button' ,
         'title'    => sprintf( '%1$s' , __( 'Customize' , 'customizr' ) ),
         'href'     => $this -> tc_get_contx_link_attr('href'),
         'meta'     => array(
            'class' => 'hide-if-no-customize',
             'title'    => sprintf( '%1$s %2$s' , __( 'Customize this context :' , 'customizr' ) , $this -> tc_get_contx_link_attr('title') ),
            ),
     ));
    }


    /*
    * @return string
    */
    function tc_get_contx_link_attr( $_wot = null ) {
      $_wot = is_null($_wot) ? 'href' : $_wot;
      $_return = '';
      $current_url = '';

      if ( is_admin() )
        list($meta_type, $type , $obj_id) = $this -> tc_get_admin_contx();
      else
        list($meta_type, $type , $obj_id) = $this -> tc_get_query_contx();

      if ( 'href' == $_wot ) {
        //inspired by wp_admin_bar_edit_menu() in wp-admin/admin-bar.php
        //@todo : author case to handle
        ### ADMIN CONTEXT ###
        if ( is_admin() ) {
          global $tag;
          $current_screen = get_current_screen();
          $post = get_post();
          if ( 'post' == $current_screen->base
            && 'add' != $current_screen->action
            && ( $post_type_object = get_post_type_object( $post->post_type ) )
            && current_user_can( 'read_post', $post->ID )
            && ( $post_type_object->public )
            && ( $post_type_object->show_in_admin_bar ) )
          {
            if ( 'draft' != $post->post_status )
              $current_url = get_permalink( $post->ID );
          }
          elseif ( 'edit-tags' == $current_screen->base
            && isset( $tag ) && is_object( $tag )
            && ( $tax = get_taxonomy( $tag->taxonomy ) )
            && $tax->public )
          {
            $current_url = get_term_link( $tag );
          }
        }
        ### FRONT END CONTEXT ###
        else {
          $current_url    = join("", array(
                          ( is_ssl() ? 'https://' : 'http://' ),
                          $_SERVER['HTTP_HOST'],
                          $_SERVER['REQUEST_URI']));
        }

        if ( empty( $current_url ))
          return $_return;

        $args = array();
        if ( ! is_null($meta_type) && ! is_null($obj_id) )
          $args = array( 'url' => urlencode( $current_url ) , 'meta_type' => $meta_type , 'type' => $type , 'obj_id' => $obj_id );
        else if ( ! is_null($meta_type) && is_null($obj_id) )
          $args = array( 'url' => urlencode( $current_url ) , 'type' => $type );
        $_return = add_query_arg( $args , wp_customize_url() );
      }

      if ( 'title' == $_wot ) {
        $_title = '';
        if ( ! is_null($meta_type) && ! is_null($obj_id) )
          $_title = sprintf('%1$s #%2$s' , $type, $obj_id );
        else if ( ! is_null($meta_type) && is_null($obj_id) )
          $_title = sprintf('%1$s' , $type );
        $_return = $_title;
      }
      return $_return;
    }



    //ON CUSTOMIZER SAVE :
    //1) identify the current context with obj suffix
    function tc_ajax_update_context() {
      //check_ajax_referer( 'tc-customizer-nonce', 'TCNonce' );

      $db_obj_suffix    = self::$customize_context;
      $customizer_current_suffix  = isset($_POST['TCContext']) ? $_POST['TCContext'] : '';
      if ( $db_obj_suffix != $customizer_current_suffix ){
        set_transient( 'tc_current_customize_context' , $customizer_current_suffix, 60*60 );
      }
      echo get_transient( 'tc_current_customize_context');

      die;
    }

  }
endif;