<?php
/**
* Loads front end JS
*
*
* @package            Customizr
*/
if ( !class_exists( 'CZR_resources_scripts' ) ) :
    #[\AllowDynamicProperties]
   class CZR_resources_scripts {
         //Access any method or var of the class with classname::$instance->var or method():
         static $instance;

         private $_resources_version;
         private $_minify_js;
         public $tc_script_map;

         function __construct () {
              self::$instance =& $this;

              $this->_resouces_version        = CZR_DEBUG_MODE || CZR_DEV_MODE || CZR_REFRESH_ASSETS ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER;
              $this->_minify_js               = CZR_DEBUG_MODE || CZR_DEV_MODE ? false : true ;


              add_action( 'wp_enqueue_scripts'                    , array( $this , 'czr_fn_enqueue_front_scripts' ) );
              add_action( 'czr_ajax_dismiss_welcome_note_front'   , array( $this , 'czr_fn_dismiss_welcome_note_front' ) );

              //stores the front scripts map in a property
              $this->tc_script_map = $this->czr_fn_get_script_map();

              // Adds `async` and `defer` support for scripts registered or enqueued
              // NOT USED IN DEV MODE
              // and for which we've added an attribute with wp_script_add_data( $_hand, 'async', true );
              // inspired from Twentytwenty WP theme
              // @see https://core.trac.wordpress.org/ticket/12009
              // commented after first implementation because of a suspition of regression with Customizr Pro masonry grid.
              add_filter( 'script_loader_tag', array( $this, 'czr_fn_filter_script_loader_tag' ), 10, 2 );
         }


         /**
         * Helper to get all front end script
         * Fired from the constructor
         *
         * @package Customizr
         * @since Customizr 3.3+
         */
         private function czr_fn_get_script_map( $_handles = array() ) {
              $_front_path  =  CZR_ASSETS_PREFIX . 'front/js/';
              $_libs_path =  CZR_ASSETS_PREFIX . 'front/js/libs/';

              $_map = array(
                     'tc-js-params' => array(
                          'path' => $_front_path,
                          'files' => array( 'tc-js-params.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //adds support for map method in array prototype for old ie browsers <ie9
                     'tc-js-arraymap-proto' => array(
                          'path' => $_libs_path,
                          'files' => array( 'oldBrowserCompat.js', 'oldBrowserCompat.min.js' ),
                          'dependencies' => array()
                     ),
                     'tc-smoothscroll' => array(
                        'path' => $_libs_path,
                        'files' => array( 'smoothscroll.js' ),
                        'dependencies' => array( 'underscore' )
                     ),
                     /**
                     ** libs
                     **/
                     //bootstrap
                     'tc-bootstrap' => array(
                          'path' => $_libs_path,
                          'files' => array( 'custom-bootstrap-modern.js' , 'custom-bootstrap-modern.min.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //magnific popup
                     'tc-mfp' => array(
                          'path' => $_libs_path,
                          'files' => array( 'jquery-magnific-popup.min.js' ),
                          'dependencies' => array( 'jquery' ),
                          'in_footer' => true,
                     ),
                     //flickity
                     'tc-flickity' => array(
                          'path' => $_libs_path,
                          'files' => array( 'flickity-pkgd.min.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //waypoints
                     'tc-waypoints' => array(
                          'path' => $_libs_path,
                          'files' => array( 'waypoints.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //vivus
                     'tc-vivus' => array(
                          'path' => $_libs_path,
                          'files' => array( 'vivus.min.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //holder
                     'tc-holder' => array(
                          'path' => $_libs_path,
                          'files' => array( 'holder.min.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     //mcustom scrollbar
                     'tc-mcs' => array(
                          'path' => $_libs_path,
                          'files' => array( 'jquery-mCustomScrollbar.min.js' ),
                          'dependencies' => array( 'jquery' ),
                     ),
                     /*
                     * OWNED JQUERY PLUGINS
                     */
                     //original sizes
                     'tc-img-original-sizes' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryimgOriginalSizes.js' ),
                          'dependencies' => array('jquery')
                     ),
                     'tc-dropcap' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryaddDropCap.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'underscore' )
                     ),
                     'tc-img-smartload' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryimgSmartLoad.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'underscore' )
                     ),
                     'tc-ext-links' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryextLinks.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'underscore' )
                     ),
                     'tc-parallax' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryParallax.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'underscore' )
                     ),
                     // FEB 2020 => NOT ENQUEUED ANYMORE for performance considerations
                     // 'tc-animate-svg' => array(
                     //      'path' => $_libs_path . 'jquery-plugins/',
                     //      'files' => array( 'jqueryAnimateSvg.js' ),
                     //      'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'tc-bootstrap', 'underscore' )
                     // ),
                     'tc-center-images' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryCenterImages.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'tc-img-original-sizes', 'tc-bootstrap', 'underscore' )
                     ),

                     //fittext
                     'tc-fittext' => array(
                          'path' => $_libs_path . 'jquery-plugins/',
                          'files' => array( 'jqueryFittext.js' ),
                          'dependencies' => array( 'jquery' )
                     ),
                     /*
                     * OUR PARTS
                     */
                     'tc-outline' => array(
                          'path' => $_libs_path,
                          'files' => array( 'outline.js' ),
                          'dependencies' => array()
                     ),
                     'tc-main-front' => array(
                          'path' => $_front_path,
                          'files' => array( 'main-ccat.js' ),
                          'dependencies' => array( 'tc-js-arraymap-proto', 'jquery' , 'tc-js-params', 'tc-outline', 'tc-img-original-sizes', 'tc-bootstrap', 'tc-fittext', 'underscore' )
                     ),
                     //concats all scripts
                     'tc-scripts' => array(
                          'path' => $_front_path,
                          'files' => array( 'tc-scripts.js' , 'tc-scripts.min.js' ),
                          'dependencies' => array('underscore', 'jquery' )
                     )
               );//end of scripts map

               return apply_filters('tc_get_script_map' , $_map, $_handles );
         }




         /**
         * Loads Customizr front scripts
         * Dependencies are defined in the script map property
         *
         * @return  void()
         * @uses wp_enqueue_script() to manage script dependencies
         * @package Customizr
         * @since Customizr 1.0
         */

         function czr_fn_enqueue_front_scripts() {
              if ( czr_fn_is_full_nimble_tmpl() )
                return;

               //wp scripts
               if ( is_singular() && get_option( 'thread_comments' ) )
                     wp_enqueue_script( 'comment-reply' );


               wp_enqueue_script( 'jquery' );
               wp_enqueue_script( 'jquery-ui-core' );

               $main_script_injected_on_dom_ready = czr_fn_is_checked( 'tc_defer_front_script' );

               if ( $main_script_injected_on_dom_ready ) {
                   // March 2020 for https://github.com/presscustomizr/customizr/issues/1812
                   wp_enqueue_script(
                       'czr-init',
                       sprintf( '%1$s%2$s', CZR_BASE_URL . CZR_ASSETS_PREFIX . 'front/js/', ( CZR_DEBUG_MODE || CZR_DEV_MODE ) ? 'tc-init.js' : 'tc-init.min.js' ),
                       array( 'underscore' ),
                       $this->_resouces_version,
                       false
                   );
                }

               wp_enqueue_script(
                   'modernizr',
                   CZR_BASE_URL . CZR_ASSETS_PREFIX . 'front/js/libs/modernizr.min.js',
                   array(),
                   $this->_resouces_version,
                   false
               );

               if ( !$main_script_injected_on_dom_ready ) {
                   // load concatenated js script when not in CZR_DEBUG_MODE or CZR_DEV
                   if ( $this->czr_fn_load_concatenated_front_scripts() ) {
                         // if ( $this->czr_fn_is_lightbox_required() ) {
                         //       $this->czr_fn_enqueue_script( 'tc-mfp' );
                         // }
                         //!!tc-scripts includes underscore, tc-js-arraymap-proto
                         $this->czr_fn_enqueue_script( 'tc-scripts' );
                         wp_script_add_data( 'tc-scripts', 'defer', true );
                   }
                   else {

                      wp_enqueue_script( 'underscore' );

                      //!!mind the dependencies
                      $this->czr_fn_enqueue_script( array(
                         'tc-js-params',
                         'tc-js-arraymap-proto',
                         //libs
                         'tc-bootstrap',
                         'tc-smoothscroll',//fired if  $('body').hasClass( 'czr-infinite-scroll-on' ) || ( CZRParams.SmoothScroll && CZRParams.SmoothScroll.Enabled && !czrapp.base.matchMedia( 1024 )
                         'tc-outline',
                         'tc-waypoints',
                         //'tc-mcs',//mCustomScrollBar will be loaded on demand
                         //'tc-flickity',//flickity will be loaded when needed
                         'tc-vivus',
                         'tc-raf',
                      ) );

                      // if ( $this->czr_fn_is_lightbox_required() )
                      //       $this->czr_fn_enqueue_script( 'tc-mfp' );

                      //plugins and main front
                      $this->czr_fn_enqueue_script( array(
                         'tc-dropcap' ,
                         'tc-img-smartload',
                         'tc-img-original-sizes',
                         'tc-ext-links',
                         'tc-center-images',
                         'tc-parallax',
                         //'tc-animate-svg', // FEB 2020 => NOT ENQUEUED ANYMORE for performance considerations
                         'tc-fittext',

                         'tc-main-front',
                      ) );

                  }//end load concatenated
              }



               //has the post comments ? adds a boolean parameter in js
               global $wp_query;
               $has_post_comments   = ( 0 != $wp_query->post_count && comments_open() && get_comments_number() != 0 ) ? true : false;

               if ( false != esc_attr( czr_fn_opt( 'tc_link_scroll') ) )
                     wp_enqueue_script('jquery-effects-core');


               $anchor_smooth_scroll_exclude =  apply_filters( 'czr_anchor_smoothscroll_excl' , array(
                   'simple' => array( '[class*=edd]' , '.carousel-control', '[data-toggle="modal"]', '[data-toggle="dropdown"]', '[data-toggle="czr-dropdown"]', '[data-toggle="tooltip"]', '[data-toggle="popover"]', '[data-toggle="collapse"]', '[data-toggle="czr-collapse"]', '[data-toggle="tab"]', '[data-toggle="pill"]', '[data-toggle="czr-pill"]', '[class*=upme]', '[class*=um-]' ),
                   'deep'   => array(
                     'classes' => array(),
                     'ids'     => array()
                   )

               ));

               $smooth_scroll_enabled = apply_filters('tc_enable_smoothscroll', czr_fn_is_checked( 'tc_smoothscroll') );
               $smooth_scroll_options = apply_filters('tc_smoothscroll_options', array( 'touchpadSupport' => false ) );

               //smart load
               $smart_load_enabled    = esc_attr( czr_fn_opt( 'tc_img_smart_load' ) );
               $smart_load_opts       = apply_filters( 'tc_img_smart_load_options' , array(
                       'parentSelectors' => array(
                            '[class*=grid-container], .article-container',
                            '.__before_main_wrapper',
                            '.widget-front',
                            '.post-related-articles',
                            '.tc-singular-thumbnail-wrapper',
                            '.sek-module-inner' //<= Nimble Builder selector, when using {{the_content}} in NB template, Customizr will parse images for lazy load
                       ),
                       'opts'     => array(
                            'excludeImg' => array( '.tc-holder-img' )
                       )

               ));

              //Welcome note preprocess
              $is_welcome_note_on = false;
              $welcome_note_content = '';
              if ( !czr_fn_is_pro() && czr_fn_user_started_with_current_version() ) {
                  $is_welcome_note_on = apply_filters(
                      'czr_is_welcome_front_notification_on',
                      false
                      //czr_fn_user_can_see_customize_notices_on_front() && !czr_fn_is_customizing() && !czr_fn_isprevdem() && 'dismissed' != get_transient( 'czr_welcome_note_status' )
                  );
                  if ( $is_welcome_note_on ) {
                      $welcome_note_content =  $this->czr_fn_get_welcome_note_content();
                  }
              }

              $dependant_script_for_localize = $this->czr_fn_load_concatenated_front_scripts() ? 'tc-scripts' : 'tc-js-params';
              if ( $main_script_injected_on_dom_ready ) {
                  $dependant_script_for_localize = 'czr-init';
              }

              wp_localize_script(
                  $dependant_script_for_localize,
                  'CZRParams',
                  apply_filters( 'tc_customizr_script_params' , array(

                      'assetsPath'      => czr_fn_get_theme_file_url( CZR_ASSETS_PREFIX . 'front/' ),
                      'mainScriptUrl' => sprintf( '%1$s%2$s%3$s', CZR_BASE_URL . CZR_ASSETS_PREFIX . 'front/js/', ( CZR_DEBUG_MODE || CZR_DEV_MODE ) ? 'tc-scripts.js?' : 'tc-scripts.min.js?', CUSTOMIZR_VER ),
                      'deferFontAwesome' => czr_fn_is_checked( 'tc_defer_font_awesome' ),
                      'fontAwesomeUrl' => CZR_BASE_URL . CZR_ASSETS_PREFIX . 'shared/fonts/fa/css/fontawesome-all.min.css?' . CUSTOMIZR_VER,

                      '_disabled'          => apply_filters( 'czr_disabled_front_js_parts', array() ),
                      'centerSliderImg'   => esc_attr( czr_fn_opt( 'tc_center_slider_img') ),

                      'isLightBoxEnabled'  => $this->czr_fn_is_lightbox_required(),

                      'SmoothScroll'      => array(
                          'Enabled' => $smooth_scroll_enabled,
                          'Options' => $smooth_scroll_options,
                      ),

                      'isAnchorScrollEnabled'     => czr_fn_is_checked( 'tc_link_scroll' ), // also adds the jquery effect library if smooth scroll is enabled => easeOutExpo effect
                      'anchorSmoothScrollExclude' => $anchor_smooth_scroll_exclude,

                      'timerOnScrollAllBrowsers' => apply_filters( 'tc_timer_on_scroll_for_all_browser' , true), //<= if false, for ie only

                      'centerAllImg'          => 1,
                      'HasComments'        => $has_post_comments,

                      'LoadModernizr'      => apply_filters( 'tc_load_modernizr' , true ),

                      'stickyHeader'          => czr_fn_opt( 'tc_sticky_header' ),

                      'extLinksStyle'       => esc_attr( czr_fn_opt( 'tc_ext_link_style' ) ),
                      'extLinksTargetExt'   => esc_attr( czr_fn_opt( 'tc_ext_link_target' ) ),
                      'extLinksSkipSelectors'   => apply_filters( 'tc_ext_links_skip_selectors' , array( 'classes' => array('btn', 'button') , 'ids' => array() ) ),
                      'dropcapEnabled'      => esc_attr( czr_fn_opt( 'tc_enable_dropcap' ) ),
                      'dropcapWhere'      => array( 'post' => esc_attr( czr_fn_opt( 'tc_post_dropcap' ) ) , 'page' => esc_attr( czr_fn_opt( 'tc_page_dropcap' ) ) ),
                      'dropcapMinWords'     => esc_attr( czr_fn_opt( 'tc_dropcap_minwords' ) ),
                      'dropcapSkipSelectors'  => apply_filters( 'tc_dropcap_skip_selectors' , array( 'tags' => array('IMG' , 'IFRAME', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'UL', 'OL'), 'classes' => array('btn') , 'id' => array() ) ),

                      'imgSmartLoadEnabled' => $smart_load_enabled,
                      'imgSmartLoadOpts'    => $smart_load_opts,
                      'imgSmartLoadsForSliders' => czr_fn_is_checked( 'tc_slider_img_smart_load' ),

                      'pluginCompats'       => apply_filters( 'tc_js_params_plugin_compat', array() ),
                      'isWPMobile' => wp_is_mobile(),

                      'menuStickyUserSettings' => array(
                          'desktop' => czr_fn_opt( 'tc_header_desktop_sticky' ),
                          'mobile'  => czr_fn_opt( 'tc_header_mobile_sticky' )
                      ),

                      //AJAX
                      'adminAjaxUrl'        => admin_url( 'admin-ajax.php' ),
                      'ajaxUrl'             => add_query_arg(
                            array( 'czrajax' => true ), //to scope our ajax calls
                            set_url_scheme( home_url( '/' ) )
                      ),
                      'frontNonce'   => array( 'id' => 'CZRFrontNonce', 'handle' => wp_create_nonce( 'czr-front-nonce' ) ),

                      'isDevMode'        => CZR_DEBUG_MODE || CZR_DEV_MODE,
                      'isModernStyle'    => CZR_IS_MODERN_STYLE,

                      'i18n' => apply_filters( 'czr_front_js_translated_strings',
                          array(
                              'Permanently dismiss' => __('Permanently dismiss', 'customizr')
                          )
                      ),

                      //FRONT NOTIFICATIONS
                      //ordered by priority
                      'frontNotifications' => array(
                            'welcome' => array(
                                'enabled' => $is_welcome_note_on,
                                'content' => $welcome_note_content,
                                'dismissAction' => 'dismiss_welcome_note_front'
                            )
                      ),
                      // March 2020 : gfonts can be preloaded since https://github.com/presscustomizr/customizr/issues/1816
                      'preloadGfonts' => czr_fn_is_checked( 'tc_preload_gfonts' ),
                      'googleFonts' => CZR_resources_fonts::czr_fn_get_gfont_candidates(),
                      'version' => ( CZR_DEBUG_MODE || CZR_DEV_MODE ) ? time() : CUSTOMIZR_VER

                  ), czr_fn_get_id() )//end of filter

              );


              //holder.js is loaded when featured pages are enabled AND FP are set to show images and at least one holder should be displayed.
              //@TODO: enqueue holder.js only if needed
              if ( apply_filters( 'tc_holder_js_required', false ) || czr_fn_is_customizing() ) {
                  wp_enqueue_script(
                     'holder-js',
                     CZR_FRONT_ASSETS_URL . 'js/libs/holder.min.js',
                     array(),
                     $this-> _resouces_version,
                     $in_footer = true
                  );
              }

              //load retina.js in footer if enabled
              if ( apply_filters('tc_load_retinajs', 1 == czr_fn_opt( 'tc_retina_support' ) ) ) {
                  wp_enqueue_script( 'retinajs', CZR_FRONT_ASSETS_URL . 'js/libs/retina.min.js', array(), $this-> _resouces_version, $in_footer = true);
              }


              //enqueue placeholders style
              if ( apply_filters(  'czr_enqueue_placeholders_resources', false ) ) {
                  //no need to minify this
                  wp_enqueue_script( 'customizr-front-placeholders', CZR_FRONT_ASSETS_URL . 'js/libs/customizr-placeholders.js', array(), $this-> _resouces_version, $in_footer = true );
              }
         }


         /**
         * Convenient method to normalize script enqueueing in the Customizr theme
         * @return  void
         * @uses wp_enqueue_script() to manage script dependencies
         * @package Customizr
         * @since Customizr 3.3+
         */
         function czr_fn_enqueue_script( $_handles = array() ) {
               if ( empty($_handles) )
                 return;

               $_map = $this->tc_script_map;
               //Picks the requested handles from map
               if ( 'string' == gettype($_handles) && isset($_map[$_handles]) ) {
                     $_scripts = array( $_handles => $_map[$_handles] );
               }
               else {

                     $_scripts = array();
                     foreach ( $_handles as $_hand ) {

                           if ( !isset( $_map[$_hand]) )
                              continue;

                           $_scripts[$_hand] = $_map[$_hand];
                     }
               }

               //Enqueue the scripts with normalizes args
               foreach ( $_scripts as $_hand => $_params ) {
                  call_user_func_array( 'wp_enqueue_script',  $this->czr_fn_normalize_script_args( $_hand, $_params ) );
                  //wp_script_add_data( $_hand, 'async', true );
                }

         }//end of fn


         /**
         * Fired @'script_loader_tag'
         * Adds async/defer attributes to enqueued / registered scripts.
         * based on a solution found in Twentytwenty
         * NOT USED IN DEV MODE
         * and for which we've added an attribute with wp_script_add_data( $_hand, 'async', true );
         * If #12009 lands in WordPress, this function can no-op since it would be handled in core.
         *
         * @param string $tag    The script tag.
         * @param string $handle The script handle.
         * @return string Script HTML string.
         */
          public function czr_fn_filter_script_loader_tag( $tag, $handle ) {
            // load concatenated js script when not in CZR_DEBUG_MODE or CZR_DEV
            // if ( !$this->czr_fn_load_concatenated_front_scripts() )
            //   return $tag;

            foreach ( [ 'async', 'defer' ] as $attr ) {
              if ( !wp_scripts()->get_data( $handle, $attr ) ) {
                continue;
              }
              // Prevent adding attribute when already added in #12009.
              if ( !preg_match( ":\s$attr(=|>|\s):", $tag ) ) {
                $tag = preg_replace( ':(?=></script>):', " $attr", $tag, 1 );
              }
              // Only allow async or defer, not both.
              break;
            }
            return $tag;
          }



         /**
         * Helper to normalize the arguments passed to wp_enqueue_script()
         * Also handles the minified version of the file
         *
         * @return array of arguments for wp_enqueue_script
         * @package Customizr
         * @since Customizr 3.3+
         */
         private function czr_fn_normalize_script_args( $_handle, $_params ) {
               //Do we load the minified version if available ?
               if ( count( $_params['files'] ) > 1 ) {
                    $_filename = !$this->_minify_js ? $_params['files'][0] : $_params['files'][1];
                } else {
                    $_filename = $_params['files'][0];
                }

               //default is false
               $_params[ 'in_footer' ] = isset( $_params[ 'in_footer' ] ) ? $_params[ 'in_footer' ] : false;

               return array(
                     $_handle,
                     sprintf( '%1$s%2$s%3$s', CZR_BASE_URL , $_params['path'], $_filename ),
                     $_params['dependencies'],
                     $this-> _resouces_version,
                     apply_filters( "tc_load_{$_handle}_in_footer", $_params['in_footer'] )
               );
         }


         /**
         * Helper to check if we need fancybox or not on front
         *
         * @return boolean
         * @package Customizr
         * @since v3.3+
         */
         private function czr_fn_is_lightbox_required() {
               return czr_fn_opt( 'tc_fancybox' ) || czr_fn_opt( 'tc_gallery_fancybox');
         }

         /**
         * Helper
         * 'CZR_DEBUG_MODE' = isset( $_GET['czr_debug'] ) && 1 == $_GET['czr_debug']
         *
         * @return boolean
         * @package Customizr
         * @since v3.3+
         */
         function czr_fn_load_concatenated_front_scripts() {
              if ( defined( 'CZR_LOAD_CONCATENATED_SCRIPTS' ) && true === CZR_LOAD_CONCATENATED_SCRIPTS )
                return true;
              if ( defined( 'CZR_DEBUG_MODE' ) && true === CZR_DEBUG_MODE )
                return false;
              return apply_filters( 'tc_load_concatenated_front_scripts' , !defined('CZR_DEV')  || ( defined('CZR_DEV') && false == CZR_DEV ) );
         }


        /* ------------------------------------------------------------------------- *
         *  WELCOME NOTE
        /* ------------------------------------------------------------------------- */
        //This function is invoked only when :
        //1) !czr_fn_is_pro() && czr_fn_user_started_with_current_version()
        //2) AND if the welcome note can be displayed : czr_fn_user_can_see_customize_notices_on_front() && !czr_fn_is_customizing() && !czr_fn_isprevdem() && 'dismissed' != get_transient( 'czr_welcome_note_status' )
        //It returns a welcome note html string that will be localized in the front js
        //@return html string
        function czr_fn_get_welcome_note_content() {
            // beautify notice text using some defaults the_content filter callbacks
            // => turns emoticon :D into an svg
            foreach ( array( 'wptexturize', 'convert_smilies', 'wpautop') as $callback ) {
              if ( function_exists( $callback ) )
                  add_filter( 'czr_front_welcome_note_html', $callback );
            }
            ob_start();
              ?>
              <h2><?php printf( '%1$s :D' , __('Welcome in the Customizr theme', 'customizr' ) ); ?></h2>
                <?php
                    printf('<p>%1$s <a href="%2$s" target="_blank">%3$s</a> %4$s</p>',
                        __('The theme offers a wide range', 'customizr'),
                         admin_url( 'customize.php'),
                        __('of customization options', 'customizr'),
                        __('to let you create the best possible websites.', 'customizr' )
                    );
                    printf('<p>%1$s : <a href="%2$s" title="%3$s" target="_blank">%3$s <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>&nbsp;,<a href="%4$s" title="%5$s" target="_blank">%5$s <i class="fas fa-external-link-alt" aria-hidden="true"></i></a></p>',
                        __("If you need inspiration, you can visit our online demos", 'customizr'),
                        esc_url('https://wp-themes.com/customizr/'),
                        __('Customizr Demo 1', 'customizr'),
                        esc_url('demo.presscustomizr.com/'),
                        __('Customizr Demo 2', 'customizr')
                    );
                    printf( '<br/><br/><p>%1$s <a href="%2$s" target="_blank">%3$s <i class="fas fa-external-link-alt" aria-hidden="true"></i></a></p>',
                        __('To help you getting started with Customizr, we have published', 'customizr'),
                        esc_url('http://docs.presscustomizr.com/article/175-first-steps-with-the-customizr-wordpress-theme'),
                        __('a short guide here.', 'customizr')
                    );
                ?>
              <?php
            $html = ob_get_contents();
            if ($html) ob_end_clean();
            return apply_filters('czr_front_welcome_note_html', $html );
        }

        //hook : czr_ajax_dismiss_welcome_note_front
        function czr_fn_dismiss_welcome_note_front() {
            set_transient( 'czr_welcome_note_status', 'dismissed' , 60*60*24*365*20 );//20 years of peace
            wp_send_json_success( array( 'status_note' => 'dismissed' ) );
        }

   }//end of CZR_resources_js
endif;
