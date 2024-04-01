<?php
/**
* Loads front end CSS
* Inline front end skin
*
*
* @package            Customizr
*/
if ( !class_exists( 'CZR_resources_styles' ) ) :
   #[\AllowDynamicProperties]
   class CZR_resources_styles {
        //Access any method or var of the class with classname::$instance->var or method():
        static $instance;

        private $_is_css_minified;
        private $_resources_version;

        function __construct () {
            self::$instance =& $this;
            //setup version param and is_css_minified bool
            add_action( 'after_setup_theme'                   , array( $this , 'czr_fn_setup_properties' ), 20 );
            add_action( 'wp_enqueue_scripts'                  , array( $this , 'czr_fn_enqueue_front_styles' ) );

            add_filter( 'czr_user_options_style'              , array( $this , 'czr_fn_maybe_write_skin_inline_css') );
            add_filter( 'czr_user_options_style'              , array( $this , 'czr_fn_maybe_write_header_custom_skin_inline_css') );

            add_filter( 'czr_user_options_style'              , array( $this , 'czr_fn_maybe_write_boxed_layout_inline_css' ) );

            add_filter( 'czr_user_options_style'              , array( $this , 'czr_fn_write_custom_css') , apply_filters( 'czr_custom_css_priority', 9999 ) );
        }


        //hook: after_setup_theme
        function czr_fn_setup_properties() {

              $this->_resouces_version             = CZR_DEBUG_MODE || CZR_DEV_MODE ? CUSTOMIZR_VER . time() : CUSTOMIZR_VER;

              $this->_is_css_minified              = CZR_DEBUG_MODE || CZR_DEV_MODE ? false : true ;
              $this->_is_css_minified              = esc_attr( czr_fn_opt( 'tc_minified_skin' ) ) ? $this->_is_css_minified : false;

        }



         /**
         * Registers and enqueues Customizr stylesheets
         * @package Customizr
         * @since Customizr 1.1
         */
         function czr_fn_enqueue_front_styles() {
              $_path       = CZR_ASSETS_PREFIX . 'front/css/';
              $_ver        = $this->_resouces_version;
              $_ext        = $this->_is_css_minified ? '.min.css' : '.css';

              // Even if using the full nimble template, we still need the Customizr stylesheet to style the widget zones.
              // if ( czr_fn_is_full_nimble_tmpl() )
              //   return;

              //wp_enqueue_style( 'customizr-flickity'       , czr_fn_get_theme_file_url( "{$_path}flickity{$_ext}" ), array(), $_ver, 'all' );
              //wp_enqueue_style( 'customizr-magnific'       , czr_fn_get_theme_file_url( "{$_path}magnific-popup{$_ext}" ), array(), $_ver, 'all' );
              //wp_enqueue_style( 'customizr-scrollbar'      , czr_fn_get_theme_file_url( "{$_path}jquery.mCustomScrollbar.min.css" ), array(), $_ver, 'all' );

              $main_theme_file_name  = is_rtl() ? 'rtl' : 'style';

              //Customizr main stylesheets
              wp_enqueue_style( 'customizr-main'         , czr_fn_get_theme_file_url( "{$_path}{$main_theme_file_name}{$_ext}"), array(), $_ver, 'all' );


              //Modular scale respond
              //Customizr main stylesheet
              if ( 1 == esc_attr( czr_fn_opt( 'tc_ms_respond_css' ) ) ) {
                  wp_enqueue_style( 'customizr-ms-respond'     , czr_fn_get_theme_file_url( "{$_path}style-modular-scale{$_ext}"), array(), $_ver, 'all' );
              }

              //Customizer user defined style options : the custom CSS is written with a high priority here
              wp_add_inline_style( 'customizr-main'      , apply_filters( 'czr_user_options_style' , '' ) );


              //enqueue placeholders style
              if ( apply_filters(  'czr_enqueue_placeholders_resources', false ) ) {
                  wp_enqueue_style( 'customizr-front-placholders', CZR_FRONT_ASSETS_URL . 'css/style-front-placeholders.css', array(), $_ver, 'all' );
              }


              //Customizr stylesheet (style.css)
              if ( czr_fn_is_child() ) {
                  wp_enqueue_style( 'customizr-style'          , czr_fn_get_theme_file_url( "style.css"), array(), $_ver, 'all' );
              }
         }

         /**
         * Writes the sanitized custom CSS from options array into the custom user stylesheet, at the very end (priority 9999)
         * hook : czr_user_options_style
         * @package Customizr
         * @since Customizr 2.0.7
         */
         function czr_fn_write_custom_css( $_css = null ) {

               $_css                     = isset( $_css ) ? $_css : '';
               $_moved_opts              = czr_fn_opt(  '__moved_opts' );

               /*
               * Do not print old custom css if moved in the WP Custom CSS
               */
               if ( !empty( $_moved_opts ) && is_array( $_moved_opts ) && in_array( 'custom_css', $_moved_opts) ) {
                     return $_css;
               }

               $tc_custom_css            = czr_fn_opt( 'tc_custom_css' );
               $esc_tc_custom_css        = esc_html( $tc_custom_css );

               if ( !isset( $esc_tc_custom_css ) || empty( $esc_tc_custom_css ) )
                     return $_css;

               return apply_filters( 'czr_write_custom_css',
                     $_css . "\n" . html_entity_decode( $esc_tc_custom_css ),
                     $_css,
                     $tc_custom_css
               );

         }//end of function




         /* See: https://github.com/presscustomizr/customizr/issues/605 */
         function czr_fn_apply_media_upload_front_patch( $_css ) {

               global $wp_version;
               if ( version_compare( '4.5', $wp_version, '<=' ) )
                     $_css = sprintf("%s%s", $_css, 'table { border-collapse: separate; } body table { border-collapse: collapse; }');

               return $_css;

         }


         //hook : czr_user_options_style
         function czr_fn_maybe_write_header_custom_skin_inline_css( $_css ) {
               //retrieve the current option
               $skin_color                             = czr_fn_get_header_skin();

               if ( 'custom' != $skin_color )
                     return $_css;

               //retrieve the current option
               $header_bg_color                        = czr_fn_opt( 'tc_header_custom_bg_color' );
               $header_fg_color                        = czr_fn_opt( 'tc_header_custom_fg_color' );

               $header_bg_inverted_color               = czr_fn_hex_invert( $header_bg_color );

               //shaded
               $header_bg_inverted_color_shade_highest = czr_fn_hex2rgba( $header_bg_inverted_color, 0.045, $array = false, $make_prop_value = true );

               $header_bg_color_shade_low              = czr_fn_hex2rgba( $header_bg_color, 0.9, $array = false, $make_prop_value = true );
               $header_bg_color_shade_lowest           = czr_fn_hex2rgba( $header_bg_color, 0.98, $array = false, $make_prop_value = true );

               $header_fg_color_shade_very_high        = czr_fn_hex2rgba( $header_fg_color, 0.09, $array = false, $make_prop_value = true );
               $header_fg_color_shade_highest          = czr_fn_hex2rgba( $header_fg_color, 0.075, $array = false, $make_prop_value = true );

               //in header _skins.scss this is the $secondary_color which is $grey-light or $grey-dark depending on whether
               //the header skin is light or dark, so it's constant and not depending on the primary color.
               //This color is used for the tagline color, the menu items hover color etc.
               //In the dynamic skin we want to refer everything to two colors: foreground and backgorund that's why we use a shdaded version
               //of the foreground color.
               //Also consider that $grey-dark, for our settings is the same as $grey, and the $secondary-color-light is always $grey-light
               //So since here we don't differentiate between $grey-light and $grey-dark, we'll use the following color for the $secondary-color-light too.
               //TODO: unify this in the header _skins.scss too?!
               $header_fg_color_shade_low              = czr_fn_hex2rgba( $header_fg_color, 0.7, $array = false, $make_prop_value = true );

               //LET'S DANCE
               //start computing style
               $header_skin                            = array();
               $glue                                   = $this->_is_css_minified || esc_attr( czr_fn_opt( 'tc_minified_skin' ) ) ? '' : "\n";

               $header_skin_style_map                  = array(

                     'header_fg_color' => array(
                           'color'  => $header_fg_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'color'  => array(
                                       '.tc-header',
                                       '#tc-sn .tc-sn-inner',
                                       '.czr-overlay',
                                       '.add-menu-button',
                                       '.tc-header .socials a',
                                       '.tc-header .socials a:focus',
                                       '.tc-header .socials a:active',
                                       '.nav__utils',
                                       '.nav__utils a',
                                       '.nav__utils a:focus',
                                       '.nav__utils a:active',
                                       '.header-contact__info a',
                                       '.header-contact__info a:focus',
                                       '.header-contact__info a:active',
                                       '.czr-overlay a:hover',
                                       '.dropdown-menu',
                                       '.tc-header .navbar-brand-sitename',
                                       '[class*=nav__menu] .nav__link',
                                       '[class*=nav__menu] .nav__link-wrapper .caret__dropdown-toggler',
                                       '[class*=nav__menu] .dropdown-menu .nav__link',
                                       '[class*=nav__menu] .dropdown-item .nav__link:hover',
                                       '.tc-header form.czr-form label',
                                       '.czr-overlay form.czr-form label',
                                       ".tc-header .czr-form input:not([type='submit']):not([type='button']):not([type='number']):not([type='checkbox']):not([type='radio'])",
                                       '.tc-header .czr-form textarea',
                                       '.tc-header .czr-form .form-control',
                                       ".czr-overlay .czr-form input:not([type='submit']):not([type='button']):not([type='number']):not([type='checkbox']):not([type='radio'])",
                                       '.czr-overlay .czr-form textarea',
                                       '.czr-overlay .czr-form .form-control',
                                       '.tc-header h1',
                                       '.tc-header h2',
                                       '.tc-header h3',
                                       '.tc-header h4',
                                       '.tc-header h5',
                                       '.tc-header h6',
                                 ),
                                 'border-color' => array(
                                       ".tc-header .czr-form input:not([type='submit']):not([type='button']):not([type='number']):not([type='checkbox']):not([type='radio'])",
                                       '.tc-header .czr-form textarea',
                                       '.tc-header .czr-form .form-control',
                                       ".czr-overlay .czr-form input:not([type='submit']):not([type='button']):not([type='number']):not([type='checkbox']):not([type='radio'])",
                                       '.czr-overlay .czr-form textarea',
                                       '.czr-overlay .czr-form .form-control'
                                 ),
                                 'background-color' => array(
                                       '.ham__toggler-span-wrapper .line',
                                       '[class*=nav__menu] .nav__title::before',
                                 )
                           )
                     ),
                     'header_fg_color_shade_low' => array(
                           'color'  => $header_fg_color_shade_low,
                           'rules'  => array(
                                 //prop => selectors
                                 'color' => array(
                                         '.header-tagline',
                                         '[class*=nav__menu] .nav__link:hover',
                                         '[class*=nav__menu] .nav__link-wrapper .caret__dropdown-toggler:hover',
                                         '[class*=nav__menu] .show:not(.dropdown-item) > .nav__link',
                                         '[class*=nav__menu] .show:not(.dropdown-item) > .nav__link-wrapper .nav__link',
                                         '.czr-highlight-contextual-menu-items [class*=nav__menu] li:not(.dropdown-item).current-active > .nav__link',
                                         '.czr-highlight-contextual-menu-items [class*=nav__menu] li:not(.dropdown-item).current-active > .nav__link-wrapper .nav__link',
                                         '.czr-highlight-contextual-menu-items [class*=nav__menu] .current-menu-item > .nav__link',
                                         '.czr-highlight-contextual-menu-items [class*=nav__menu] .current-menu-item > .nav__link-wrapper .nav__link',
                                         '[class*=nav__menu] .dropdown-item .nav__link',
                                         '.czr-overlay a',
                                         '.tc-header .socials a:hover',
                                         '.nav__utils a:hover',
                                         '.czr-highlight-contextual-menu-items .nav__utils a.current-active',
                                         '.header-contact__info a:hover',
                                         '.tc-header .czr-form .form-group.in-focus label',
                                         '.czr-overlay .czr-form .form-group.in-focus label'
                                 ),
                                 'background-color' => array(
                                         '.nav__utils .ham-toggler-menu.czr-collapsed:hover .line',
                                 )
                           )
                     ),
                     'header_fg_color_shade_very_high' => array(
                           'color'  => $header_fg_color_shade_very_high,
                           'rules'  => array(
                                 //prop => selectors
                                 'border-color' => array(
                                         '.topbar-navbar__wrapper',
                                         '.dropdown-item:not(:last-of-type)'
                                 ),
                                 'border-bottom-color' => array(
                                         '.tc-header',
                                 ),
                                 'outline-color' => array(
                                         '#tc-sn'
                                 )
                           )
                     ),
                     'header_fg_color_shade_highest' => array(
                           'color'  => $header_fg_color_shade_highest,
                           'rules'  => array(
                                 //prop => selectors
                                 'border-color' => array(
                                         '.mobile-nav__container',
                                         '.header-search__container',
                                         '.mobile-nav__nav',
                                         '.vertical-nav > li:not(:last-of-type)'
                                 ),
                           )
                     ),
                     'header_bg_color' => array(
                           'color'  => $header_bg_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.tc-header',
                                       '#tc-sn .tc-sn-inner',
                                       '.dropdown-menu',
                                       '.dropdown-item:active',
                                       '.dropdown-item:focus',
                                       '.dropdown-item:hover'
                                 )
                           )
                     ),
                     'header_bg_color_shade_low' => array(
                           'color'  => $header_bg_color_shade_low,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.sticky-transparent.is-sticky .mobile-sticky',  //the alpha param is actually set at 0.7 for the dark_skin in the header skin scss, here we set it always at 0.9 which is the value used for the light skin
                                       '.sticky-transparent.is-sticky .desktop-sticky', //the alpah param is actually set at 0.7 for the dark_skin in the header skin scss, here we set it always at 0.9 which is the value used for the light skin
                                       '.sticky-transparent.is-sticky .mobile-nav__nav',
                                       '.header-transparent:not(.is-sticky) .mobile-nav__nav',
                                       '.header-transparent:not(.is-sticky) .dropdown-menu'
                                 )
                           )
                     ),
                     'header_bg_color_shade_lowest' => array(
                           'color'  => $header_bg_color_shade_lowest,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.czr-overlay',
                                 )
                           )
                     ),
                     'header_bg_inverted_color_shade_highest' => array(
                           'color'  => $header_bg_inverted_color_shade_highest,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.dropdown-item:before',
                                       '.vertical-nav .caret__dropdown-toggler'
                                 )
                           )
                     ),
               );

               $header_skin  = self::czr_fn_build_inline_style_from_map( $header_skin_style_map, $glue );


               // end computing
               if ( empty ( $header_skin ) ) {
                     return $_css;
               }

               //LET's GET IT ON
               $header_skin  = implode( "{$glue}{$glue}", $header_skin );

               return $_css . $header_skin;
         }


         //@return string
         function czr_fn_maybe_write_skin_inline_css( $_css ) {

               //retrieve the current option
               $skin_color                     = czr_fn_opt( 'tc_skin_color' );

               //retrieve the default color
               $defaults                       = czr_fn_get_default_options();

               $def_skin_color                 = isset( $defaults['tc_skin_color'] ) ? $defaults['tc_skin_color'] : false;

               if ( in_array( $def_skin_color, array( $skin_color, strtoupper( $skin_color) ) ) )
                     return $_css;

               $skin_dark_color                = czr_fn_darken_hex( $skin_color, '12%' );
               $skin_light_color               = czr_fn_lighten_hex( $skin_color, '15%' );
               $skin_lightest_color            = czr_fn_lighten_hex( $skin_color, '20%' );

               //shaded
               $skin_lightest_color_shade_high = czr_fn_hex2rgba( $skin_lightest_color, 0.2, $array = false, $make_prop_value = true );
               $skin_dark_color_shade_high     = czr_fn_hex2rgba( $skin_dark_color, 0.2, $array = false, $make_prop_value = true );
               $skin_dark_color_shade_low      = czr_fn_hex2rgba( $skin_dark_color, 0.8, $array = false, $make_prop_value = true);

               //LET'S DANCE
               //start computing style
               $skin                           = array();
               $glue                           = $this->_is_css_minified || esc_attr( czr_fn_opt( 'tc_minified_skin' ) ) ? '' : "\n";

               $skin_style_map                 = array(

                     'skin_color' => array(
                           'color'  => $skin_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'color'  => array(
                                       'a',
                                       '.btn-skin:active',
                                       '.btn-skin:focus',
                                       '.btn-skin:hover',
                                       '.btn-skin.inverted',
                                       '.grid-container__classic .post-type__icon',
                                       '.post-type__icon:hover .icn-format',
                                       '.grid-container__classic .post-type__icon:hover .icn-format',
                                       "[class*='grid-container__'] .entry-title a.czr-title:hover",
                                       'input[type=checkbox]:checked::before',
                                 ),
                                 'border-color' => array(
                                       '.czr-css-loader > div ',
                                       '.btn-skin',
                                       '.btn-skin:active',
                                       '.btn-skin:focus',
                                       '.btn-skin:hover',
                                       '.btn-skin-h-dark',
                                       '.btn-skin-h-dark.inverted:active',
                                       '.btn-skin-h-dark.inverted:focus',
                                       '.btn-skin-h-dark.inverted:hover',

                                 ),
                                 'border-top-color' => array(
                                       '.tc-header.border-top'
                                  ),
                                 'background-color' => array(
                                       "[class*='grid-container__'] .entry-title a:hover::after",
                                       '.grid-container__classic .post-type__icon',
                                       '.btn-skin',
                                       '.btn-skin.inverted:active',
                                       '.btn-skin.inverted:focus',
                                       '.btn-skin.inverted:hover',
                                       '.btn-skin-h-dark',
                                       '.btn-skin-h-dark.inverted:active',
                                       '.btn-skin-h-dark.inverted:focus',
                                       '.btn-skin-h-dark.inverted:hover',
                                       '.sidebar .widget-title::after',
                                       'input[type=radio]:checked::before'
                                 )
                           )
                     ),

                     'skin_light_color' => array(
                           'color'  => $skin_light_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'color'  => array(
                                       '.btn-skin-light:active',
                                       '.btn-skin-light:focus',
                                       '.btn-skin-light:hover',
                                       '.btn-skin-light.inverted',
                                 ),

                                 'border-color' => array(
                                       "input:not([type='submit']):not([type='button']):not([type='number']):not([type='checkbox']):not([type='radio']):focus",
                                       'textarea:focus',
                                       '.btn-skin-light',
                                       '.btn-skin-light.inverted',
                                       '.btn-skin-light:active',
                                       '.btn-skin-light:focus',
                                       '.btn-skin-light:hover',
                                       '.btn-skin-light.inverted:active',
                                       '.btn-skin-light.inverted:focus',
                                       '.btn-skin-light.inverted:hover',
                                 ),

                                 'background-color' => array(
                                       '.btn-skin-light',
                                       '.btn-skin-light.inverted:active',
                                       '.btn-skin-light.inverted:focus',
                                       '.btn-skin-light.inverted:hover',
                                 ),
                           )
                     ),

                     'skin_lightest_color' => array(
                           'color'  => $skin_lightest_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'color'  => array(
                                       '.btn-skin-lightest:active',
                                       '.btn-skin-lightest:focus',
                                       '.btn-skin-lightest:hover',
                                       '.btn-skin-lightest.inverted',
                                 ),

                                 'border-color' => array(
                                       '.btn-skin-lightest',
                                       '.btn-skin-lightest.inverted',
                                       '.btn-skin-lightest:active',
                                       '.btn-skin-lightest:focus',
                                       '.btn-skin-lightest:hover',
                                       '.btn-skin-lightest.inverted:active',
                                       '.btn-skin-lightest.inverted:focus',
                                       '.btn-skin-lightest.inverted:hover',
                                 ),

                                 'background-color' => array(
                                       '.btn-skin-lightest',
                                       '.btn-skin-lightest.inverted:active',
                                       '.btn-skin-lightest.inverted:focus',
                                       '.btn-skin-lightest.inverted:hover',
                                 ),
                           )
                     ),

                     'skin_dark_color' => array(
                           'color'  => $skin_dark_color,
                           'rules'  => array(
                                 //prop => selectors
                                 'color'  => array(
                                       '.pagination',
                                       'a:hover',
                                       'a:focus',
                                       'a:active',
                                       '.btn-skin-dark:active',
                                       '.btn-skin-dark:focus',
                                       '.btn-skin-dark:hover',
                                       '.btn-skin-dark.inverted',
                                       '.btn-skin-dark-oh:active',
                                       '.btn-skin-dark-oh:focus',
                                       '.btn-skin-dark-oh:hover',
                                       '.post-info a:not(.btn):hover',
                                       '.grid-container__classic .post-type__icon .icn-format',
                                       "[class*='grid-container__'] .hover .entry-title a",
                                       '.widget-area a:not(.btn):hover',
                                       'a.czr-format-link:hover',
                                       '.format-link.hover a.czr-format-link',
                                       'button[type=submit]:hover',
                                       'button[type=submit]:active',
                                       'button[type=submit]:focus',
                                       'input[type=submit]:hover',
                                       'input[type=submit]:active',
                                       'input[type=submit]:focus',
                                       '.tabs .nav-link:hover',
                                       '.tabs .nav-link.active',
                                       '.tabs .nav-link.active:hover',
                                       '.tabs .nav-link.active:focus'
                                 ),

                                 'border-color' => array(
                                       '.grid-container__classic.tc-grid-border .grid__item',
                                       '.btn-skin-dark',
                                       '.btn-skin-dark.inverted',
                                       'button[type=submit]',
                                       'input[type=submit]',
                                       '.btn-skin-dark:active',
                                       '.btn-skin-dark:focus',
                                       '.btn-skin-dark:hover',
                                       '.btn-skin-dark.inverted:active',
                                       '.btn-skin-dark.inverted:focus',
                                       '.btn-skin-dark.inverted:hover',
                                       '.btn-skin-h-dark:active',
                                       '.btn-skin-h-dark:focus',
                                       '.btn-skin-h-dark:hover',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-dark-oh:active',
                                       '.btn-skin-dark-oh:focus',
                                       '.btn-skin-dark-oh:hover',
                                       '.btn-skin-dark-oh.inverted:active',
                                       '.btn-skin-dark-oh.inverted:focus',
                                       '.btn-skin-dark-oh.inverted:hover',
                                       'button[type=submit]:hover',
                                       'button[type=submit]:active',
                                       'button[type=submit]:focus',
                                       'input[type=submit]:hover',
                                       'input[type=submit]:active',
                                       'input[type=submit]:focus',

                                 ),

                                 'background-color' => array(
                                       '.btn-skin-dark',
                                       '.btn-skin-dark.inverted:active',
                                       '.btn-skin-dark.inverted:focus',
                                       '.btn-skin-dark.inverted:hover',
                                       '.btn-skin-h-dark:active',
                                       '.btn-skin-h-dark:focus',
                                       '.btn-skin-h-dark:hover',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-h-dark.inverted',
                                       '.btn-skin-dark-oh.inverted:active',
                                       '.btn-skin-dark-oh.inverted:focus',
                                       '.btn-skin-dark-oh.inverted:hover',
                                       '.grid-container__classic .post-type__icon:hover',
                                       'button[type=submit]',
                                       'input[type=submit]',
                                       '.czr-link-hover-underline .widgets-list-layout-links a:not(.btn)::before', //<-jetpack top post/pages textual links only
                                       '.czr-link-hover-underline .widget_archive a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_nav_menu a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_rss ul a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_recent_entries a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_categories a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_meta a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_recent_comments a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_pages a:not(.btn)::before',
                                       '.czr-link-hover-underline .widget_calendar a:not(.btn)::before',
                                       "[class*='grid-container__'] .hover .entry-title a::after",
                                       'a.czr-format-link::before',
                                       '.comment-author a::before',
                                       '.comment-link::before',
                                       '.tabs .nav-link.active::before'
                                 ),
                           )
                     ),

                     'skin_dark_color_shade_high' => array(
                           'color'  => $skin_dark_color_shade_high,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.btn-skin-dark-shaded:active',
                                       '.btn-skin-dark-shaded:focus',
                                       '.btn-skin-dark-shaded:hover',
                                       '.btn-skin-dark-shaded.inverted',
                                 )
                           )
                     ),

                     'skin_dark_color_shade_low' => array(
                           'color'  => $skin_dark_color_shade_low,
                           'rules'  => array(
                                 //prop => selectors
                                 'background-color' => array(
                                       '.btn-skin-dark-shaded',
                                       '.btn-skin-dark-shaded.inverted:active',
                                       '.btn-skin-dark-shaded.inverted:focus',
                                       '.btn-skin-dark-shaded.inverted:hover',
                                 )
                           )
                     )

               );

               //these break the style, let's separate them from the others;
               $skin[]  = '::-moz-selection{background-color:'. $skin_color .'}';
               $skin[]  = '::selection{background-color:'. $skin_color .'}';

               $skin    = array_merge( $skin, self::czr_fn_build_inline_style_from_map( $skin_style_map, $glue ) );

               // end computing
               if ( empty ( $skin ) ) {
                     return $_css;
               }

               //LET's GET IT ON
               $skin    = implode( "{$glue}{$glue}", $skin );

               return $_css . $skin;
         }


         //hook : czr_user_options_style
         function czr_fn_maybe_write_boxed_layout_inline_css( $_css ) {
               if ( 'boxed' != esc_attr( czr_fn_opt( 'tc_site_layout') ) ) {
                     return $_css;
               }
               /**
                * When we choose a boxed layout we increase the .container right and left paddings from 15px to 30px
                * though their inner .row still have a negative right and left margin of 15px
                * and the col-X inside will still have the left and right padding of 15px.
                * hence to ensure the actual contained elements always have the same width ( e.g. 1110px in desktop)
                * whether or not we're in a boxed layout, we have to increase the .container widths (for the viewports within it's defined)
                * of 30px.
                */


               //get default container widths
               /*
               CZR_init::$instance->$css_container_width looks like:

               array(
                   //min-widths: 1200px, 992px, 768px,
                   //xl, lg, md, sm, xs
                   '1140', '960', '720', '540' //, no xs => 100%

                   'xl' => '1140',
                   'lg' => '960',
                   'md' => '720',
                   'sm' => '540'
               )
               */
               $css_container_widths   = CZR_init::$instance->css_container_widths;

               /*
               CZR_init::$instance->$css_mq_breakpoints looks like:

               array(
                     'xl' => '1200',
                     'lg' => '992',
                     'md' => '768',
                     'sm' => '575'
               )
               */
               $css_mq_breakpoints          = CZR_init::$instance->css_mq_breakpoints;
               $css_container               = array();
               $container_selector          = '.czr-boxed-layout .container';
               $glue                        = $this->_is_css_minified || esc_attr( czr_fn_opt( 'tc_minified_skin' ) ) ? '' : "\n";
               $additional_width            = 30; //px

               //Add some rules from sm up
               //add padding
               $css_container[]        = sprintf( '@media (min-width: %1$spx){ %2$s{ padding-right: %3$spx; padding-left:  %3$spx; } }',
                           $css_mq_breakpoints[ 'sm' ],
                           $container_selector,
                           $additional_width
               );

               //define container widths
               foreach ( array_reverse( $css_mq_breakpoints, true ) as $mq => $mq_w_width ) {

                     $container_width  = $css_container_widths[ $mq ] + $additional_width;

                     $css_container[]  = sprintf( '@media (min-width: %1$spx){ %2$s{ width: %3$spx } }',
                                 $mq_w_width,
                                 $container_selector,
                                 $container_width
                     );
               }


               //LET's GET IT ON
               $css_container           = implode( "{$glue}{$glue}", $css_container );

               return $_css . $css_container;

         }




         //@return string
         public static function czr_fn_build_inline_style_from_map( $style_map = array(), $glue = '') {

               $style = array();

               if ( empty( $style_map ) )
                     return $style;

               //Builder
               foreach ( $style_map as $color => $params ) {

                     foreach ( $params['rules'] as $prop => $selectors ) {

                           $_selectors = implode( ",{$glue}", apply_filters( "czr_dynamic_{$color}_{$prop}_prop_selectors", $selectors ) );

                           if ( $_selectors ) {
                                 $style[] = sprintf( '%1$s{%2$s:%3$s}',
                                       $_selectors,
                                       $prop, //property
                                       $params['color'] //color
                                 );
                           }//end if $_selectors

                     }//end foreach $param['rules'] as ...

               }//end foreach $style_map as ...

               return $style;
         }


   }//end of CZR_resources_styles
endif;
