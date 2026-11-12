<?php
// Sidebar Template

$uri_path         = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$uri_segments     = explode( '/', $uri_path );
$curr_post_slug   = end( $uri_segments );

// Get current post’s categories
$category         = get_the_category();
$curr_cat_slug    = $category[0]->slug ?? '';
$curr_parent_slug = '';

if ( ! empty( $category ) ) {
    $parent_category_id = $category[0]->category_parent;
    if ( $parent_category_id ) {
        $parent_category    = get_category( $parent_category_id );
        $curr_parent_slug   = $parent_category->slug ?? '';
    }
}

$categories_args = [
    'taxonomy'   => 'category',
    'parent'     => 0,
    'number'     => 0,
    'exclude'    => 1,
    'hide_empty' => 0,
    'orderby'    => 'meta_value',
    'meta_key'   => 'order',
    'order'      => 'ASC',
];

$categories_posts = get_terms( $categories_args );

$p_categories = wp_get_post_categories( get_the_ID() );

function is_top_level_ancestor( $cat_id, $top_level_id ) {
    while ( $cat_id != 0 ) {
        $category = get_category( $cat_id );
        if ( ! $category ) break;
        if ( $category->term_id == $top_level_id ) {
            return true;
        }
        $cat_id = $category->parent;
    }
    return false;
}
?>

<div class="col-md-3">
    <?php if ( ! empty( $categories_posts ) ): ?>
        <?php foreach ( $categories_posts as $category ): ?>
            <?php if ( ! category_has_parent( $category->term_id ) ): ?>
                <?php
                // Check whether to open this section (supports all nesting levels)
                $post_categories = wp_get_post_categories( get_the_ID() );
                $is_open = false;
                foreach ( $post_categories as $cat_id ) {
                    if ( is_top_level_ancestor( $cat_id, $category->term_id ) ) {
                        $is_open = true;
                        break;
                    }
                }

                $bar_status  = $is_open ? 'show' : '';
                $aria_status = $is_open ? 'true' : 'false';
                ?>
                <button
                    class="sidebar-title-postHeading"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#multiCollapseExample<?php echo $category->term_id; ?>"
                    aria-expanded="<?php echo $aria_status; ?>"
                    aria-controls="multiCollapseExample<?php echo $category->term_id; ?>"
                >
                    <?php echo esc_html( $category->name ); ?>
                    <span class="<?php echo $aria_status ? 'sidebar-icon-open' : 'sidebar-icon-close'; ?>">
                        <i class="<?php echo $aria_status ? 'fa fa-angle-up' : 'fa fa-angle-down'; ?>"></i>
                    </span>
                </button>
                <div class="BorderBottom"></div>
                <div class="policySectionHeight scrollbar multi-collapse collapse <?php echo $bar_status; ?>" id="multiCollapseExample<?php echo $category->term_id; ?>">
                    <div class="sidebar-menu-post">
                        <div class="menu-home-page-menu-container">
                            <div class="panel-group" id="accordion">
                                <div class="panel-default">
                                    <?php
                                    // Include posts/subcategories
                                    display_categories( $category->term_id, $curr_post_slug );
                                    display_category_posts( $category->term_id, $curr_post_slug );
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    // Related Files Section
    $uri_path         = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $uri_segments     = explode( '/', $uri_path );
    $last_segment_val = $uri_segments[ array_key_last( $uri_segments ) - 1 ];
    $curr_post_slug   = $last_segment_val;

    if ( isset( $curr_post_slug ) ) {
        $slug         = $curr_post_slug;
        $post_details = get_post_id_by_name( wp_strip_all_tags( $slug ), 'post' );
        $post_id      = $post_details->ID ?? 0;
        $post_data    = get_post_meta( $post_id, 'document_revisions_details' );

        if ( isset( $post_data[0] ) ) {
            $doc_id     = $post_data[0];
            $attachments = get_document_revisions( $doc_id );

            if ( ! empty( $attachments ) ) {
                echo '<div class="archives-sidebar single-related-files scrollbar">';
                echo '<span class="sidebar-title-postHeading">Related Files</span><div class="BorderBottom"> </div><div class="policySectionHeight scrollbar"><div class="sidebar-menu-post1"><div class="menu-home-page-menu-container"><div class="panel-group" id="accordion"><div class="panel-default"><ul class="">';
                foreach ( $attachments as $key => $value ) {
                    if ( $value->post_status !== 'publish' && $value->post_excerpt != '' ) {
                        $file_url = ( 'attachment' === get_post_type( $value->ID ) ) ? wp_get_attachment_url( $value->ID ) : get_permalink( $value->ID );
                        echo "<li><a target='_blank' href='" . esc_url( $file_url ) . "' download>" . esc_html( $value->post_title ) . "</a>";
                        echo '<p class="ReviewBoardSecsubtext">' . strtoupper( get_the_date( get_option( 'date_format' ), $value->ID ) ) . '</p>';
                        echo "</li>";
                    }
                }
                echo '</ul></div></div></div></div></div></div>';
            }
        }
    }
    ?>
</div><!-- #secondary -->
