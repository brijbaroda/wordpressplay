<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package BizSphere
 	Template Name: Web Servers Page Layout
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<div id="content" class="site-content" role="main">

			<?php
                
                
                $args = array( 'posts_per_page' => 5, 'offset'=> 0, 'category' => 10 );
                
                $myposts = get_posts( $args );
                foreach ( $myposts as $post ) : setup_postdata( $post );
            ?>
                    

				<?php get_template_part( 'content', 'single' ); ?>


		<?php
                 endforeach; 
                //wp_reset_postdata();
            ?>

		</div><!-- #content -->
	</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
