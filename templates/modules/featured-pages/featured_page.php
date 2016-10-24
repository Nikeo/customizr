<?php
/**
 * The template for displaying the single featured page
 */

if ( czr_fn_get( 'is_first_of_row' ) ) : ?>
<div class="row widget-area section custom" >
<?php endif ?>
    <div class="featured col-xs-12 col-md-<?php czr_fn_echo( 'fp_col' ) ?> fp-<?php czr_fn_echo( 'fp_id' ) ?>">
      <div class="widget-front grid-post" <?php czr_fn_echo('element_attributes') ?>>
      <?php if ( czr_fn_get( 'fp_img' ) ) : /* FP IMAGE */?>
        <div class="tc-thumbnail thumb-wrapper <?php czr_fn_echo( 'thumb_wrapper_class' ) ?>">
          <a class="featured-pages-link-bg" href="<?php czr_fn_echo( 'featured_page_link' ) ?>" title="<?php czr_fn_echo( 'featured_page_title' ) ?>"></a>
          <?php czr_fn_echo( 'fp_img' ) ?>
        </div>
      <?php endif /* END FP IMAGE*/ ?>
        <?php if ( czr_fn_get( 'edit_enabled' ) ): ?>
          <a class="post-edit-link btn-edit pull-xs-left" href="<?php echo get_edit_post_link( czr_fn_get( 'featured_page_id' ) ) ?>" title="<?php czr_fn_echo( 'featured_page_title' ) ?>" target="_blank"><i class="icn-edit"></i><?php _e( 'Edit', 'customizr' ) ?></a>
        <?php endif ?>
        <div class="tc-content">
          <?php /* FP TITLE */ ?>
            <h2><?php czr_fn_echo( 'featured_page_title' ) ?>

            </h2>
          <?php /* END FP TITLE */ ?>
          <?php /* FP TEXT */ ?>
            <p class="fp-text-<?php czr_fn_echo( 'fp_id' ) ?>"><?php czr_fn_echo( 'text' ) ?></p>
          <?php /* END FP TEXT*/ ?>
          <?php if ( czr_fn_get( 'fp_button_text' ) ): /* FP BUTTON TEXT */ ?>
            <a class="fp-button <?php czr_fn_echo( 'fp_button_class' ) ?>" href="<?php czr_fn_echo( 'featured_page_link' ) ?>" title="<?php czr_fn_echo( 'featured_page_title' ) ?>" ><?php czr_fn_echo( 'fp_button_text' ) ?></a>
          <?php endif;/* END FP BUTTON TEXT*/ ?>
        </div>
      </div><!--/.widget-front-->
    </div><!--/.fp-->
<?php if ( czr_fn_get( 'is_last_of_row' ) ) : ?>
  </div>
<?php endif;