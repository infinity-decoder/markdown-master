<?php
/**
 * Archive Course (Grid View)
 *
 * @package Cortex
 */

get_header(); ?>

<div class="cortex-wrap-parent cortex-course-archive-page cortex-page-wrap cortex-pt-32 cortex-pb-32">
    <div class="cortex-container">
        <div class="cortex-row cortex-gx-xl-5">
            
            <!-- Filters Sidebar -->
            <aside class="cortex-col-lg-3 cortex-mb-40 cortex-mb-lg-0">
                <div class="cortex-course-archive-sidebar cortex-border cortex-rounded cortex-p-24 cortex-bg-white">
                    <h4 class="cortex-fs-6 cortex-fw-bold cortex-mb-24"><?php _e( 'Filter By', 'cortex' ); ?></h4>
                    
                    <!-- Search -->
                    <div class="cortex-form-group cortex-mb-24">
                        <label class="cortex-mb-8 cortex-d-block cortex-fs-7 cortex-fw-medium"><?php _e( 'Search', 'cortex' ); ?></label>
                        <input type="text" class="cortex-form-control" placeholder="<?php _e( 'Search courses...', 'cortex' ); ?>">
                    </div>

                    <!-- Categories -->
                    <div class="cortex-filter-group cortex-mb-24">
                        <h5 class="cortex-fs-7 cortex-fw-bold cortex-mb-12"><?php _e( 'Category', 'cortex' ); ?></h5>
                        <ul class="cortex-ul-list">
                             <?php
                             $cats = get_terms( array( 'taxonomy' => 'course_category', 'hide_empty' => false ) );
                             if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) :
                                foreach ( $cats as $cat ) : ?>
                                    <li class="cortex-mb-8">
                                        <label class="cortex-form-check cortex-flex cortex-align-center">
                                            <input type="checkbox" class="cortex-form-check-input cortex-mr-8">
                                            <span class="cortex-fs-7 cortex-text-muted"><?php echo esc_html( $cat->name ); ?></span>
                                        </label>
                                    </li>
                                <?php endforeach;
                             endif; ?>
                        </ul>
                    </div>
                </div>
            </aside>

            <!-- Main Listing -->
            <div class="cortex-col-lg-9">
                
                <!-- Grid Header -->
                <div class="cortex-archive-header cortex-flex cortex-justify-between cortex-align-center cortex-mb-32">
                    <h2 class="cortex-fs-4 cortex-fw-bold cortex-m-0">
                        <?php _e( 'All Courses', 'cortex' ); ?>
                    </h2>
                    <div class="cortex-message-sort cortex-d-none cortex-d-md-block">
                         <!-- Sorting Dropdown Placeholder -->
                         <select class="cortex-form-select cortex-fs-7">
                             <option><?php _e( 'Release Date (newest first)', 'cortex' ); ?></option>
                             <option><?php _e( 'Most Popular', 'cortex' ); ?></option>
                         </select>
                    </div>
                </div>

                <!-- Course Grid -->
                <div class="cortex-row cortex-gx-30 cortex-gy-30">
                    <?php if ( have_posts() ) : ?>
                        <?php while ( have_posts() ) : the_post(); ?>
                            <?php 
                            $price = get_post_meta( get_the_ID(), '_price', true ); 
                            $author_id = get_the_author_meta( 'ID' );
                            ?>
                            <div class="cortex-col-md-6 cortex-col-xl-4">
                                <div class="cortex-card cortex-course-card cortex-h-100 cortex-hover-shadow cortex-transition">
                                    <!-- Header / Image -->
                                    <div class="cortex-card-header cortex-p-0 cortex-position-relative">
                                        <a href="<?php the_permalink(); ?>" class="cortex-d-block cortex-ratio cortex-ratio-16x9">
                                            <?php if ( has_post_thumbnail() ) : ?>
                                                <?php the_post_thumbnail( 'medium_large', array( 'class' => 'cortex-img-fluid cortex-object-cover' ) ); ?>
                                            <?php else: ?>
                                                <div class="cortex-bg-light cortex-flex cortex-center cortex-h-100">
                                                    <span class="dashicons dashicons-book cortex-text-muted" style="font-size:48px;width:auto;height:auto;"></span>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        <div class="cortex-card-badges cortex-position-absolute cortex-top-0 cortex-left-0 cortex-p-16">
                                             <!-- Logic for badges like 'Best Seller' -->
                                        </div>
                                    </div>

                                    <!-- Body -->
                                    <div class="cortex-card-body cortex-p-24 cortex-flex cortex-column">
                                        
                                        <!-- Meta -->
                                        <div class="cortex-course-meta cortex-fs-7 cortex-text-muted cortex-flex cortex-align-center cortex-mb-12">
                                            <span class="dashicons dashicons-star-filled cortex-color-warning cortex-mr-4" style="font-size:16px;"></span>
                                            <span class="cortex-fw-bold cortex-color-black cortex-mr-4">4.8</span>
                                            <span>(10)</span>
                                        </div>

                                        <!-- Title -->
                                        <h3 class="cortex-course-title cortex-fs-6 cortex-fw-bold cortex-mb-12">
                                            <a href="<?php the_permalink(); ?>" class="cortex-color-black cortex-text-decoration-none">
                                                <?php the_title(); ?>
                                            </a>
                                        </h3>

                                        <!-- Author -->
                                        <div class="cortex-course-author cortex-flex cortex-align-center cortex-mb-24">
                                             <?php echo get_avatar( $author_id, 24, '', '', array( 'class' => 'cortex-rounded-circle cortex-mr-8' ) ); ?>
                                             <span class="cortex-fs-7 cortex-text-muted"><?php _e( 'By', 'cortex' ); ?> <?php the_author(); ?></span>
                                        </div>
                                        
                                        <!-- Footer (Price) -->
                                        <div class="cortex-card-footer cortex-mt-auto cortex-pt-16 cortex-border-top cortex-flex cortex-justify-between cortex-align-center">
                                             <div class="cortex-course-price">
                                                  <span class="cortex-fs-5 cortex-fw-bold cortex-color-primary">
                                                      <?php echo $price ? esc_html( $price ) : __( 'Free', 'cortex' ); ?>
                                                  </span>
                                             </div>
                                             <!-- Wishlist Icon -->
                                             <span class="dashicons dashicons-heart cortex-cursor-pointer cortex-text-muted"></span>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <div class="cortex-col-12">
                            <p><?php _e( 'No courses found.', 'cortex' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <div class="cortex-pagination cortex-mt-40 cortex-text-center">
                    <?php
                    echo paginate_links( array(
                        'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
                        'next_text' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
                    ) );
                    ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
