<?php
class TC_post_list_wrapper_model_class extends TC_article_model_class {
  public $place_1 ;
  public $place_2 ;

  //Default post list layout
  private static $default_post_list_layout   = array(
            'content'           => 'span9',
            'thumb'             => 'span3',
            'show_thumb_first'  => false,
            'alternate'         => true
          );

  private $post_list_layout;

  function __construct( $model = array() ) {
    //Fires the parent constructor
    parent::__construct( $model );
    
    //set the post list layout based on the user's options
    $this -> post_list_layout  = $this -> tc_set_post_list_layout();
    //inside the loop but before rendering set some properties
    add_action( $model['hook'], array( $this, 'set_layout_hooks' ), 0 );
  } 


  function set_layout_hooks() {
    global $wp_query;
    
    extract( apply_filters( 'tc_post_list_layout', $this -> post_list_layout ) );


    $has_post_thumbnail   = false;
    $this -> place_1      = 'content';
    $this -> place_2      = 'thumb';

    if ( has_post_thumbnail() ) {
       // conditions to show the thumb first are:
       // a) alternate on
      //   a.1) position is left/top ( show_thumb_first true == 1 ) and current post number is odd (1,3,..)
      //       current_post starts by 0, hence current_post + show_thumb_first = 1..2..3.. -> so mod % 2 == 1, 0, 1 ...
      //    or
      //   a.2) position is right/bottom ( show_thumb_first false == 0 ) and current post number is even (2,4,...)
      //       current_post starts by 0, hence current_post + show_thumb_first = 0..1..2.. -> so mod % 2 == 0, 1, 0...
      //  b) alternate off & position is left/top ( show_thumb_first == true == 1)
      if (  $alternate && ( ( $wp_query -> current_post + (int) $show_thumb_first ) % 2 ) || 
            $show_thumb_first && ! $alternate ) {
        $this -> place_1 = 'thumb';
        $this -> place_2 = 'content';
      }
      $has_post_thumbnail = true;
    }

    set_query_var( 'tc_has_post_thumbnail', $has_post_thumbnail );
    set_query_var( 'tc_content_width'     , $content );
    set_query_var( 'tc_thumbnail_width'   , $thumb );
  }

  /**
  * @return array() of layout data
  * @package Customizr
  * @since Customizr 3.2.0
  */
  function tc_set_post_list_layout() {
    $_layout                     = self::$default_post_list_layout;  
    $_position                   = esc_attr( TC_utils::$inst->tc_opt( 'tc_post_list_thumb_position' ) );
    //since 3.4.16 the alternate layout is not available when the position is top or bottom
    $_layout['alternate']        = ( 0 == esc_attr( TC_utils::$inst->tc_opt( 'tc_post_list_thumb_alternate' ) ) 
                                   || in_array( $_position, array( 'top', 'bottom') ) ) ? false : true;
    $_layout['show_thumb_first'] = ( 'left' == $_position || 'top' == $_position ) ? true : false;
    $_layout['content']          = ( 'left' == $_position || 'right' == $_position ) ? $_layout['content'] : 'span12';
    $_layout['thumb']            = ( 'top' == $_position || 'bottom' == $_position ) ? 'span12' : $_layout['thumb'];
    return $_layout;
  }

  /**
  * hook : body_class
  * @return  array of classes
  *
  * @package Customizr
  * @since Customizr 3.3.2
  */
  function tc_body_class( $_class ) {
    array_push( $_class , 'tc-post-list-context');
    return $_class;
  }
}
