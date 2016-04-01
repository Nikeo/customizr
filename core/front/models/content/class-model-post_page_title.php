<?php
class TC_post_page_title_model_class extends TC_Model {
  public $context;

  function tc_setup_children() {
    $children = array(
      //comment bubble
      array(
        'hook'      => '__after_inner_post_page_title__',
        'template'  => 'modules/comment_bubble'
      ),
      //edit post links
      array(
        'hook'      => '__after_inner_post_page_title__',
        'template'  => 'modules/edit_button',
        'priority'  => 20
      ),
      //recently updated
      array(
        'hook'      => '__after_inner_post_page_title__',
        'template'  => 'modules/recently_updated',
        'priority'  => 30
      )
    );
    return $children;
  }


  /*
  * @override
  */
  function tc_maybe_render_this_model_view() {
    return $this -> visibility && tc_post_has_title();
  }

  /**
  * @override
  * fired before the model properties are parsed
  *
  * return model params array()
  */
  function tc_extend_params( $model = array() ) {
    $this -> context            = $this -> tc_get_the_post_page_context();
    $model['element_class']     = apply_filters( 'tc_content_title_icon', $this -> tc_get_post_page_title_class( 'entry-title' ) );
    return $model;
  }

  function tc_get_the_post_page_context() {
    if ( ! is_singular() )
      return 'post_list';
    if ( is_page() )
      return 'page';

    return 'post';
  }

  function tc_get_post_page_title_class( $class ) {
    $new_class = ( esc_attr( TC_utils::$inst->tc_opt( "tc_show_{$this -> context}_title_icon" ) )
          && esc_attr( TC_utils::$inst->tc_opt( 'tc_show_title_icon' ) ) ) ? array( 'format-icon' ) : array();
    array_push( $new_class, $class );
    return $new_class;
  }
}