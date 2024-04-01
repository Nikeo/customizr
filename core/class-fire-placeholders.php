<?php
/**
* This class must be instantiated if is_admin() for the ajax call to work
* => because ajax request are fired with the admin_url(), even on front-end.
* more here : https://codex.wordpress.org/AJAX_in_Plugins
*
*/
if ( ! class_exists( 'CZR_placeholders' ) ) :
  #[\AllowDynamicProperties]
  class CZR_placeholders {
    //Access any method or var of the class with classname::$instance -> var or method():
    static $instance;
    public $nonce_handle;

    function __construct () {
        self::$instance =& $this;

        add_action( 'init'                             , array( $this, 'czr_fn_placeholders_ajax_setup') );


        add_action( 'template_redirect'                , array( $this, 'czr_fn_maybe_register_front_placeholders'), 500 );
        /*
        * To display widget placeholders in the customizer preview
        * @since WP 3.9.0
        */
        add_filter( 'dynamic_sidebar_has_widgets'      , array( $this, 'czr_fn_maybe_add_preview_widget_placeholders' ), 9999, 2 );
    }



    /*****************************************************
    * ADMIN AJAX HOOKS ALL PLACEHOLDERS
    *****************************************************/
    /**
    * hook : init => because we need to fire this function before the admin_ajax.php call
    * @since v3.4+
    */
    function czr_fn_placeholders_ajax_setup() {
        if ( ! czr_fn_is_front_help_enabled() )
            return;

        add_action( 'wp_ajax_dismiss_thumbnail_help'    , array( $this, 'czr_fn_dismiss_thumbnail_help' ) );
        add_action( 'wp_ajax_dismiss_img_smartload_help', array( $this, 'czr_fn_dismiss_img_smartload_help' ) );
        /*
        add_action( 'wp_ajax_dismiss_sidenav_help'      , array( $this, 'czr_fn_dismiss_sidenav_help' ) );
        add_action( 'wp_ajax_dismiss_second_menu_notice', array( $this, 'czr_fn_dismiss_second_menu_notice' ) );
        add_action( 'wp_ajax_dismiss_main_menu_notice'  , array( $this, 'czr_fn_dismiss_main_menu_notice' ) );

        add_action( 'wp_ajax_dismiss_slider_notice'     , array( $this, 'czr_fn_dismiss_slider_notice' ) );
        add_action( 'wp_ajax_remove_slider'             , array( $this, 'czr_fn_remove_slider' ) );
        */
        add_action( 'wp_ajax_dismiss_fp_notice'         , array( $this, 'czr_fn_dismiss_fp_notice' ) );
        add_action( 'wp_ajax_remove_fp'                 , array( $this, 'czr_fn_remove_fp' ) );

        add_action( 'wp_ajax_dismiss_widget_notice'     , array( $this, 'czr_fn_dismiss_widget_notice' ) );
    }





    //hook: template_redirect
    function czr_fn_maybe_register_front_placeholders() {

        //do nothing when is customizing or is admin or front help notices disabled
        if ( is_admin() || czr_fn_is_customizing() || ! czr_fn_is_front_help_enabled() )
            return;

        //do nothing when user not logged in or cannot edit theme options, unless is CZR_DEV == true
        if ( !( defined('CZR_DEV') && true === CZR_DEV ) ) {
            if ( ! ( is_user_logged_in() && current_user_can('edit_theme_options') ) )
                return;
        }

        //enqueue resources
        add_filter( 'czr_enqueue_placeholders_resources', '__return_true' );

        //one nonce for all
        $this->nonce_handle                  = wp_create_nonce( 'czr-helpblock-nonce' );
        $this->placeholder_template_callback = array( $this, 'czr_fn_help_block_template' );

        $help_blocks = array(
            //left sidebar
            array(
                'template'    => false,
                'hook'        => '__before_left_sidebar_widgets',
                'callback'    => $this->placeholder_template_callback,
                'cb_params'   => array(
                    'dismiss_action'         => 'dismiss_widget_notice',
                    'element_tag'            => 'aside',
                    'position'               => 'sidebar',
                    'help_title'             => __( 'The left sidebar has no widgets', 'customizr' ),
                    'help_message'           =>  sprintf( __( 'Add widgets to this sidebar %s or %s.', 'customizr' ),
                        sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
                            czr_fn_get_customizer_url( array( 'panel' => 'widgets' ) ),
                            __( 'Add widgets', 'customizr' ),
                            __( 'now', 'customizr' )
                        ),
                        sprintf('<a class="tc-inline-dismiss-notice" data-position="sidebar" href="#" title="%1$s">%1$s</a>',
                          __( 'dismiss this notice', 'customizr')
                        )
                    ),
                    'help_secondary_message' => sprintf('<p><i>%1s <a href="http:%2$s" title="%3$s" target="blank" rel="noopener noreferrer">%4$s</a></i></p>',
                        __( 'You can also remove this sidebar by changing the current page layout.', 'customizr' ),
                        '//docs.presscustomizr.com/article/107-customizr-theme-options-pages-and-posts-layout',
                        __( 'Changing the layout in the Customizr theme' , 'customizr'),
                        __( 'See the theme documentation.' , 'customizr' )
                    )
                ),
                'controller'               => array( $this, 'czr_fn_is_left_sidebar_widget_placeholder_enabled' )
            ),
            //right sidebar
            array(
                'template'    => false,
                'hook'        => '__before_right_sidebar_widgets',
                'callback'    => $this->placeholder_template_callback,
                'cb_params'   => array(
                    'dismiss_action'         => 'dismiss_widget_notice',
                    'element_tag'            => 'aside',
                    'position'               => 'sidebar',
                    'help_title'             => __( 'The right sidebar has no widgets', 'customizr'),
                    'help_message'           => sprintf( __( 'Add widgets to the footer %s or %s.', 'customizr'),
                        sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', czr_fn_get_customizer_url( array( 'panel' => 'widgets') ), __( 'Add widgets', 'customizr'), __('now', 'customizr') ),
                        sprintf('<a class="tc-inline-dismiss-notice tc-dismiss-notice" href="#" title="%1$s">%1$s</a>',
                          __( 'dismiss this notice', 'customizr')
                        )
                    ),
                    'help_secondary_message' => sprintf('<p><i>%1s <a href="http:%2$s" title="%3$s" target="blank" rel="noopener noreferrer">%4$s</a></i></p>',
                        __( 'You can also remove this sidebar by changing the current page layout.', 'customizr' ),
                        '//docs.presscustomizr.com/article/107-customizr-theme-options-pages-and-posts-layout',
                        __( 'Changing the layout in the Customizr theme' , 'customizr'),
                        __( 'See the theme documentation.' , 'customizr' )
                    )
                ),
                'controller'               => array( $this, 'czr_fn_is_right_sidebar_widget_placeholder_enabled' )
            ),
            //footer_horizontal_widgets
            // array(
            //     'template'    => false,
            //     'hook'        => '__before_footer',
            //     'priority'    => '999',
            //     'callback'    => $this->placeholder_template_callback,
            //     'cb_params'   => array(
            //         'dismiss_action'         => 'dismiss_widget_notice',
            //         'element_tag'            => 'aside',
            //         'element_class'          => 'col-12 horizontal-footer',
            //         'position'               => 'horizontal_footer',
            //         'help_title'             => __( 'The horizontal footer widget area has no widgets', 'customizr'),
            //         'help_message'           => sprintf( __( 'Add widgets to the horizontal footer widget area %s or %s.', 'customizr'),
            //             sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', czr_fn_get_customizer_url( array( 'panel' => 'widgets') ), __( 'Add widgets', 'customizr'), __('now', 'customizr') ),
            //             sprintf('<a class="tc-inline-dismiss-notice tc-dismiss-notice" href="#" title="%1$s">%1$s</a>',
            //               __( 'dismiss this notice', 'customizr')
            //             )
            //         ),
            //     ),
            //     'controller'               => array( $this, 'czr_fn_is_horizontal_footer_widgets_placeholder_enabled' )
            // ),
            //footer widgets
            array(
                'template'    => false,
                'hook'        => '__before_inner_footer',
                'callback'    => $this->placeholder_template_callback,
                'cb_params'   => array(
                    'dismiss_action'         => 'dismiss_widget_notice',
                    'element_tag'            => 'aside',
                    'position'               => 'footer',
                    'help_title'             => __( 'The footer has no widgets', 'customizr'),
                    'help_message'           => sprintf( __( 'Add widgets to the footer %s or %s.', 'customizr'),
                        sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', czr_fn_get_customizer_url( array( 'panel' => 'widgets') ), __( 'Add widgets', 'customizr'), __('now', 'customizr') ),
                        sprintf('<a class="tc-inline-dismiss-notice tc-dismiss-notice" href="#" title="%1$s">%1$s</a>',
                          __( 'dismiss this notice', 'customizr')
                        )
                    ),
                ),
                'controller'               => array( $this, 'czr_fn_is_footer_widgets_placeholder_enabled' )
            ),
            //featured image in posts
            array(
                'template'    => false,
                'hook'        => '__before_content_inner',
                'callback'    => $this->placeholder_template_callback,
                'cb_params'   => array(
                    'dismiss_action'         => 'dismiss_thumbnail_help',
                    'help_message'           => __( "You can display your post's featured image here if you have set one.", 'customizr' ),
                    'help_secondary_message' => sprintf( '<p>%1$s</p><p>%2$s</p>',
                        sprintf( __("%s to display a featured image here.", 'customizr'),
                            sprintf( '<strong><a href="%1$s" title="%2$s">%2$s</a></strong>', czr_fn_get_customizer_url( array( "section" => "single_posts_sec") ), __( 'Jump to the customizer now', 'customizr') )
                        ),
                        sprintf( __( "Don't know how to set a featured image to a post? Learn how in the %s.", 'customizr' ),
                            sprintf('<a href="%1$s" title="%2$s" target="_blank">%2$s</a> <span class="tc-external fas fa-external-link-alt"></span>' , esc_url('codex.wordpress.org/Post_Thumbnails#Setting_a_Post_Thumbnail'), __('WordPress documentation' , 'customizr' ) )
                        )
                    ),
                ),
                'controller'               => array( $this, 'czr_fn_is_post_thumbnail_help_on' )
            ),
            //featured image in pages
            array(
                 'template'    => false,
                 'hook'        => '__before_content_inner',
                 'callback'    => $this->placeholder_template_callback,
                 'cb_params'   => array(
                     'dismiss_action'         => 'dismiss_thumbnail_help',
                     'help_message'           =>  __( "You can display your page's featured image here if you have set one.", 'customizr' ),
                     'help_secondary_message' => sprintf( '<p>%1$s</p><p>%2$s</p>',
                         sprintf( __("%s to display a featured image here.", 'customizr'),
                             sprintf( '<strong><a href="%1$s" title="%2$s">%2$s</a></strong>', czr_fn_get_customizer_url( array( "section" => "single_pages_sec" ) ), __( 'Jump to the customizer now', 'customizr') )
                         ),
                         sprintf( __( "Don't know how to set a featured image to a page? Learn how in the %s.", 'customizr' ),
                             sprintf('<a href="%1$s" title="%2$s" target="_blank">%2$s</a> <span class="tc-external fas fa-external-link-alt"></span>' , esc_url('codex.wordpress.org/Post_Thumbnails#Setting_a_Post_Thumbnail'), __('WordPress documentation' , 'customizr' ) )
                         )
                     ),
                 ),
                 'controller'               => array( $this, 'czr_fn_is_page_thumbnail_help_on' )
            ),
            //smartload in lists of posts
            array(
                'template'    => false,
                'callback'    => $this->placeholder_template_callback,
                'hook'        => '__loop_start',
                'cb_params'   => array(
                    'dismiss_action'         => 'dismiss_img_smartload_help',
                    'element_class'          => 'col-12 tc-post-list-smarload-help',
                    'help_message'           =>  __( 'Did you know you can easily speed up your page load by deferring the loading of the non visible images?', 'customizr' ),
                    'help_secondary_message' =>  sprintf( "<p>%s</p>",
                        sprintf( __("%s and check the option 'Load images on scroll' under 'Website Performances' section.", 'customizr'),
                            sprintf( '<strong><a href="%1$s" title="%2$s">%2$s</a></strong>', czr_fn_get_customizer_url( array( "control" => "tc_img_smart_load", "section" => "performances_sec" ) ), __( "Jump to the customizer now", "customizr") )
                        )
                    )
                ),
                'controller'  => array( $this, 'czr_fn_is_post_lists_img_smartload_help_on' )
            ),
            //featured pages
            // array(
            //     'template'    => false,
            //     'hook'        => '__after_fp',
            //     'callback'    => $this->placeholder_template_callback,
            //     'cb_params'   => array(
            //         'dismiss_action'         => 'dismiss_fp_notice',
            //         'remove_action'          => 'remove_fp',
            //         'remove_selector'        => '.marketing',
            //         'element_class'          => 'col-12 offset-md-6 col-md-6',
            //         'help_message'           =>  sprintf( __("Edit those featured pages %s, or %s (you'll be able to add yours later)." , "customizr"),
            //           sprintf( '<a href="%3$s" title="%1$s">%2$s</a>', __( "Edit those featured pages", "customizr" ), __( "now", "customizr" ), czr_fn_get_customizer_url( array( 'control' => 'tc_show_featured_pages', 'section' => 'frontpage_sec') ) ),
            //           sprintf( '<a href="#" class="tc-inline-remove" title="%1$s">%2$s</a>', __( "Remove the featured pages", "customizr" ), __( "remove them", "customizr" ) )
            //         ),
            //     ),
            //     'controller'               => array( $this, 'czr_fn_is_fp_notice_on' )
            // ),
        );

        //register help blocks
        foreach ( $help_blocks as $help_block_model ) {
            CZR() -> collection -> czr_fn_register( $help_block_model );
        }//foreach


        //different treatment for the smartload in singular as we want to know the number of images present in the post/page content
        //this action hook is triggered only in a page or in a post
        add_action( '__before_content_inner'    , array( $this, 'czr_fn_maybe_filter_the_content_for_smartload_help' ) );
    }




    //hook: __before_content_inner
    function czr_fn_maybe_filter_the_content_for_smartload_help() {
        //not in home
        if ( czr_fn_is_real_home() ) {
            return;
        }

        add_filter( 'the_content'         , array( $this, 'czr_fn_maybe_display_img_smartload_help') , PHP_INT_MAX );
    }



    /**
    * Maybe displays a help block about images smartload for single pages/posts prepended to the content
    * hook : the_content
    */
    function czr_fn_maybe_display_img_smartload_help( $the_content ) {
        if ( $this->czr_fn_is_img_smartload_help_on( $text = $the_content, $min_img_num = 2 ) ) {

            ob_start();
            CZR() -> collection -> czr_fn_register( array(
                    'template'    => false,
                    'callback'    => $this->placeholder_template_callback,
                    'cb_params'   => array(
                        'dismiss_action'         => 'dismiss_img_smartload_help',
                        'help_message'           =>  __( 'Did you know you can easily speed up your page load by deferring the loading of the non visible images?', 'customizr' ),
                        'help_secondary_message' =>  sprintf( "<p>%s</p>",
                            sprintf( __("%s and check the option 'Load images on scroll' under 'Website Performances' section.", 'customizr'),
                                sprintf( '<strong><a href="%1$s" title="%2$s">%2$s</a></strong>', czr_fn_get_customizer_url( array( "control" => "tc_img_smart_load", "section" => "performances_sec" ) ), __( 'Jump to the customizer now', 'customizr' ) )
                            )
                        )
                    ),
                    'render'      => true,
            ) );
            $ph = ob_get_clean();
            return $ph . $the_content;
        }

        return $the_content;
    }





    /*
    * Maybe adds widget placeholders when in customizer preview to the widget areas with no widgets
    */
    //hook: dynamic_sidebar_has_widgets
    function czr_fn_maybe_add_preview_widget_placeholders( $did_one, $index ) {
        if ( !czr_fn_is_customize_preview_frame() || czr_fn_isprevdem() )
            return $did_one;

        if ( $did_one ) {
            return $did_one;
        }

        //gets the filtered default values
        $widgets                     = CZR_widgets::$instance->widgets;
        //Inline style, we really don't need to add this to our syle css or enqueue a different style, right?
        $placeholder_style           = ' style="background:#f7f8f9;padding:7%;text-align:center;border:2px dotted #008ec2;font-size:.875em;"';
        $placeholder_title_style     = ' style="margin:0.5em;font-size:1.2em;line-height:1.5em;color:#444"';
        $placeholder_zone_name_style = ' style="font-weight:bold"';

        if ( array_key_exists( $index, $widgets ) ) {
            printf('<div class="widget" data-czr-panel-focus="widgets">%6$s<div class="czr-placeholder-widget"%3$s><h3%4$s>%1$s<br/><span class="zone-name"%5$s>"%2$s"</span></h3></div></div>',
                __('Add widgets to the zone :', 'customizr'),
                isset( $widgets[ $index ][ 'name' ] ) ? $widgets[ $index ][ 'name' ] : $widgets[ $index ],
                $placeholder_style,
                $placeholder_title_style,
                $placeholder_zone_name_style,
                sprintf( '<div style="position: relative;left: 33px;">%1$s</div>',
                   czr_fn_get_customizer_focus_icon( array( 'wot' => 'section', 'id' => 'sidebar-widgets-' . $index ) )
                )
            );
            $did_one = true;
        }

        return $did_one;
    }



    /*****************************************************
    * THUMBNAIL MENU HELP : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss thumbnail help
    * hook : wp_ajax_dismiss_thumbnail_help
    *
    */
    function czr_fn_dismiss_thumbnail_help() {
        check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
        set_transient( 'tc_thumbnail_help', 'disabled' , 60*60*24*365*20 );//20 years of peace
        wp_die();
    }


    /**
    *
    * @return  bool
    */
    function czr_fn_is_post_thumbnail_help_on() {
        //never display when customizing
        if ( czr_fn_is_customizing() )
            return;

        //always display in DEV mode
        if ( defined('CZR_DEV') && true === CZR_DEV && czr_fn_is_single_post() )
            return true;

        //match all conditions
        return apply_filters(
            'tc_is_post_thumbnail_help_on',
            (
                ! is_admin() && is_user_logged_in() && current_user_can('edit_theme_options') &&
                'disabled' != get_transient( 'tc_thumbnail_help' ) &&
                czr_fn_is_single_post() && 'hide' == czr_fn_opt('tc_single_post_thumb_location')
            )
        );
    }



    /**
    *
    * @return  bool
    */
    function czr_fn_is_page_thumbnail_help_on() {
        //never display when customizing
        if ( czr_fn_is_customizing() )
           return;

        //always display in DEV mode (in the right context)
        if ( defined('CZR_DEV') && true === CZR_DEV && czr_fn_is_single_page() && !czr_fn_is_real_home() )
           return true;

        //match all conditions
        return apply_filters(
            'tc_is_page_thumbnail_help_on',
            (
                ! is_admin() && is_user_logged_in() && current_user_can('edit_theme_options') &&
                'disabled' != get_transient( 'tc_thumbnail_help' ) &&
                czr_fn_is_single_page() && !czr_fn_is_real_home() && 'hide' == czr_fn_opt('tc_single_page_thumb_location')
            )
        );
    }




    /*****************************************************
    * IMG SMARTLOAD MENU HELP : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss images smartload help
    * hook : wp_ajax_dismiss_img_smartload_help
    *
    * @package Customizr
    * @since Customizr 3.4+
    */
    function czr_fn_dismiss_img_smartload_help() {
        check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
        set_transient( 'tc_img_smartload_help', 'disabled' , 60*60*24*365*20 );//20 years of peace
        wp_die();
    }








    /**
    *
    * @return  bool
    */
    function czr_fn_is_post_lists_img_smartload_help_on() {
        return czr_fn_is_list_of_posts() && $this->czr_fn_is_img_smartload_help_on( $text = '', $min_img_num = 0 );
    }



    /**
    *
    * @return  bool
    * @since Customizr 3.4+
    */
    function czr_fn_is_img_smartload_help_on( $text, $min_img_num = 2 ) {
        //never display when customizing
        if ( czr_fn_is_customizing() )
            return;

        if ( $min_img_num ) {
            if ( ! $text ) {
                return false;
            }
        }

        //always display in DEV mode
        if ( defined('CZR_DEV') && true === CZR_DEV ) {
            return true;
        }


        //match all conditions
        return apply_filters(
          'tc_is_img_smartload_help_on',
            ! is_admin() && is_user_logged_in() && current_user_can('edit_theme_options')
            && 1 != esc_attr( czr_fn_opt( 'tc_img_smart_load' ) )
            && 'disabled' != get_transient("tc_img_smartload_help")
            && ! ( $min_img_num ? apply_filters('tc_img_smartload_help_n_images', $min_img_num ) > preg_match_all( '/(<img[^>]+>)/i', $text, $matches ) : false  )
        );
    }



    /************************************************************
    * WIDGET PLACEHOLDERS AJAX JS AND CALLBACK : FOR SIDEBARS AND FOOTER
    ************************************************************/
    /**
    * Dismiss widget notice ajax callback
    * hook : wp_ajax_dismiss_widget_notice
    *
    */
    function czr_fn_dismiss_widget_notice() {
        check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );

        if ( isset( $_POST['position'] ) )
            $_pos = esc_attr( $_POST['position'] );
        else
            wp_die(0);

        //20 years transient
        set_transient( "tc_widget_placehold_{$_pos}", 'disabled' , 60*60*24*365*20 );
        wp_die();
    }


    //helper
    function czr_fn_is_left_sidebar_widget_placeholder_enabled() {
        return $this -> czr_fn_is_widget_placeholder_enabled( 'sidebar' ) && ! is_active_sidebar( 'left' );
    }

    //helper
    function czr_fn_is_right_sidebar_widget_placeholder_enabled() {
        return $this -> czr_fn_is_widget_placeholder_enabled( 'sidebar' ) && ! is_active_sidebar( 'right' );
    }

    //helper
    function czr_fn_is_horizontal_footer_widgets_placeholder_enabled() {
        return $this -> czr_fn_is_widget_placeholder_enabled( 'horizontal_footer' ) && !czr_fn_is_registered_or_possible( 'footer_horizontal_widgets' );
    }

    //helper
    function czr_fn_is_footer_widgets_placeholder_enabled() {
        return $this -> czr_fn_is_widget_placeholder_enabled( 'footer' ) && !czr_fn_is_registered_or_possible( 'footer_widgets' );
    }



    /**
    * Public helper, state if we can display a widget placeholder to the current user.
    */
    function czr_fn_is_widget_placeholder_enabled( $_position = null ) {
        //always display in DEV mode
        if ( defined('CZR_DEV') && true === CZR_DEV )
            return true;

        $_position = is_null($_position) ? apply_filters('tc_widget_areas_position', array( 'sidebar', 'footer', 'horizontal_footer') ) : array($_position);

        return apply_filters( "tc_display_widget_placeholders",
            czr_fn_is_front_help_enabled() && is_user_logged_in() && current_user_can('edit_theme_options')
            && array_sum( array_map( array( self::$instance , 'czr_fn_check_widget_placeholder_transient'), $_position ) )
        );
    }


    /**
    * @return  bool
    * @since Customizr 3.3+
    */
    function czr_fn_check_widget_placeholder_transient( $_position ) {
        return 'disabled' != get_transient("tc_widget_placehold_{$_position}");
    }




    /*****************************************************
    * FEATURED PAGES : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss notice ajax callback
    * hook : wp_ajax_dismiss_fp_notice
    */
    function czr_fn_dismiss_fp_notice() {
        check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
        set_transient( 'tc_fp_notice', 'disabled' , 60*60*24*365*20 );//20 years of peace
        wp_die();
    }

    /**
    * Disable home slider
    * hook : wp_ajax_remove_fp
    */
    function czr_fn_remove_fp() {
        check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
        czr_fn_set_option( 'tc_show_featured_pages' , 0 );
        wp_die();
    }


    /**
    * Do we display the featured page notice ?
    * @return  bool
    * @since Customizr 3.4+
    */
    function czr_fn_is_fp_notice_on() {
        //never display when customizing
        if ( czr_fn_is_customizing() )
            return;

        //always display in DEV mode
        if ( defined('CZR_DEV') && true === CZR_DEV )
            return true;

         //checks if at least one of the conditions is true
        return apply_filters(
          'tc_is_fp_notice_on',
            ! is_admin() && is_user_logged_in() && current_user_can('edit_theme_options')
            && ! czr_fn_is_pro()
            && ! czr_fn_is_plugin_active('tc-unlimited-featured-pages/tc_unlimited_featured_pages.php')
            && czr_fn_is_real_home() && false != (bool)czr_fn_opt('tc_show_featured_pages')
            && 'disabled' != get_transient("tc_fp_notice")
            && ! apply_filters( 'czr_is_one_fp_set', false )
        );
    }





    /*****************************************************
    * SIDENAV MENU HELP : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss sidenav help
    * hook : wp_ajax_dismiss_sidenav_help
    *
    * @package Customizr
    * @since Customizr 3.4+
    */
    function czr_fn_dismiss_sidenav_help() {
      check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
      set_transient( 'tc_sidenav_help', 'disabled' , 60*60*24*365*20 );//20 years of peace
      wp_die();
    }


    /**
    *
    * @return  bool
    * @since Customizr 3.3+
    */
    function czr_fn_is_sidenav_help_on() {
      //never display when customizing
      if ( czr_fn_is_customizing() )
        return;

      //always display in DEV mode
      if ( defined('CZR_DEV') && true === CZR_DEV )
        return true;

      $_dont_display_conditions = array(
        ! is_user_logged_in() || ! current_user_can('edit_theme_options'),
        czr_fn_has_location_menu('main'),// => if the "main" location has a menu assigned
        'navbar' == czr_fn_opt('tc_menu_style'),
        'disabled' == get_transient("tc_sidenav_help"),
        ! czr_fn_is_front_help_enabled()
      );

      //checks if at least one of the conditions is true
      return apply_filters(
        'tc_is_sidenav_help_on',
        ! (bool)array_sum($_dont_display_conditions)
      );
    }






    /*****************************************************
    * SECOND MENU PLACEHOLDER : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss notice ajax callback
    * hook : wp_ajax_dismiss_second_menu_notice
    *
    * @package Customizr
    * @since Customizr 3.3+
    */
    function czr_fn_dismiss_second_menu_notice() {
      check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
      set_transient( 'tc_second_menu_placehold', 'disabled' , 60*60*24*365*20 );//20 years of peace
      wp_die();
    }


    /**
    *
    * @return  bool
    * @since Customizr 3.3+
    */
    function czr_fn_is_second_menu_placeholder_on() {
      //never display when customizing
      if ( czr_fn_is_customizing() )
        return;

      //always display in DEV mode
      if ( defined('CZR_DEV') && true === CZR_DEV )
        return true;
      //don't display if main menu style is regular <=> 'navbar' == tc_menu_style
      if ( 'navbar' == czr_fn_opt('tc_menu_style') )
        return false;
      //don't display if second menu is enabled : tc_display_second_menu
      if ( (bool)czr_fn_opt('tc_display_second_menu') )
        return false;

      return apply_filters(
        "tc_is_second_menu_placeholder_on",
        czr_fn_is_front_help_enabled() && is_user_logged_in() && current_user_can('edit_theme_options') && 'disabled' != get_transient("tc_second_menu_placehold")
      );
    }



    /*****************************************************
    * MAIN MENU NOTICE : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss notice ajax callback
    * hook : wp_ajax_dismiss_main_menu_notice
    *
    * @package Customizr
    * @since Customizr 3.3+
    */
    function czr_fn_dismiss_main_menu_notice() {
      check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
      set_transient( 'tc_main_menu_notice', 'disabled' , 60*60*24*365*20 );//20 years of peace
      wp_die();
    }



    /**
    *
    * @return  bool
    * @since Customizr 3.3+
    */
    function czr_fn_is_main_menu_notice_on() {
      //never display when customizing
      if ( czr_fn_is_customizing() )
        return;

      //always display in DEV mode
      if ( defined('CZR_DEV') && true === CZR_DEV )
        return true;

      $_dont_display_conditions = array(
        ! is_user_logged_in() || ! current_user_can('edit_theme_options'),
        'navbar' != czr_fn_opt('tc_menu_style'),
        (bool)czr_fn_opt('tc_display_second_menu'),
        'disabled' == get_transient("tc_main_menu_notice"),
        ! czr_fn_is_front_help_enabled()
      );

      //checks if at least one of the conditions is true
      return apply_filters(
        'tc_is_main_menu_notice_on',
        ! (bool)array_sum($_dont_display_conditions)
      );
    }



    /*****************************************************
    * SLIDER : AJAX JS AND CALLBACK
    *****************************************************/
    /**
    * Dismiss notice ajax callback
    * hook : wp_ajax_dismiss_slider_notice
    */
    function czr_fn_dismiss_slider_notice() {
      check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
      set_transient( 'tc_slider_notice', 'disabled' , 60*60*24*365*20 );//20 years of peace
      wp_die();
    }

    /**
    * Disable home slider
    * hook : wp_ajax_remove_slider
    */
    function czr_fn_remove_slider() {
      check_ajax_referer( 'czr-helpblock-nonce', 'czrHelpBlockNonce' );
      czr_fn_set_option( 'tc_front_slider' , 0 );
      wp_die();
    }



    /**
    * Do we display the slider notice ?
    * @return  bool
    * @since Customizr 3.4+
    */
    function czr_fn_is_slider_notice_on( $_position = null ) {
      //never display when customizing
      if ( czr_fn_is_customizing() )
        return;

      //always display in DEV mode
      if ( defined('CZR_DEV') && true === CZR_DEV )
        return true;

      $_dont_display_conditions = array(
        ! is_user_logged_in() || ! current_user_can('edit_theme_options'),
        ! is_admin() && ! czr_fn_is_real_home(),
        'tc_posts_slider' != czr_fn_opt('tc_front_slider'),
        'disabled' == get_transient("tc_slider_notice"),
        ! czr_fn_is_front_help_enabled()
      );

      //checks if at least one of the conditions is true
      return apply_filters(
        'tc_is_slider_notice_on',
        ! (bool)array_sum($_dont_display_conditions)
      );
    }







    /**
     * The template for displaying the help block placeholders
     */
    function czr_fn_help_block_template( $params ) {
        $defaults = array(
            'dismiss_action'         => '',
            'remove_action'          => null,
            'nonce_handle'           => $this->nonce_handle,
            'nonce_id'               => 'czrHelpBlockNonce',
            'element_tag'            => 'div',
            'element_class'          => 'col-12',
            'position'               => '',
            'remove_selector'        => '',
            'help_title'             => '',
            'help_message'           => '',
            'help_secondary_message' => ''
        );

        $params = wp_parse_args( $params, $defaults );
        extract( $params );

        //create help_block_data

        $help_block_data_array = array_filter( array(
          'data-nonce_handle' => $nonce_handle,
          'data-nonce_id' => $nonce_id,
          'data-dismiss_action' => $dismiss_action,
          'data-position' => $position,
          'data-remove_action' => $remove_action,
          'data-remove_selector' => $remove_selector
        ) );

        $help_block_data = array();
        foreach ( $help_block_data_array as $key => $value ) {
            $help_block_data[] = $key . '="'. $value . '"';
        }
        ?>
        <<?php /* generally a div, can be aside when in a widget area*/
          echo $element_tag
        ?> class="tc-placeholder-wrap <?php echo $element_class ?>" <?php echo czr_fn_stringify_array( $help_block_data ) ?>>
            <span class="tc-admin-notice"><i class="fas fa-user-secret"></i> <?php _e( 'This notice is visible for admin users only.', 'customizr') ?></span>
                <div class="tc-placeholder-content">
                    <?php if ( $help_title ): ?>
                      <h4><?php echo $help_title ?></h4>
                    <?php endif //title ?>
                    <p><strong><?php /* Print the message: contains html */ echo $help_message ?> </strong></p>
                    <?php /* Print the secondary message: contains html */echo $help_secondary_message ?>
                </div>
            <a class="tc-dismiss-notice" href="#" title="<?php _e( 'dismiss notice', 'customizr' ) ?>"><?php _e( 'dismiss notice', 'customizr' ) ?> x</a>
        </<?php echo $element_tag ?>>
        <?php
    }

  }//end of class
endif;
