<?php
if( ! function_exists( 'better_comments' ) ):
function better_comments($comment, $args, $depth) {
    ?>
   <li <?php //comment_class(); ?> id="li-comment-<?php comment_ID() ?>">
    <div class="comment">
        <div class="img-thumbnail d-none d-sm-block">
            <?php echo get_avatar(get_current_user_id(),$size='32'); ?>
        </div>
        <strong><?php echo get_comment_author() ?></strong> 
        <div class="comment-meta commentmetadata " ><?php printf(/* translators: 1: date and time(s). */ esc_html__('%1$s at %2$s' , '5balloons_theme'), get_comment_date(),  get_comment_time()) ?></div>
        <?php if ($comment->comment_approved == '0') : ?>
            <em><?php esc_html_e('Your comment is awaiting moderation.','5balloons_theme') ?></em>
            <a class="p3-comment-moderation" href="#" data-comment_id="<?php comment_ID() ?>">(Approve)</a>
        <?php endif; ?>
        <?php comment_text() ?>
        <!-- <span class="float-right">
            <span> <a href="#"><i class="fa fa-reply"></i> <?php //comment_reply_link(array_merge( $args, array('depth' => $depth, 'max_depth' => $args['max_depth']))) ?></a></span>
        </span> -->
        

<?php
        }
endif;