<?php
/**
 * The template for displaying the content in a post list element
 *
 * @package WordPress
 * @subpackage Customizr
 * @since Customizr 3.5.0
 */
?>
<section class="tc_content <?php tc_echo('element_class') ?>" <?php tc_echo('element_attributes') ?> >
  <?php do_action( 'before_post_list_entry_content' ) ?>
  <section class="entry-content <?php tc_echo( 'content_class' ) ?>">
  <?php
    tc_echo( 'post_list_content', array(
       __( 'Continue reading <span class="meta-nav">&rarr;</span>' , 'customizr' )
   ) );
    wp_link_pages( array(
          'before'  => '<div class="pagination pagination-centered">' . __( 'Pages:' , 'customizr' ),
          'after'   => '</div>',
          'echo'    => 1
    ) );
  ?>
  </section>
  <?php do_action( 'after_post_list_entry_content' ) ?>
</section>