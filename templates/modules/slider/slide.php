<?php
/**
 * The template for displaying a single slide item
 *
 * @package WordPress
 * @subpackage Customizr
 * @since Customizr 3.5.0
 */
?>
<div class="item <?php tc_echo( 'element_class' ) ?>" <?php tc_echo('element_attributes') ?>>

  <div class="<?php tc_echo( 'img_wrapper_class' ) ?>">
  <?php if ( tc_get( 'link_whole_slide' ) ) : ?>
    <a class="tc-slide-link" href="<?php tc_echo( 'link_url' ) ?>" target="<?php tc_echo( 'link_target' ) ?>" title=<?php _e( 'Go to', 'customizr' ) ?>>
  <?php endif ?>
    <?php
        do_action('__before_all_slides_background__');
          tc_echo( 'slide_background' );
        do_action('__after_all_slides_background__');
    ?>
  <?php if ( tc_get( 'link_whole_slide' ) ) : ?>
    </a>
  <?php endif; ?>
  </div> <!-- .carousel-image -->

  <?php

  if ( tc_get( 'has_caption' ) ) :

  do_action('__before_all_slides_caption__');

  ?>
  <div class="<?php tc_echo( 'caption_class' ) ?>">
    <?php if ( tc_get( 'title' ) ): ?>
    <!-- TITLE -->
      <<?php tc_echo( 'title_tag' ) ?> class ="<?php tc_echo( 'title_class' ) ?>" <?php tc_echo( 'color_style' ) ?>><?php tc_echo( 'title' ) ?></<?php tc_echo( 'title_tag' ) ?>>
    <?php endif; ?>
    <?php if ( tc_get( 'text' ) ) : ?>
    <!-- TEXT -->
      <p class ="<?php tc_echo( 'text_class' ) ?>" <?php tc_echo( 'color_style' ) ?>><?php tc_echo( 'text' ) ?></p>
    <?php endif; ?>
    <!-- BUTTON -->
    <?php if ( tc_get( 'button_text' ) ): ?>
      <a class="<?php tc_echo( 'button_class' ) ?>" href="<?php tc_echo( 'button_link' ) ?>" target="<?php tc_echo( 'link_target' ) ?>"><?php tc_echo( 'button_text' ) ?></a>
    <?php endif; ?>
  </div>
  <?php

  do_action('__after_all_slides_caption__');
  if ( tc_has( 'slide_edit_button' ) )
    tc_render_template( 'modules/edit_button', 'slide_edit_button' );
  /* endif caption*/
  endif;

  ?>
</div><! -- /.item -->