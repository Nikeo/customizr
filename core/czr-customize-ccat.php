<?php
/**
* Customizer actions and filters
*
*/
if ( ! class_exists( 'CZR_customize' ) ) :
  class CZR_customize {
    static $instance;
    public $control_translations;
    public $czz_attr;

    function __construct () {
      global $wp_version;

      //check if WP version >= 3.4 to include customizer functions
      //Shall we really keep this ?
      if ( ! version_compare( $wp_version, '3.4' , '>=' ) ) {
        add_action( 'admin_menu'                             , array( $this , 'czr_fn_add_fallback_page' ));
        return;
      }

      self::$instance =& $this;

      //add control class
      add_action( 'customize_register'                       , array( $this, 'czr_fn_augment_customizer' ),10, 1);

      //Partial refreshs
      add_action( 'customize_register'                       , array( $this, 'czr_fn_register_partials' ) );

      //add the customizer built with the builder below
      add_action( 'customize_register'                       , array( $this, 'czr_fn_customize_register' ), 20, 1 );

      //modify some WP built-in settings / controls / sections
      add_action( 'customize_register'                       , array( $this, 'czr_fn_alter_wp_customizer_settings' ), 1000, 1 );

      //add grid/post list buttons in the control views
      add_action( '__before_setting_control'                 , array( $this, 'czr_fn_render_grid_control_link' ) );

      //remove old logo setting if the wp custom logo option has been set
      add_action( 'customize_save_custom_logo'               , array( $this, 'czr_fn_remove_old_tc_logo_upload' ) );

      add_action( 'customize_save_after',  array( $this, 'czr_fn_flush_wp_cache' ) );

      //load resources class
      $this -> czr_fn_fire_czr_resources();
    }

    //@hook 'customize_save_after'
    function czr_fn_flush_wp_cache() {
        // Make sure cached objects are cleaned
        // for https://github.com/presscustomizr/customizr/issues/1898
        wp_cache_flush();
    }



    function czr_fn_fire_czr_resources() {
      if (  ! is_object(CZR_customize_resources::$instance) )
        new CZR_customize_resources();
    }



    /* ------------------------------------------------------------------------- *
     *  AUGMENT CUSTOMIZER SERVER SIDE
    /* ------------------------------------------------------------------------- */
    /**
    * Augments wp customize controls and settings classes
    * @package Customizr
    * @since Customizr 1.0
    */
    function czr_fn_augment_customizer( $manager ) {
      /* Concatenated with grunt  */

      //Registered types are eligible to be rendered via JS and created dynamically.
      if ( class_exists('CZR_Customize_Cropped_Image_Control') )
        $manager -> register_control_type( 'CZR_Customize_Cropped_Image_Control' );

      if ( class_exists('CZR_Customize_Code_Editor_Control') )
        $manager -> register_control_type( 'CZR_Customize_Code_Editor_Control' );

      if ( class_exists('CZR_Customize_Panels') )
        $manager -> register_panel_type( 'CZR_Customize_Panels');

      if ( class_exists('CZR_Customize_Sections') )
        $manager -> register_panel_type( 'CZR_Customize_Sections');

      if ( czr_fn_is_pro_section_on() ) {
        $manager -> register_section_type( 'CZR_Customize_Section_Pro');
      }
    }



    /* ------------------------------------------------------------------------- *
     *  PARTIALS
    /* ------------------------------------------------------------------------- */
    //hook : customize_register
    function czr_fn_register_partials( WP_Customize_Manager $wp_customize ) {

        //Bail if selective refresh is not available (old versions) or disabled (for skope for example)
        if ( ! isset( $wp_customize->selective_refresh ) || ! czr_fn_is_partial_refreshed_on() ) {
            return;
        }

        //SOCIALS
        $wp_customize->selective_refresh->add_partial( 'social_links', array(
            'selector'            => '.social-links',
            'settings'            => array( CZR_THEME_OPTIONS . '[tc_social_links]' ),
            'render_callback'     => 'czr_fn_print_social_links',
            'fallback_refresh'    => false,
        ) );

        //ONLY FOR OLD CZR at the moment
        if ( ! czr_fn_is_ms() ) {
            /* Header */
            $wp_customize->selective_refresh->add_partial( 'main_header', array(
                'selector'            => 'header.tc-header',
                'settings'            => array(
                  CZR_THEME_OPTIONS . '[tc_header_layout]',
                  CZR_THEME_OPTIONS . '[tc_show_tagline]',
                  CZR_THEME_OPTIONS . '[tc_social_in_header]',
                ),
                'container_inclusive' => true,
                'render_callback'     => 'czr_fn_render_main_header',
                'fallback_refresh'    => false,
            ) );
            /* Tagline text */
            $wp_customize->selective_refresh->add_partial( 'blogdescription', array(
                'selector'            => '.site-description',
                'settings'            => array( 'blogdescription' ),
                'container_inclusive' => false,
                'render_callback'     => 'czr_fn_get_tagline_text',
                'fallback_refresh'    => false,
            ) );
        }
    }




    /*
    * Since the WP_Customize_Manager::$controls and $settings are protected properties, one way to alter them is to use the get_setting and get_control methods
    * Another way is to remove the control and add it back as an instance of a custom class and with new properties
    * and set new property values
    * hook : tc_customize_register:30
    * @return void()
    */
    function czr_fn_alter_wp_customizer_settings( $wp_customize ) {

      //CHANGE BLOGNAME AND BLOGDESCRIPTION TRANSPORT
      if ( is_object( $wp_customize -> get_setting( 'blogname' ) ) ) {
        $wp_customize -> get_setting( 'blogname' )->transport = 'postMessage';
      }
      if ( is_object( $wp_customize -> get_setting( 'blogdescription' ) ) ) {
        $wp_customize -> get_setting( 'blogdescription' )->transport = 'postMessage';
      }

      //IF WP VERSION >= 4.3 AND SITE_ICON SETTING EXISTS
      //=> REMOVE CUSTOMIZR FAV ICON CONTROL
      //=> CHANGE SITE ICON DEFAULT WP SECTION TO CUSTOMIZR LOGO SECTION
      global $wp_version;
      if ( version_compare( $wp_version, '4.3', '>=' ) && is_object( $wp_customize -> get_control( 'site_icon' ) ) ) {
        $tc_option_group = CZR_THEME_OPTIONS;

        //ONLY FOR OLD CUSTOMIZR
        if ( ! ( defined( 'CZR_IS_MODERN_STYLE' ) && CZR_IS_MODERN_STYLE ) ) {
          $wp_customize -> remove_control( "{$tc_option_group}[tc_fav_upload]" );
        }
        //note : the setting is kept because used in the customizer js api to handle the transition between Customizr favicon to WP site icon.
        $wp_customize -> get_control( 'site_icon' )->section = 'title_tagline';
      }
      //end ALTER SITE ICON



      //CHANGE MENUS PROPERTIES
      // $locations    = get_registered_nav_menus();
      // $menus        = wp_get_nav_menus();
      // $choices      = array( '' => __( '&mdash; Select &mdash;', 'customizr' ) );
      // foreach ( $menus as $menu ) {
      //   $choices[ $menu->term_id ] = wp_html_excerpt( $menu->name, 40, '&hellip;' );
      // }
      // $_priorities  = array(
      //   'main' => 10,
      //   'secondary' => 20,
      //   'topbar'    => 30,
      // );

      // //WP only adds the menu(s) settings and controls if the user has created at least one menu.
      // //1) if no menus yet, we still want to display the menu picker + add a notice with a link to the admin menu creation panel
      // //=> add_setting and add_control for each menu location. Check if they are set first by security
      // //2) if user has already created a menu, the settings are already created, we just need to update the controls.
      // $_priority = 0;
      // //assign new priorities to the menus controls
      // foreach ( $locations as $location => $description ) {
      //   $menu_setting_id = "nav_menu_locations[{$location}]";

      //   //create the settings if they don't exist
      //   //=> in the condition, make sure that the setting has really not been created yet (maybe over secured)
      //   if ( ! $menus && ! is_object( $wp_customize->get_setting($menu_setting_id ) ) ) {
      //     $wp_customize -> add_setting( $menu_setting_id, array(
      //       'sanitize_callback' => 'absint',
      //       'theme_supports'    => 'menus',
      //     ) );
      //   }

      //   //remove the controls if they exists
      //   if ( is_object( $wp_customize->get_control( $menu_setting_id ) ) ) {
      //     $wp_customize -> remove_control( $menu_setting_id );
      //   }

      //   //replace the controls by our custom controls
      //   $_control_properties = array(
      //     'label'   => $description,
      //     'section' => 'nav',
      //     'title'   => "main" == $location ? __( 'Assign menus to locations' , 'customizr') : false,
      //     'type'    => 'select',
      //     'choices' => $choices,
      //     'priority' => isset($_priorities[$location]) ? $_priorities[$location] : $_priority,
      //     'notice' => __('If your freshly created menu is not listed, please refresh the customizer panel.', 'customizr')
      //   );

      //   //add a notice property if no menu created yet.
      //   if ( ! $menus ) {
      //     //adapt the nav section description for v4.3 (menu in the customizer from now on)
      //     $_create_menu_link =  version_compare( $GLOBALS['wp_version'], '4.3', '<' ) ? admin_url('nav-menus.php') : "javascript:wp.customize.section('nav').container.find('.customize-section-back').trigger('click'); wp.customize.panel('nav_menus').focus();";
      //     $_control_properties['notice'] = sprintf( __("You haven't created any menu yet. %s or check the %s to learn more about menus.", "customizr"),
      //       sprintf( '<strong><a href="%1$s" title="%2$s">%2$s</a></strong>', $_create_menu_link, __("Create a new menu now" , "customizr") ),
      //       sprintf( '<a href="%1$s" title="%2$s" target="_blank">%2$s</a>', esc_url('codex.wordpress.org/WordPress_Menu_User_Guide'),  __("WordPress documentation" , "customizr") )
      //     );
      //   }

      //   $wp_customize -> add_control( new CZR_controls( $wp_customize, $menu_setting_id, $_control_properties ) );

      //   $_priority = $_priority + 10;
      // }//foreach



      //MOVE THE CUSTOM CSS SECTION (introduced in 4.7) INTO THE ADVANCED PANEL
      if ( is_object( $wp_customize->get_section( 'custom_css' ) ) ) {
          $wp_customize -> get_section( 'custom_css' ) -> panel = 'tc-advanced-panel';
          $wp_customize -> get_section( 'custom_css' ) -> priority = 10;
      }
    }




    /**
    * Generates customizer sections, settings and controls
    * @package Customizr
    * @since Customizr 3.0
    */
    function czr_fn_customize_register( $wp_customize) {
      return $this -> czr_fn_customize_factory (
        $wp_customize,
        $this -> czr_fn_customize_arguments(),
        czr_fn_get_customizer_map()
      );
    }




    /**
     * Defines authorized arguments for panels, sections, settings and controls
     * @package Customizr
     * @since Customizr 3.0
     */
    function czr_fn_customize_arguments() {
      $args = array(
          'panels' => array(
                'title',
                'czr_subtitle',
                'description',
                'priority' ,
                'theme_supports',
                'capability',
                'type'
          ),
          'sections' => array(
                'title' ,
                'priority' ,
                'description',
                'panel',
                'theme_supports',
                'type',
                'active_callback',

                'pro_subtitle',
                'pro_doc_url',
                'pro_text',
                'pro_url',

                'ubq_panel'
          ),
          'settings' => array(
                'default'     =>  null,
                'capability'    =>  'manage_options' ,
                'setting_type'    =>  'option' ,
                'sanitize_callback' =>  null,
                'sanitize_js_callback'  =>  null,
                'validate_callback'  =>  null,
                'transport'     =>  null
          ),
          'controls' => array(
                'title' ,
                'label' ,
                'description',
                'section' ,
                'settings',
                'type' ,

                'module_type',
                'syncCollection',

                'choices' ,
                'priority' ,

                'notice' ,
                'buttontext' ,//button specific
                'link' ,//button specific
                'step' ,//number specific
                'min' ,//number specific
                'range-input' ,
                'max',
                'cssid',
                'slider_default',
                'active_callback',
                'content_after',
                'content_before',
                'icon',
                'width',
                'height',
                'flex_width',
                'flex_height',
                'dst_width',
                'dst_height',

                'ubq_section',

                //for the code editor
                'code_type',
                'input_attrs'

          )
      );
      return apply_filters( 'tc_customizer_arguments', $args );
    }


    /**
     * Generates customizer
     * @package Customizr
     * @since Customizr 3.0
     */
    function czr_fn_customize_factory ( $wp_customize , $args, $setup ) {
      global $wp_version;
      //add panels if current WP version >= 4.0
      if ( isset( $setup['add_panel']) && version_compare( $wp_version, '4.0', '>=' ) ) {
        foreach ( $setup['add_panel'] as $p_key => $p_options ) {
          //declares the clean section option array
          $panel_options = array();
          //checks authorized panel args
          foreach( $args['panels'] as $p_set) {
            $panel_options[$p_set] = isset( $p_options[$p_set]) ?  $p_options[$p_set] : null;
          }
          if ( class_exists( 'CZR_Customize_Panels' ) ) {
            $wp_customize -> add_panel( new CZR_Customize_Panels( $wp_customize, $p_key, $panel_options ) );
          } else {
            $wp_customize -> add_panel( $p_key, $panel_options );
          }
        }
      }

      //remove sections
      if ( isset( $setup['remove_section'])) {
        foreach ( $setup['remove_section'] as $section) {
          $wp_customize -> remove_section( $section);
        }
      }

      //add sections
      if ( isset( $setup['add_section'])) {
        foreach ( $setup['add_section'] as  $key => $options) {
          //generate section array
          $option_section = array();

          foreach( $args['sections'] as $sec) {
            $option_section[$sec] = isset( $options[$sec]) ?  $options[$sec] : null;
          }

          //instanciate a custom class if defined
          if( ! isset( $options['section_class']) )
            $wp_customize -> add_section( $key,$option_section);
          else if ( isset( $options['section_class']) && class_exists($options['section_class']) )
            $wp_customize -> add_section( new $options['section_class']( $wp_customize, $key, $option_section ));

        }//end foreach
      }//end if


      //add setting alone
      if ( isset( $setup['add_setting'])) {

        foreach ( $setup['add_setting'] as $key => $options) {
          //isolates the option name for the setting's filter
          $f_option = preg_match_all( '/\[(.*?)\]/' , $key , $match );
          $f_option_name = isset( $match[1][0] )  ? $match[1][0] : 'setting';

          $czr_option_group = CZR_THEME_OPTIONS;

          $_opt_name = "{$czr_option_group}[{$key}]";

          //declares settings array
          $option_settings = array();
          foreach( $args['settings'] as $set => $set_value) {
            if ( $set == 'setting_type' ) {
              $option_settings['type'] = isset( $options['setting_type']) ?  $options['setting_type'] : $args['settings'][$set];
              $option_settings['type'] = apply_filters( "{$f_option_name}_customizer_set", $option_settings['type'] , $set );
            }
            else {
              $option_settings[$set] = isset( $options[$set]) ?  $options[$set] : $args['settings'][$set];
              $option_settings[$set] = apply_filters( "{$f_option_name}_customizer_set" , $option_settings[$set] , $set );
            }
          }

          //add setting
          $wp_customize -> add_setting( $_opt_name, $option_settings );
        }//end for each
      }//end if isset


      //add control alone
      if ( isset( $setup['add_control'])) {

        foreach ( $setup['add_control'] as $key => $options) {
          //isolates the option name for the setting's filter
          $f_option = preg_match_all( '/\[(.*?)\]/' , $key , $match );
          $f_option_name = isset( $match[1][0] )  ? $match[1][0] : 'setting';

          $czr_option_group = CZR_THEME_OPTIONS;

          $_opt_name = "{$czr_option_group}[{$key}]";

          //generate controls array
          $option_controls = array();
          foreach( $args['controls'] as $con) {
            $option_controls[$con] = isset( $options[$con]) ?  $options[$con] : null;
          }

          //add control with a class instanciation if not default
          if( ! isset( $options['control']) )
            $wp_customize -> add_control( $_opt_name, $option_controls );
          else
            $wp_customize -> add_control( new $options['control']( $wp_customize, $_opt_name, $option_controls ));

        }//end for each
      }//end if isset



      //add settings and controls
      if ( isset( $setup['add_setting_control'])) {

        foreach ( $setup['add_setting_control'] as $key => $options) {
          //isolates the option name for the setting's filter
          $f_option = preg_match_all( '/\[(.*?)\]/' , $key , $match );
          $f_option_name = isset( $match[1][0] )  ? $match[1][0] : 'setting';

          $czr_option_group = CZR_THEME_OPTIONS;

          //build option name
          //When do we add a prefix ?
          //all customizr theme options start by "tc_" by convention
          //=> footer customizer addon starts by fc_
          //=> grid customizer addon starts by gc_
          //When do we add a prefix ?
          $add_prefix = false;
          if ( czr_fn_is_customizr_option( $key ) )
            $add_prefix = true;
          $_opt_name = $add_prefix ? "{$czr_option_group}[{$key}]" : $key;

          //declares settings array
          $option_settings = array();

          // bail here if the setting is registered dynamically
          if ( array_key_exists( 'registered_dynamically', $options ) && true === $options[ 'registered_dynamically' ] )
            continue;

          foreach( $args['settings'] as $set => $set_value) {
            if ( $set == 'setting_type' ) {
              $option_settings['type'] = isset( $options['setting_type']) ?  $options['setting_type'] : $args['settings'][$set];
              $option_settings['type'] = apply_filters( "{$f_option_name}_customizer_set", $option_settings['type'] , $set );
            }
            else {
              $option_settings[$set] = isset( $options[$set]) ?  $options[$set] : $args['settings'][$set];
              $option_settings[$set] = apply_filters( "{$f_option_name}_customizer_set" , $option_settings[$set] , $set );
            }
          }

          //The post message callbacks and css are not implement yet as of July 2017 for the modern style.
          //=> let's force the "refresh" for all transport. => this will also disable any partial refreshs added to the controls
          //$option_settings['transport'] = CZR_IS_MODERN_STYLE ? 'refresh' : $option_settings['transport'];

          //add setting
          $wp_customize -> add_setting( $_opt_name, $option_settings );

          //generate controls array
          $option_controls = array();
          foreach( $args['controls'] as $con) {
            $option_controls[$con] = isset( $options[$con]) ?  $options[$con] : null;
          }

          //add control with a class instanciation if not default
          if( ! isset( $options['control']) )
            $wp_customize -> add_control( $_opt_name, $option_controls );
          else {
            if ( class_exists( $options['control'] ) )
              $wp_customize -> add_control( new $options['control']( $wp_customize, $_opt_name, $option_controls ));
          }

        }//end for each
      }//end if isset
    }//end of customize generator function


    /* ------------------------------------------------------------------------- *
     *  HELPERS
    /* ------------------------------------------------------------------------- */
    //@return array of WP builtin settings
    function czr_fn_get_wp_builtin_settings() {
      return array(
        'blogname',
        'blogdescription',
        'site_icon',
        //'custom_logo',
        //'background_color',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        //'header_image',
       // 'header_image_data'
      );
    }

    //@return array of grid design options
    function czr_fn_get_grid_design_controls() {
      return apply_filters( 'tc_grid_design_controls', array(
        'tc_grid_in_blog',
        'tc_grid_in_archive',
        'tc_grid_in_search',
        'tc_grid_thumb_height',
        'tc_grid_shadow',
        'tc_grid_bottom_border',
        'tc_grid_icons',
        'tc_grid_num_words'
      ) );
    }

    /**
    * hook __before_setting_control (declared in class-tc-controls-settings.php)
    * @echo clickable text
    */
    function czr_fn_render_grid_control_link( $set_id ) {
      if ( false !== strpos( $set_id, 'tc_post_list_show_thumb' ) ) {
        printf('<span class="tc-grid-toggle-controls" title="%1$s">%1$s</span>' , __('More grid design options' , 'customizr'));
      }
    }



    //hook: customize_save_custom_logo
    function czr_fn_remove_old_tc_logo_upload( $setting ) {
      //make sure the custom_logo option is a theme mod
      if ( 'theme_mod' !== $setting->type ) {
        return;
      }
      $theme_options = czr_fn_get_unfiltered_theme_options();
      unset( $theme_options['tc_logo_upload'] );
      if ( is_array( $theme_options ) && ! empty( $theme_options ) ) {
        update_option( CZR_THEME_OPTIONS, $theme_options );
      }
    }




    //ONLY FOR OLD CUSTOMIZR
    /*
    * hook : '__after_setting_control' (declared in class-tc-controls-settings.php)
    * Display a title for the favicon control, after the logo
    */
    function czr_fn_add_favicon_title($set_id) {
      if ( false !== strpos( $set_id, 'tc_sticky_logo_upload' ) )
        printf( '<h3 class="czr-customizr-title">%s</h3>', __( 'SITE ICON' , 'customizr') );
    }




    /**
    * Add fallback admin page.
    * @package Customizr
    * @since Customizr 1.1
    */
    function czr_fn_add_fallback_page() {
        $theme_page = add_theme_page(
            __( 'Upgrade WP' , 'customizr' ),   // Name of page
            __( 'Upgrade WP' , 'customizr' ),   // Label in menu
            'edit_theme_options' ,          // Capability required
            'upgrade_wp.php' ,             // Menu slug, used to uniquely identify the page
            array( $this , 'czr_fn_fallback_admin_page' )         //function to be called to output the content of this page
        );
    }




    /**
    * Render fallback admin page.
    * @package Customizr
    * @since Customizr 1.1
    */
    function czr_fn_fallback_admin_page() {
      ?>
        <div class="wrap upgrade_wordpress">
          <div id="icon-options-general" class="icon32"><br></div>
          <h2><?php _e( 'This theme requires WordPress 3.4+' , 'customizr' ) ?> </h2>
          <br />
          <p style="text-align:center">
            <a style="padding: 8px" class="button-primary" href="<?php echo admin_url().'update-core.php' ?>" title="<?php _e( 'Upgrade Wordpress Now' , 'customizr' ) ?>">
            <?php _e( 'Upgrade Wordpress Now' , 'customizr' ) ?></a>
            <br /><br />
          <img src="<?php echo CZR_BASE_URL . 'screenshot.png' ?>" alt="Customizr" />
          </p>
        </div>
      <?php
    }
  }//end class
endif;
?><?php
/**
* Customizer actions and filters
*
*/
if ( ! class_exists( 'CZR_customize_resources' ) ) :
  class CZR_customize_resources {
    static $instance;
    private $_is_dev_mode           = false;
    private $_is_debug_mode         = false;

    private $_style_version_suffix  = false;

    function __construct () {
      self::$instance =& $this;

      $this->_is_debug_mode         = ( defined('WP_DEBUG') && true === WP_DEBUG );
      $this->_is_dev_mode           = ( defined('CZR_DEV') && true === CZR_DEV );
      $this->_style_version_suffix  = defined( 'CZR_IS_MODERN_STYLE' ) && CZR_IS_MODERN_STYLE ? '-modern' : '';

      //control scripts and style
      add_action( 'customize_controls_enqueue_scripts'        , array( $this, 'czr_fn_customize_controls_js_css' ), 20 );

      //preview scripts
      //set with priority 20 to be fired after czr_fn_customize_store_db_opt
      add_action( 'customize_preview_init'                    , array( $this, 'czr_fn_customize_preview_js_css' ), 20 );

      //exports some wp_query informations. Updated on each preview refresh.
      add_action( 'customize_preview_init'                    , array( $this, 'czr_fn_add_preview_footer_action' ), 20 );


    }



    //only for the classic
    //adds specific preview style for partial refresh to the user option style
    //hook : 'tc_user_options_style'
    function czr_fn_write_preview_style_classic( $_css ) {
      //specific preview style
      return sprintf( "%s\n%s",
          $_css,
          '/* Fix partial edit shortcut conflict with bootstrap .span first child of a .row */
.row [class*=customize-partial-edit-shortcut]:first-child + [class*=span],
.row-fluid [class*=customize-partial-edit-shortcut]:first-child + [class*=span] {
  margin-left: 0;
  margin-right: 0;
}
/* Fine tune pencil icon in the header */
.tc-header > .customize-partial-edit-shortcut > button {
  left: 0
}'
      );

    }


    /**
     * Add script to controls
     * Dependency : customize-controls located in wp-includes/script-loader.php
     * Hooked on customize_controls_enqueue_scripts located in wp-admin/customize.php
     * @package Customizr
     * @since Customizr 3.1.0
     */
    function czr_fn_customize_controls_js_css() {

        wp_enqueue_style(
            'tc-customizer-controls-theme-style',
            sprintf('%1$sassets/czr/css/czr-control-theme.css', CZR_BASE_URL),
            array( 'czr-fmk-controls-style' ),
            $this->_is_debug_mode ? time() : CUSTOMIZR_VER,
            $media = 'all'
        );


        // czr-control-deps-modern.js / czr-control-deps.js
        wp_enqueue_script(
            'tc-customizer-controls-deps',
            sprintf('%1$sassets/czr/js/czr-control-deps%2$s.js' , CZR_BASE_URL, $this->_style_version_suffix ),
            array( 'czr-theme-customizer-fmk' ),
            $this->_is_debug_mode ? time() : CUSTOMIZR_VER,
            true
        );

        wp_enqueue_script(
            'tc-customizer-controls-vdr',
            sprintf('%1$sassets/czr/js/czr-control-dom_ready.js', CZR_BASE_URL ),
            array( 'czr-theme-customizer-fmk' ),
            $this->_is_debug_mode ? time() : CUSTOMIZR_VER,
            true
        );

        $this->czr_fn_customize_controls_localize();
    }



    //shared
    function czr_fn_customize_controls_localize() {

      //gets the featured pages id from init
      $fp_ids       = apply_filters( 'tc_featured_pages_ids' , CZR___::$instance -> fp_ids);

      //declares the common fp control fields and the dynamic arrays
      $fp_controls      = array(
        CZR_THEME_OPTIONS.'[tc_show_featured_pages_img]',
        CZR_THEME_OPTIONS.'[tc_featured_page_button_text]'
      );
      $page_dropdowns     = array();
      $text_fields      = array();

      //adds filtered page dropdown fields
      foreach ( $fp_ids as $id ) {
        $page_dropdowns[]   = CZR_THEME_OPTIONS.'[tc_featured_page_'. $id.']';
        $text_fields[]    = CZR_THEME_OPTIONS.'[tc_featured_text_'. $id.']';
      }


      //localizes
      wp_localize_script(
        'tc-customizer-controls-deps',
        'themeServerControlParams',
        array(
            //should be included in all themes
            'wpBuiltinSettings'=> CZR_customize::$instance -> czr_fn_get_wp_builtin_settings(),
            'isThemeSwitchOn'  => ! CZR_IS_PRO,
            'themeSettingList' => CZR_BASE::$theme_setting_list,
            'themeOptions'     => CZR_THEME_OPTIONS,

            // Customizr theme specifics
            'FPControls'      => array_merge( $fp_controls , $page_dropdowns , $text_fields ),
            'defaultSliderHeight' => 500,//500px, @todo make sure we can hard code it here

            'i18n'   => $this -> czr_fn_get_translated_strings(),

            //not used by the new
            'faviconOptionName' => 'tc_fav_upload',

            'gridDesignControls' => CZR_customize::$instance -> czr_fn_get_grid_design_controls(),
            'isRTL'           => is_rtl(),
            'isChildTheme'    => is_child_theme(),
            'isModernStyle'   => czr_fn_is_ms(),
            'isPro'           => czr_fn_is_pro()
        )
      );

    }

    //hook : customize_preview_init
    function czr_fn_customize_preview_js_css() {
        global $wp_version;

        // loads czr-preview-post_message.js / czr-preview-post_message-modern.js
        wp_enqueue_script(
            'czr-customizr-theme-preview-js' ,
            sprintf('%1$s/assets/czr/js/czr-preview-post_message%2$s.js' , CZR_BASE_URL, $this->_style_version_suffix ),
            array( 'czr-customizer-preview' ),//<= czr-preview-base.js, loaded from the czr-base-fmk
            $this->_is_debug_mode ? time() : CUSTOMIZR_VER,
            true
        );

        //localizes
        wp_localize_script(
              'czr-customizr-theme-preview-js',
              'themeServerPreviewParams',// 'CZRPreviewParams',
              apply_filters('tc_js_customizer_preview_params' ,
                  array(
                      //czr4 won't use this
                      'customSkin'      => apply_filters( 'tc_custom_skin_preview_params' , array( 'skinName' => '', 'fullPath' => '' ) ),
                      'fontPairs'       => czr_fn_get_font( 'list' ),
                      'fontSelectors'   => CZR_init::$instance -> font_selectors,

                      'wpBuiltinSettings' => CZR_customize::$instance -> czr_fn_get_wp_builtin_settings(),
                      'themeOptionsPrefix'  => CZR_THEME_OPTIONS,
                  )
              )
        );

        if ( 'modern' != $this->_style_version_suffix ) {
            add_filter( 'tc_user_options_style', array( $this, 'czr_fn_write_preview_style_classic' ) );
        }
    }


    //hook : customize_preview_init
    function czr_fn_add_preview_footer_action() {
      add_action( 'wp_footer', array( $this, 'czr_fn_add_customize_preview_data' ) , 20 );
    }


    //hook : wp_footer in the preview
    function czr_fn_add_customize_preview_data() {
      global $wp_query, $wp_customize;

      $_wp_conditionals = array();

      //export only the conditional tags
      foreach( (array)$wp_query as $prop => $val ) {
        if (  false === strpos($prop, 'is_') )
          continue;
        if ( 'is_home' == $prop )
          $val = czr_fn_is_real_home();

        $_wp_conditionals[$prop] = $val;
      }

      ?>
        <script id="czr-customizer-data">
          (function ( _export ){
            _export.czr_wp_conditionals = <?php echo wp_json_encode( $_wp_conditionals ) ?>;
          })( _wpCustomizeSettings );
        </script>
      <?php
    }


    // the localized translated strings property of the global themeServerControlParams
    function czr_fn_get_translated_strings() {
      return apply_filters('controls_translated_strings',
          array(
                'edit' => __('Edit', 'customizr'),
                'close' => __('Close', 'customizr'),
                'notset' => __('Not set', 'customizr'),
                'rss' => __('Rss', 'customizr'),
                'selectSocialIcon' => __('Select a social icon', 'customizr'),
                'followUs' => __('Follow us on', 'customizr'),
                'successMessage' => __('Done !', 'customizr'),
                'socialLinkAdded' => __('New Social Link created ! Scroll down to edit it.', 'customizr'),
                'readDocumentation' => __('Learn more about this in the documentation', 'customizr'),
                //WP TEXT EDITOR MODULE
                'textEditorOpen' => __('Edit', 'customizr'),
                'textEditorClose' => __('Close Editor', 'customizr'),
                //SLIDER MODULE
                'slideAdded'   => __('New Slide created ! Scroll down to edit it.', 'customizr'),
                'slideTitle'   => __( 'Slide', 'customizr'),
                'postSliderNote' => __( "This option generates a home page slider based on your last posts, starting from the most recent or the featured (sticky) post(s) if any.", "customizr" ),
                'sidenavNote'  => sprintf( '%1$s<br/>%2$s',
                                    __( 'The side on which the menu is revealed depends on the choosen header layout.', 'customizr'),
                                    sprintf( __("To change the global header layout, %s" , "customizr"),
                                      sprintf( '<a href="%1$s" title="%3$s">%2$s &raquo;</a>',
                                        "javascript:wp.customize.section('header_layout_sec').focus();",
                                        __("jump to the Design and Layout section" , "customizr"),
                                        __("Change the header layout", "customizr")
                                      )
                                    )
                                  ),


                'readDocumentation' => __('Learn more about this in the documentation', 'customizr'),
                'Settings' => __('Settings', 'customizr'),
                'Options for' => __('Options for', 'customizr')
          )
      );
    }

  }
endif;

?><?php
/**
* Add controls to customizer
*
*/
if ( ! class_exists( 'CZR_controls' ) ) :
  class CZR_controls extends WP_Customize_Control  {
      public $type;
      public $link;
      public $title;
      public $label;
      public $buttontext;
      public $settings;
      public $hr_after;
      public $notice;
      //number vars
      public $step;
      public $min;
      public $icon;

      public $ubq_section;

      static $enqueued_resources;

      public function render_content()  {

        do_action( '__before_setting_control' , $this -> id );

        switch ( $this->type) {
            case 'hr':
              echo '<hr class="czr-customizer-separator" />';
            break;


            case 'title' :
              ?>
              <?php if (isset( $this->title)) : ?>
              <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <?php if (isset( $this->notice)) : ?>
              <i class="czr-notice"><?php echo $this -> notice ?></i>
             <?php endif; ?>

            <?php
            break;

            case 'select':
              if ( empty( $this->choices ) )
                return;
              ?>
              <?php if (!empty( $this->title)) : ?>
                <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <label>
                <span class="customize-control-title"><?php echo $this->label; ?></span>
                <?php $this -> czr_fn_print_select_control( in_array( $this->id, array( CZR_THEME_OPTIONS.'[tc_fonts]', CZR_THEME_OPTIONS.'[tc_skin]' ) ) ? 'czrSelect2 no-selecter-js' : '' ) ?>
                <?php if(!empty( $this -> notice)) : ?>
                  <span class="czr-notice"><?php echo $this -> notice ?></span>
                <?php endif; ?>
              </label>
              <?php
              if ( CZR_THEME_OPTIONS.'[tc_front_slider]' == $this -> id ) {
                //retrieve all sliders in option array
                $sliders          = czr_fn_opt( 'tc_sliders' );

                if ( empty( $sliders ) ) {
                  printf('<div class="czr-notice" style="padding: 5px;"><span class="czr-notice">%1$s<br/><a class="button-primary" href="%2$s" target="_blank">%3$s</a><br/><span class="tc-notice">%4$s <a href="%5$s" title="%6$s" target="_blank">%6$s</a></span></span>',
                    __("You haven't create any slider yet. Go to the media library, edit your images and add them to your sliders.", "customizr" ),
                    admin_url( 'upload.php?mode=list' ),
                    __( 'Create a slider' , 'customizr' ),
                    __( 'Need help to create a slider ?' , 'customizr' ),
                    esc_url( "http://docs.presscustomizr.com/article/3-creating-a-slider-with-customizr-wordpress-theme" ),
                    __( 'Check the documentation' , 'customizr' )
                  );
                }
              }
            break;


            case 'number':
              ?>
              <?php if (isset( $this->title)) : ?>
                <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <label>
                <span class="czr-number-label customize-control-title"><?php echo $this->label ?></span>
                <input <?php $this->link() ?> type="number" step="<?php echo $this-> step ?>" min="<?php echo $this-> min ?>" id="posts_per_page" value="<?php echo $this->value() ?>" class="czr-number-input small-text">
                <?php if(!empty( $this -> notice)) : ?>
                  <span class="czr-notice"><?php echo $this-> notice ?></span>
                <?php endif; ?>
              </label>
              <?php
              break;

            case 'checkbox':
            case 'nimblecheck':
              ?>
              <?php if (isset( $this->title)) : ?>
                <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>

              <?php if ( 'checkbox' === $this->type ) : ?>
                <?php
                    printf('<div class="czr-check-label"><label><span class="customize-control-title">%1$s</span></label></div>',
                      $this->label
                    );
                ?>
                <input <?php $this->link(); ?> type="checkbox" value="<?php echo esc_attr( $this->value() ); ?>"  <?php czr_fn_checked( $this->value() ); ?> />
              <?php elseif ( 'nimblecheck' === $this->type ) : ?>
                <div class="czr-control-nimblecheck">
                  <?php
                    printf('<div class="czr-check-label"><label><span class="customize-control-title">%1$s</span></label></div>',
                      $this->label
                    );
                  ?>
                  <div class="nimblecheck-wrap">
                    <input id="nimblecheck-<?php echo $this -> id; ?>" <?php $this->link(); ?> type="checkbox" value="<?php echo esc_attr( $this->value() ); ?>"  <?php czr_fn_checked( $this->value() ); ?> class="nimblecheck-input">
                    <label for="nimblecheck-<?php echo $this -> id; ?>" class="nimblecheck-label">Switch</label>
                  </div>
                </div>
              <?php endif; ?>

              <?php if(!empty( $this -> notice)) : ?>
               <span class="czr-notice"><?php echo $this-> notice ?></span>
              <?php endif; ?>
              <?php
            break;

            case 'textarea':
              ?>
              <?php if (isset( $this->title)) : ?>
                <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <label>
                <span class="customize-control-title"><?php echo $this->label; ?></span>
                <?php if(!empty( $this -> notice)) : ?>
                  <span class="czr-notice"><?php echo $this-> notice; ?></span>
                <?php endif; ?>
                <textarea class="widefat" rows="3" cols="10" <?php $this->link(); ?>><?php echo esc_html( $this->value() ); ?></textarea>
              </label>
              <?php
              break;

            case 'url':
            case 'email':
              ?>
              <?php if (isset( $this->title)) : ?>
              <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <?php
              printf('<label><span class="customize-control-title %1$s">%2$s</span><input type="text" value="%3$s" %4$s /></label>',
                ! empty( $this -> icon) ? $this -> icon : '',
                $this->label,
                call_user_func( 'czr_fn_sanitize_' . $this -> type, $this->value() ),
                call_user_func( array( $this, 'get'.'_'.'link' ) )
              );
              break;


            default:
              global $wp_version;
              ?>
              <?php if (isset( $this->title)) : ?>
                <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
              <?php endif; ?>
              <label>
                <?php if ( ! empty( $this->label ) ) : ?>
                  <span class="customize-control-title"><?php echo $this->label; ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $this->description ) ) : ?>
                  <span class="description customize-control-description"><?php echo $this->description; ?></span>;;;
                <?php endif; ?>
                <?php if ( ! version_compare( $wp_version, '4.0', '>=' ) ) : ?>
                  <input type="<?php echo esc_attr( $this->type ); ?>" value="<?php echo esc_attr( $this->value() ); ?>" <?php $this->link(); ?> />
                <?php else : ?>
                  <input type="<?php echo esc_attr( $this->type ); ?>" <?php $this->input_attrs(); ?> value="<?php echo esc_attr( $this->value() ); ?>" <?php $this->link(); ?> />
                <?php endif; ?>
                <?php if(!empty( $this -> notice)) : ?>
                  <span class="czr-notice"><?php echo $this-> notice; ?></span>
                <?php endif; ?>
              </label>
              <?php
            break;
          }//end switch
          do_action( '__after_setting_control' , $this -> id );
     }//end function




    private function czr_fn_print_select_control($class) {
      printf('<select %1$s class="%2$s">%3$s</select>',
        call_user_func( array( $this, 'get'.'_'.'link' ) ),
        $class,
        $this -> czr_fn_get_select_options()
      );
    }


    private function czr_fn_get_select_options() {
      $_options_html = '';
      switch ( $this -> id ) {
        case CZR_THEME_OPTIONS.'[tc_fonts]':
          foreach ( $this -> choices as $_opt_group => $_opt_list ) {
            $_options = array();
            foreach ( $_opt_list['list'] as $label => $value ) {
              $_options[] = sprintf('<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $label ),
                selected( $this->value(), $value, false ),
                $value
              );
            }
            $_options_html .= sprintf('<optgroup label="%1$s">%2$s</optgroup>',
              $_opt_list['name'],
              implode($_options)
            );
          }
        break;

        case CZR_THEME_OPTIONS.'[tc_skin]':
          $_data_hex  = '';
          //only for czr3
          if ( defined( 'CZR_IS_MODERN_STYLE' ) && CZR_IS_MODERN_STYLE )
            return;

          $_color_map = CZR_utils::$inst->czr_fn_get_skin_color( 'all' );
          //Get the color map array structured as follow
          // array(
          //       'blue.css'        =>  array( '#08c', '#005580' ),
          //       ...
          // )
          foreach ( $this->choices as $value => $label ) {
            if ( is_array($_color_map) && isset( $_color_map[esc_attr( $value )] ) )
              $_data_hex       = isset( $_color_map[esc_attr( $value )][0] ) ? $_color_map[esc_attr( $value )][0] : '';
            $_options_html .= sprintf('<option value="%1$s" %2$s data-hex="%4$s">%3$s</option>',
              esc_attr( $value ),
              selected( $this->value(), $value, false ),
              $label,
              $_data_hex
            );
          }
        break;
        default:
          foreach ( $this->choices as $value => $label ) {
            $_options_html .= sprintf('<option value="%1$s" %2$s>%3$s</option>',
              esc_attr( $value ),
              selected( $this->value(), $value, false ),
              $label
            );
          }
        break;
      }//end switch
      return $_options_html;
    }//end of fn


    /**
    * Enqueue scripts/styles
    * fired by the parent Control class constructor
    *
    */
    public function enqueue() {
        if ( ! empty( self::$enqueued_resources ) )
          return;

        self::$enqueued_resources = true;

        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'wp-color-picker' );
    }

    /**
    * Refresh the parameters passed to the JavaScript via JSON.
    *
    *
    * @Override
    * @see WP_Customize_Control::to_json()
    */
    public function to_json() {
      parent::to_json();
      if ( is_array( $this->ubq_section ) && array_key_exists( 'section', $this->ubq_section ) ) {
        $this->json['ubq_section'] = $this->ubq_section;
      }
    }
  }//end of class
endif;
?><?php
/*
*/
if ( class_exists('WP_Customize_Cropped_Image_Control') && ! class_exists( 'CZR_Customize_Cropped_Image_Control' ) ) :
  class CZR_Customize_Cropped_Image_Control extends WP_Customize_Cropped_Image_Control {
    public $type = 'czr_cropped_image';
    public $title;
    public $notice;
    public $dst_width;
    public $dst_height;


    /**
    * Refresh the parameters passed to the JavaScript via JSON.
    *
    *
    * @Override
    * @see WP_Customize_Control::to_json()
    */
    public function to_json() {
        parent::to_json();
        $this->json['title']  = !empty( $this -> title )  ? esc_html( $this -> title ) : '';
        $this->json['notice'] = !empty( $this -> notice ) ?           $this -> notice  : '';

        $this->json['dst_width']  = isset( $this -> dst_width )  ?  $this -> dst_width  : $this -> width;
        $this->json['dst_height'] = isset( $this -> dst_height ) ?  $this -> dst_height : $this -> height;
        //overload WP_Customize_Upload_Control
        //we need to re-build the absolute url of the logo src set in old Customizr
        $value = $this->value();
        if ( $value ) {
          //re-build the absolute url if the value isn't an attachment id before retrieving the id
          if ( (int) esc_attr( $value ) < 1 ) {
            $upload_dir = wp_upload_dir();
            $value  = false !== strpos( $value , '/wp-content/' ) ? $value : $upload_dir['baseurl'] . $value;
          }
          // Get the attachment model for the existing file.
          $attachment_id = attachment_url_to_postid( $value );
          if ( $attachment_id ) {
              $this->json['attachment'] = wp_prepare_attachment_for_js( $attachment_id );
          }
        }//end overload

    }

    /**
    * Render a JS template for the content of the media control.
    *
    * @since 3.4.19
    * @package      Customizr
    *
    * @Override
    * @see WP_Customize_Control::content_template()
    */
    public function content_template() {
      ?>
      <# if ( data.title ) { #>
          <h3 class="czr-customizr-title">{{{ data.title }}}</h3>
        <# } #>
          <?php parent::content_template(); ?>
        <# if ( data.notice ) { #>
          <span class="czr-notice">{{{ data.notice }}}</span>
        <# } #>
      <?php
    }
  }//end class
endif;
?><?php
/*
*/
if ( class_exists('WP_Customize_Code_Editor_Control') && ! class_exists( 'CZR_Customize_Code_Editor_Control' ) ) :
  class CZR_Customize_Code_Editor_Control extends WP_Customize_Code_Editor_Control {

    public $type = 'czr_code_editor';
    public $title;
    public $notice;

    /**
     * Refresh the parameters passed to the JavaScript via JSON.
     *
     * @see WP_Customize_Control::json()
     *
     * @return array Array of parameters passed to the JavaScript.
     */
    public function json() {
        $json = parent::json();
        if ( is_array( $json ) ) {
            $json['title']  = !empty( $this -> title )  ? esc_html( $this -> title ) : '';
            $json['notice'] = !empty( $this -> notice ) ?           $this -> notice  : '';
        }

        return $json;
    }


    /**
    * Render a JS template for the content of the media control.
    *
    * @since 3.4.19
    * @package      Customizr
    *
    * @Override
    * @see WP_Customize_Control::content_template()
    */
    public function content_template() {
      ?>
      <# if ( data.title ) { #>
          <h3 class="czr-customizr-title">{{{ data.title }}}</h3>
        <# } #>
          <?php parent::content_template(); ?>
        <# if ( data.notice ) { #>
          <span class="czr-notice">{{{ data.notice }}}</span>
        <# } #>
      <?php
    }
  }//end class
endif;
?><?php
/**************************************************************************************************
* MULTIPICKER CLASSES
***************************************************************************************************/
if ( ! class_exists( 'CZR_Customize_Multipicker_Control' ) ) :
  /**
  * Customize Multi-picker Control Class
  *
  * @package WordPress
  * @subpackage Customize
  * @since 3.4.10
  */
  abstract class CZR_Customize_Multipicker_Control extends CZR_controls {

    public function render_content() {

      if ( ! $this -> type ) return;
      do_action( '__before_setting_control' , $this -> id );

      $dropdown = $this -> czr_fn_get_dropdown_multipicker();

      if ( empty( $dropdown ) ) return;

      $dropdown = str_replace( '<select', '<select multiple="multiple"' . $this->get_link(), $dropdown );
      //start rendering
      if (!empty( $this->title)) :
    ?>
        <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
      <?php endif; ?>

      <label>
        <span class="customize-control-title"><?php echo $this->label; ?></span>
        <?php echo $dropdown; ?>
        <?php if(!empty( $this -> notice)) : ?>
          <span class="czr-notice"><?php echo $this -> notice ?></span>
         <?php endif; ?>
      </label>
    <?php
      do_action( '__after_setting_control' , $this -> id );
    }

    //to define in the extended classes
    abstract public function czr_fn_get_dropdown_multipicker();
  }//end class
endif;

if ( ! class_exists( 'CZR_Customize_Multipicker_Categories_Control' ) ) :
  class CZR_Customize_Multipicker_Categories_Control extends CZR_Customize_Multipicker_Control {

    public function czr_fn_get_dropdown_multipicker() {
      $cats_dropdown = wp_dropdown_categories(
          array(
              'name'               => '_customize-'.$this->type,
              'id'                 => $this -> id,
              //hide empty, set it to false to avoid complains
              'hide_empty'         => 0 ,
              'echo'               => 0 ,
              'walker'             => new CZR_Walker_CategoryDropdown_Multipicker(),
              'hierarchical'       => 1,
              'class'              => 'czrSelect2 no-selecter-js '.$this->type,
              'selected'           => implode(',', $this->value() )
          )
      );

      return $cats_dropdown;
    }
  }
endif;


/**
 * @ dropdown multi-select walker
 * Create HTML dropdown list of Categories.
 *
 * @package WordPress
 * @since 2.1.0
 * @uses Walker
 *
 * we need to allow more than one "selected" attribute
 */

if ( ! class_exists( 'CZR_Walker_CategoryDropdown_Multipicker' ) ) :
  class CZR_Walker_CategoryDropdown_Multipicker extends Walker_CategoryDropdown {
    /**
     * Start the element output.
     *
     * @Override
     *
     * @see Walker::start_el()
     *
     * @param string $output   Passed by reference. Used to append additional content.
     * @param object $category Category data object.
     * @param int    $depth    Depth of category. Used for padding.
     * @param array  $args     Uses 'selected', 'show_count', and 'value_field' keys, if they exist.
     *                         See {@see wp_dropdown_categories()}.
     */
    public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
      $pad = str_repeat('&mdash;', $depth );
      /** This filter is documented in wp-includes/category-template.php */
      $cat_name = apply_filters( 'list_cats', $category->name, $category );

      $value_field = 'term_id';

      $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $category->{$value_field} ) . "\"";
      //Treat selected arg as array
      if ( in_array( (string) $category->{$value_field}, explode( ',', $args['selected'] ) ) )
        $output .= ' selected="selected"';

      $output .= '>';
      $output .= $pad.$cat_name;
      if ( $args['show_count'] )
        $output .= '&nbsp;&nbsp;('. number_format_i18n( $category->count ) .')';
      $output .= "</option>\n";
    }
  }
endif;
?><?php
/*********************************************************************************
* Old upload control used until v3.4.18, still used if current version of WP is < 4.3
**********************************************************************************/
if ( ! class_exists( 'CZR_Customize_Upload_Control' ) ) :
  /**
   * Customize Upload Control Class
   *
   * @package WordPress
   * @subpackage Customize
   * @since 3.4.0
   */
  class CZR_Customize_Upload_Control extends WP_Customize_Control {
    public $type    = 'czr_upload';
    public $removed = '';
    public $context;
    public $extensions = array();
    public $title;
    public $notice;

    /**
     * Enqueue control related scripts/styles.
     *
     * @since 3.4.0
     */
    public function enqueue() {
      wp_enqueue_script( 'wp-plupload' );
    }

    /**
     * Refresh the parameters passed to the JavaScript via JSON.
     *
     * @since 3.4.0
     * @uses WP_Customize_Control::to_json()
     */
    public function to_json() {
      parent::to_json();

      $this->json['removed'] = $this->removed;

      if ( $this->context )
        $this->json['context'] = $this->context;

      if ( $this->extensions )
        $this->json['extensions'] = implode( ',', $this->extensions );
    }

    /**
     * Render the control's content.
     *
     * @since 3.4.0
     */
    public function render_content() {
      do_action( '__before_setting_control' , $this -> id );
      ?>
      <?php if ( isset( $this->title) ) : ?>
        <h3 class="czr-customizr-title"><?php echo esc_html( $this->title); ?></h3>
      <?php endif; ?>
      <label>
        <?php if ( ! empty( $this->label ) ) : ?>
          <span class="customize-control-title"><?php echo $this->label; ?></span>
        <?php endif;
        if ( ! empty( $this->description ) ) : ?>
          <span class="description customize-control-description"><?php echo $this->description; ?></span>
        <?php endif; ?>
        <div>
          <a href="#" class="button-secondary czr-upload"><?php _e( 'Upload' , 'customizr'  ); ?></a>
          <a href="#" class="remove"><?php _e( 'Remove' , 'customizr'  ); ?></a>
        </div>
        <?php if(!empty( $this -> notice)) : ?>
          <span class="czr-notice"><?php echo $this -> notice; ?></span>
        <?php endif; ?>
      </label>
      <?php
      do_action( '__after_setting_control' , $this -> id );
    }
  }
endif;
?><?php
/***************************************************
* AUGMENTS WP CUSTOMIZE PANELS
***************************************************/
if ( ! class_exists( 'CZR_Customize_Panels') ) :
  class CZR_Customize_Panels extends WP_Customize_Panel {
    public $czr_subtitle = '';

    // function __construct( $manager, $id, $args = array() ) {
    //   $keys = array_keys( get_object_vars( $this ) );
    //   foreach ( $keys as $key ) {
    //     if ( isset( $args[ $key ] ) ) {
    //       $this->$key = $args[ $key ];
    //     }
    //   }
    //   parent::__construct( $manager, $id, $args );


    // }

    public function json() {
      $array = parent::json();
      $array['czr_subtitle'] = html_entity_decode( $this->czr_subtitle, ENT_QUOTES, get_bloginfo( 'charset' ) );
      return $array;
    }


     /**
     * Render the panel's JS templates.
     *
     * This function is only run for panel types that have been registered with
     * WP_Customize_Manager::register_panel_type().
     *
     * @since 4.3.0
     *
     * @see WP_Customize_Manager::register_panel_type()
     */
    public function print_template() {
      ?>
      <script type="text/html" id="tmpl-customize-panel-czr_panel">
        <?php $this->czr_fn_render_template(); ?>
      </script>
          <?php
    }

    /**
     * An Underscore (JS) template for rendering this panel's container.
     *
     * Class variables for this panel class are available in the `data` JS object;
     * export custom variables by overriding WP_Customize_Panel::json().
     *
     * @see WP_Customize_Panel::print_template()
     *
     * @since 4.3.0
     * @access protected
     */
    protected function czr_fn_render_template() {
      ?>
      <li id="accordion-panel-{{ data.id }}" class="accordion-section control-section control-panel control-panel-{{ data.type }}">
        <h3 class="accordion-section-title" tabindex="0">
        <button type="button" class="accordion-trigger" aria-expanded="false" aria-controls="{{ data.type }}_sec-content">
        {{ data.title }}
        <span class="czr-panel-subtitle">{{ data.czr_subtitle }}</span>
				</button>          
          <span class="screen-reader-text"><?php _e( 'Press return or enter to open this panel', 'customizr' ); ?></span>
        </h3>
        <ul class="accordion-sub-container control-panel-content"></ul>
      </li>
      <?php
    }
  }
endif;
?><?php
/**
 * Base customizer section.
 */
class CZR_Customize_Sections extends WP_Customize_Section {

    /**
     * The type of customize section being rendered.
     *
     * @var    string
     */
    public $type = 'czr-customize-sections';

    public $ubq_panel;


    /**
     * Add custom parameters to pass to the JS via JSON.
     *
     * @return void
     * @override
     */
    public function json() {
      $json = parent::json();
      if ( is_array( $this->ubq_panel ) && array_key_exists( 'panel', $this->ubq_panel ) ) {
        $json['ubq_panel'] = $this->ubq_panel;
      }
      return $json;
    }
}
?>
<?php
/**
 * Pro customizer section.
 * highly based on
 * https://github.com/justintadlock/trt-customizer-pro/blob/master/example-1/section-pro.php
 */
class CZR_Customize_Section_Pro extends WP_Customize_Section {

    /**
     * The type of customize section being rendered.
     *
     * @var    string
     */
    public $type ='czr-customize-section-pro';

    public $pro_subtitle = '';
    public $pro_doc_url = '';

    /**
     * Custom button text to output.
     *
     * @var    string
     */

    public $pro_text = '';
    /**
     *
     * @var    string
     */
    public $pro_url = '';


    /**
     * Add custom parameters to pass to the JS via JSON.
     *
     * @return void
     * @override
     */
    public function json() {
      $json = parent::json();
      $json['pro_subtitle'] = $this->pro_subtitle;
      $json['pro_doc_url']  = $this->pro_doc_url;
      $json['pro_text'] = $this->pro_text;
      $json['pro_url']  = $this->pro_url;
      return $json;
    }

    //overrides the default template
    protected function render_template() { ?>
      <li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }} cannot-expand">
          <h3 style="padding: 10px 2% 18px 14px;display: inline-block;width: 93%;" class="accordion-section-title">
            {{ data.title }}
            <a href="{{ data.pro_doc_url }}" style="font-size: 0.7em;display: block;float: left;position: absolute;bottom: 0px;font-style: italic;color: #555d66;" target="_blank" title="{{ data.pro_subtitle }}">{{ data.pro_subtitle }}</a>
            <# if ( data.pro_text && data.pro_url ) { #>
              <a href="{{ data.pro_url }}" class="button button-secondary alignright" target="_blank" title="{{ data.pro_text }}" style="margin-top:0px">{{ data.pro_text }}</a>
            <# } #>
          </h3>
        </li>
    <?php }
}
?>
