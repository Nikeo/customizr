<?php
class TC_comment_bubble_model_class extends TC_Model {
  public $type;

  /* DO WE WANT TO SPLIT THIS IN TWO? USING TWO DIFFERENT TEMPLATES TOO???
  *  Maybe we can do this later when we'll have the "routers" so we can register just one of the comment bubbles type based on the user options


  //when in the post list loop
  //render this?
  /*
  * @override
  * This is actually a merge of the tc_is_bubble_enabled and tc_are_comments_enabled
  */
  function tc_maybe_render_this_model_view () {
    $_bool = $this -> visibility;

    if ( ! $_bool )
      return;

    global $post;

    if ( in_the_loop() && isset( $post ) ) {

      $_bool =  ! post_password_required() && 0 != get_comments_number() &&
         in_array( get_post_type(), apply_filters('tc_show_comment_bubbles_for_post_types' , array( 'post' , 'page') ) );

      $_bool = ( 'closed' != $post -> comment_status ) ? true && $_bool : $_bool;

     //3) check global user options for pages and posts
      if ( 'page' == get_post_type() )
        $_bool = 1 == esc_attr( TC_utils::$inst->tc_opt( 'tc_page_comments' )) && $_bool;
      else
        $_bool = 1 == esc_attr( TC_utils::$inst->tc_opt( 'tc_post_comments' )) && $_bool;
    }else
      $_bool = false;

    return $_bool;
  }


  /**
  * @override
  * fired before the model properties are parsed
  *
  * return model params array()
  */
  function tc_extend_params( $model = array() ) {
    $model[ 'type' ]            =  esc_attr( TC_utils::$inst->tc_opt( 'tc_comment_bubble_shape' ) );
    return $model;
  }



  /*
  * @param link (stirng url) the link
  * @param add_anchor (bool) whether or not add an anchor to the link, default true
  */
  function tc_get_comment_bubble_link( $link, $add_anchor = true ) {
    $link = sprintf( "%s%s",
        is_singular() ? '' : esc_url( $link ),
        $add_anchor ? apply_filters( 'tc_bubble_comment_anchor', '#tc-comment-title') : ''
    );
    return $link;
  }



  /*
  * Callback of tc_user_options_style hook
  * @return css string
  *
  * @package Customizr
  * @since Customizr 3.3.2
  */
  function tc_user_options_style_cb( $_css ) {
    //fire once;
    static $_fired = false;

    if ( $_fired )
      return $_css;

    $_fired        = true;

    //apply custom color only if type custom
    //if color type is skin => bubble color is defined in the skin stylesheet
    if ( 'skin' != esc_attr( TC_utils::$inst->tc_opt( 'tc_comment_bubble_color_type' ) ) ) {
      $_custom_bubble_color = esc_attr( TC_utils::$inst->tc_opt( 'tc_comment_bubble_color' ) );
      $_comment_bubble_before_border_color = 'default' == $this -> type ?
            $_custom_bubble_color :
            "$_custom_bubble_color transparent";

      $_css .= "
          .comments-link .tc-comment-bubble {
            color: {$_custom_bubble_color};
            border: 2px solid {$_custom_bubble_color};
          }
          .comments-link .tc-comment-bubble:before {
            border-color: {$_comment_bubble_before_border_color}
          }
        ";
    }
    if ( 'default' == $this -> type )
      return $_css;
    $_css .= "
        .comments-link .custom-bubble-one {
          position: relative;
          bottom: 28px;
          right: 10px;
          padding: 4px;
          margin: 1em 0 3em;
          background: none;
          -webkit-border-radius: 10px;
          -moz-border-radius: 10px;
          border-radius: 10px;
          font-size: 10px;
        }
        .comments-link .custom-bubble-one:before {
          content: '';
          position: absolute;
          bottom: -14px;
          left: 10px;
          border-width: 14px 8px 0;
          border-style: solid;
          display: block;
          width: 0;
        }
        .comments-link .custom-bubble-one:after {
          content: '';
          position: absolute;
          bottom: -11px;
          left: 11px;
          border-width: 13px 7px 0;
          border-style: solid;
          border-color: #FAFAFA rgba(0, 0, 0, 0);
          display: block;
          width: 0;
        }\n";
    return $_css;
  }//end of fn
}