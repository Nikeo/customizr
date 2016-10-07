<?php
class CZR_cl_post_list_alternate_model_class extends CZR_cl_Model {
  public $article_selectors;
  public $sections_wrapper_class;

  public $has_format_icon_media;
  public $has_post_media;
  public $has_narrow_layout;
  public $is_full_image;

  public $place_1;
  public $place_2;

  public $def_place_1;
  public $def_place_2;

  public $media_col;
  public $content_col;

  public $is_loop_start;
  public $is_loop_end;

  //Default post list layout
  private static $default_post_list_layout   = array (
                                // array( xl, lg, md, sm, xs )
            'content'           => array( '', '', '8', '', '12'),
            'media'             => array( '', '', '4', '', '12'),
            'narrow_both'       => array( '', '', '12', '', '12'),
            'show_thumb_first'  => false,
            'alternate'         => true,
          );
  private static $post_class    = array( 'row' );
  private static $_col_bp = array(
      'xl', 'lg', 'md', 'sm', 'xs'
    );
  public $post_list_layout;


  /**
  * @override
  * fired before the model properties are parsed
  *
  * return model params array()
  */
  function czr_fn_extend_params( $model = array() ) {
    $global_sidebar_layout                 = czr_fn_get_layout( czr_fn_get_id() , 'sidebar' );

    $model[ 'element_class']          = czr_fn_get_in_content_width_class();
    $model[ 'has_narrow_layout' ]     = 'b' == $global_sidebar_layout;
    $model[ 'post_list_layout' ]      = $this -> czr_fn_get_the_post_list_layout( $model[ 'has_narrow_layout' ] );
    $model[ 'has_format_icon_media' ] = ! $model[ 'has_narrow_layout' ];

    /*
    * In the new theme the places are defined just by the option show_thumb_first,
    * we handle the alternate with bootstrap classes
    */
    $model[ 'def_place_1' ]           = 'show_thumb_first' == $model[ 'post_list_layout' ]['show_thumb_first'] ? 'media' : 'content';
    $model[ 'def_place_2' ]           = 'show_thumb_first' == $model[ 'post_list_layout' ]['show_thumb_first'] ? 'content' : 'media';

    return $model;
  }


  function czr_fn_setup_children() {

    $children = array (
      /* THUMBS */
   /*   array(
        'id'          => 'post_list_standard_thumb',
        'model_class' => 'content/post-lists/thumbnail'
      ),
      //the recangular thumb has a different model + a slighty different template
      array(
        'id'          => 'post_list_rectangular_thumb',
        'model_class' => array( 'parent' => 'content/post-lists/thumbnail', 'name' => 'content/post-lists/thumbnail_rectangular')
      ),
      //Post/page headings
      array(
        'id' => 'post_page_headings',
        'model_class' => 'content/singles/post_page_headings'
      ),

    */);

    return $children;
  }


  function czr_fn_setup_late_properties() {
    global $wp_query;
    /* Define variables */
    $_layout                 = apply_filters( 'czr_post_list_layout', $this -> post_list_layout );
    $maybe_center_sections   = apply_filters( 'czr_alternate_sections_centering', true );

    $has_post_media          = $this -> czr_fn_show_media() ;

    $post_class              = ! $has_post_media ? array_merge( self::$post_class, array('no-thumb') ) : self::$post_class;

    /*
    * Using the excerpt filter here can cause some compatibility issues
    * See: Super Socializer plugin
    */
    $_has_excerpt            = (bool) apply_filters( 'the_excerpt', get_the_excerpt() );

    $_current_post_format    = get_post_format();

    /* gallery and image (with no text) post formats */
    $is_full_image           = in_array( $_current_post_format , array( 'gallery', 'image' ) ) && ( 'image' != $_current_post_format ||
            ( 'image' == $_current_post_format && ! $_has_excerpt  ) );

    $_sections_wrapper_class = array();

    /* Structural */
    $place_1                 = $this -> def_place_1;
    $place_2                 = $this -> def_place_2;

    $_push                   = array(
      $place_1 => array(),
      $place_2 => array()
    );

    $_pull                   = array(
      $place_1 => array(),
      $place_2 => array()
    );
    /* End define variables */

    /* Process different cases */
    /*
    * $is_full_image: Gallery and images (with no text) should
    * - not be vertically centered
    * - avoid the media-content alternation
    */
    if ( ! $is_full_image ) {
      if ( $has_post_media ) {
        /*
        * Video post formats
        * In the new alternate layout video takes more space when global layout has less than 2 sidebars
        * same thing for the image post format with text
        *
        */
        if ( in_array( $_current_post_format , apply_filters( 'czr_alternate_big_media_post_formats', array( 'video', 'image' ) ) )
            && ! $this->has_narrow_layout ) {
          /* Swap the layouts */
          $_t_l                    = $_layout[ 'media' ];
          $_layout[ 'media' ]      = $_layout[ 'content' ];
          $_layout[ 'content' ]    = $_t_l;
        }
      }

      // conditions to swap place_1 with place_2 are:
      // alternate on and current post number is odd (1,3,..). (First post is 0 )
      if (  $_layout[ 'alternate' ] &&  ( 0 == ( $wp_query -> current_post + 1 ) % 2 ) ) {
        /* the slice is to avoid push/pull in xs */
        $_push[ $place_1 ]        = array_slice( $_layout[ $place_2 ], 0, count($_layout[ $place_2 ]) - 1);
        $_pull[ $place_2 ]        = array_slice( $_layout[ $place_1 ], 0, count($_layout[ $place_1 ]) - 1);
      }

      if ( ! $this->has_narrow_layout )
        //allow centering sections
        array_push( $_sections_wrapper_class, apply_filters( 'czr_alternate_sections_centering', true ) ? 'czr-center-sections' : 'a');
    }
    else {
      /*
      * $is_full_image: Gallery and images (with no text) should
      * - be displayed in full-width
      * - media comes first, content will overlap
      */
      $_layout[ 'content' ] = $_layout[ 'media' ] = array();

      $place_1 = 'media';
      $place_2 = 'content';
    }

    /* Extend article selectors with info about the presence of an excerpt and/or thumb */
    array_push( $post_class, ! $_has_excerpt ? 'no-text' : '',  ! $has_post_media ? 'no-thumb' : '' );
    $article_selectors    = czr_fn_get_the_post_list_article_selectors( array_filter($post_class) );

    $this -> czr_fn_update( array(
      'media_col'          => array_filter( $this -> czr_fn_build_cols( $_layout[ 'media' ], $_push['media'], $_pull['media'] ) ),
      'content_col'        => array_filter( $this -> czr_fn_build_cols( $_layout[ 'content'], $_push['content'], $_pull['content'] ) ),
      'has_post_media'         => $has_post_media,
      'article_selectors'      => $article_selectors,
      'is_loop_start'          => 0 == $wp_query -> current_post,
      'is_loop_end'            => $wp_query -> current_post == $wp_query -> post_count -1,
      'sections_wrapper_class' => $_sections_wrapper_class,
      'is_full_image'          => $is_full_image,
      'place_1'                => $place_1,
      'place_2'                => $place_2
    ) );

  }


  /**
  * @return array() of bootstrap classed defining the responsive widths
  *
  */
  function czr_fn_build_cols( $_widths, $_push = array(), $_pull = array() ) {
    $_col_bp = self::$_col_bp;

    $_widths = array_filter( $_widths );
    $_push   = array_filter( $_push );
    $_pull   = array_filter( $_pull );

    $_cols   = array();

    $_push_class = $_pull_class = '';

    foreach ( $_widths as $i => $val ) {

      if ( isset($_push[$i]) )
        $_push_class    = "push-{$_col_bp[$i]}-{$_push[$i]}";

      if ( isset($_pull[$i]) )
        $_pull_class    = "pull-{$_col_bp[$i]}-{$_pull[$i]}";

      $_width_class  = "col-{$_col_bp[$i]}-$val";
      array_push( $_cols, $_width_class, $_push_class, $_pull_class );
    }
    return array_filter( array_unique( $_cols ) );
  }


  /**
  * @return array() of layout data
  * @package Customizr
  * @since Customizr 3.2.0
  */
  function czr_fn_get_the_post_list_layout( $narrow_layout = false ) {

    $_layout                       = self::$default_post_list_layout;

    $_layout[ 'position' ]         = esc_attr( czr_fn_get_opt( 'tc_post_list_thumb_position' ) );
    $_layout[ 'show_thumb_first' ] = in_array( $_layout['position'] , array( 'top', 'left') );

    //since 4.5 top/bottom positions will not be optional but will be forced in narrow layouts
    if ( $narrow_layout )
      $_layout['position']       = $_layout[ 'show_thumb_first' ] ? 'top' : 'bottom';
    else {
      if ( 'top' == $_layout[ 'position' ] )
        $_layout[ 'position' ] = 'left';
      elseif ( 'bottom' == $_layout[ 'position' ] )
        $_layout[ 'position' ] = 'right';
    }

    //since 3.4.16 the alternate layout is not available when the position is top or bottom
    $_layout['alternate']        = ! ( 0 == esc_attr( czr_fn_get_opt( 'tc_post_list_thumb_alternate' ) ) || in_array( $_layout['position'] , array( 'top', 'bottom') ) );

    if ( in_array( $_layout['position'] , array( 'top', 'bottom') ) )
      $_layout['content'] = $_layout['media'] = self::$default_post_list_layout['narrow_both'];

    return $_layout;
  }


  /**
  * hook : body_class
  * @return  array of classes
  *
  * @package Customizr
  * @since Customizr 3.3.2
  */
  function czr_fn_body_class( $_class ) {
    array_push( $_class , 'czr-post-list-context');
    return $_class;
  }


  /* Following are here to allow to apply a filter on each loop ..
  *  but we can think about move them in another place if we decide
  *  the users MUST act only modifying models/templates
  *
  *  Actually they can be moved in another place anyway, but they are pretty specific of the "alternate" post list
  */
  /* HELPERS */
  /**
  * @return boolean
  * @package Customizr
  * @since Customizr 3.3.2
  */
  private function czr_fn_show_media() {
    //when do we display the thumbnail ?
    //1) there must be a thumbnail
    //2) the excerpt option is not set to full
    //3) user settings in customizer
    //4) filter's conditions
    return apply_filters( 'czr_show_media',
          ! in_array( get_post_format() , apply_filters( 'czr_post_formats_with_no_media', array( 'quote', 'link', 'status', 'aside' ) ) ) &&
          czr_fn_has_thumb() &&
          0 != esc_attr( czr_fn_get_opt( 'tc_post_list_show_thumb' ) )
    );
  }

}