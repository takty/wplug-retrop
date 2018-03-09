<?php
/**
 *
 * The Template for Publications Static Pages
 *
 * Template Name: Publications
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-06
 *
 */


get_header();
?>
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
<?php
while ( have_posts() ) : the_post();
	get_template_part( 'template-parts/content', 'page' );
	\st\Bimeson::get_instance()->the_pub_list_section();
endwhile;
?>
		</main>
	</div>
<?php
get_footer();
