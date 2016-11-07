<?php
class CZR_post_list_heading_model_class extends CZR_Model {
  public $pre_title;
  public $title;
  public $context;

  function czr_fn_extend_params( $model = array() ) {
    //the controlleer will check if we're in (not singular) context
    //context
    $this -> context  = $this -> czr_fn_get_the_posts_list_context();
    if ( ! $this -> context )
      return;
    $model['pre_title']         = apply_filters( "czr_{$this -> context}_archive_title" , $this -> czr_fn_get_posts_list_pre_title() );
    $model['title']             = apply_filters( "czr_{$this -> context}_title", $this -> czr_fn_get_posts_list_title_content() );
    /*we are getting rid of
    "tc_{context}_header_content" filter
    */
    return $model;
  }
  function czr_fn_get_the_posts_list_context() {
    global $wp_query;
    if ( $wp_query -> is_posts_page && ! is_front_page() )
      return 'page_for_posts';
    if ( is_archive() ) {
      if ( is_author() )
        return 'author';
      if ( is_category() )
        return 'category';
      if ( is_day() )
        return 'day';
      if ( is_month() )
        return 'month';
      if ( is_year() )
        return 'year';
      if ( is_tag() )
        return 'tag';
      if ( apply_filters('czr_show_tax_archive_title', true ) )
        return 'tax';
    }
    return false;
  }

  function czr_fn_get_posts_list_pre_title( $context = null ) {
    $context = $context ? $context : $this -> context;
    $context = 'category' == $context ? 'cat' : $context;
    if ( in_array( $context, array( 'day', 'month', 'year') ) ) {
      switch ( $context ) {
        case 'day'            : return __( 'Daily Archives:' , 'customizr' );
        case 'month'          : return __( 'Monthly Archives:', 'customizr' );
        case 'year'           : return __( 'Yearly Archives:', 'customizr' );
      }
    }
    return esc_attr( czr_fn_get_opt( "tc_{$context}_title" ) );
  }

  function czr_fn_get_posts_list_title_content( $context = null ) {
    $context = $context ? $context : $this -> context;
    switch ( $context ) {
      case 'page_for_posts' : return get_the_title( get_option('page_for_posts') );
      case 'author'         : return '<span class="vcard">' . get_the_author_meta( 'display_name' , get_query_var( 'author' ) ) . '</span>';
      case 'category'       : return single_cat_title( '', false );
      case 'day'            : return '<span>' . get_the_date() . '</span>';
      case 'month'          : return '<span>' . get_the_date( _x( 'F Y' , 'monthly archives date format' , 'customizr' ) ) . '</span>';
      case 'year'           : return '<span>' . get_the_date( _x( 'Y' , 'yearly archives date format' , 'customizr' ) ) . '</span>';
      case 'tag'            : return single_tag_title( '', false );
      case 'tax'            : return get_the_archive_title();
    }
  }
}