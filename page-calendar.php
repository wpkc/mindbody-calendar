<?php
/**
 * All contents (c)2016 Kazimer Corp.
 * Removal in part or in whole of this copyright notice is not allowed.
 * Selling this code or any derivative works is a violation of copyright law.
 * Kazimer Corp and its employees will be held harmless should any loss occur while using this code.
 * Use of this code constitutes full agreement with all these terms.
 *
 *  http://www.kazimer.com
 *
 *  @author     Kazimer Corp
 *  @copyright  (c) 2016 - Kazimer Corp
 *  @version    1.0.0
 *
 *  @package WordPress
 *
 * Template Name: MindBody Calendar
 *
 */
get_header();
?>
<div id="content">
    <div id="main" class="site-main">
        <div id="primary" class="content-area">
            <div class="site-content" role="main">

                <?php
                if (have_posts()) {
                    the_post();
                }
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <div class="entry-content">
                        <?php
                        $cal = new mbo_calendar();
                        echo $cal->create_calendar();
                        ?>
                    </div>
                </article>
            </div><!-- .site-content -->
        </div><!-- #primary -->
        <?php get_sidebar(); ?>
    </div><!-- #main -->
    <?php get_footer(); ?>
