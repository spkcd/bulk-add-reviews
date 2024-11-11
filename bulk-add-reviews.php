<?php
/*
Plugin Name: Bulk Add Reviews with Progress Bar
Description: Adds 5-star reviews to WooCommerce products with a progress bar and AJAX.
Version: 1.3
Author: KCD x SPK
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Bulk_Add_Reviews_Plugin {
    private $option_name = 'bar_reviewers'; // Option name to store reviewers
    private $batch_option_name = 'bar_batch_size'; // Option name to store batch size

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_process_reviews_batch', array( $this, 'process_reviews_batch' ) );
        add_action( 'wp_ajax_bar_save_reviewer', array( $this, 'save_reviewer' ) );
        add_action( 'wp_ajax_bar_delete_reviewer', array( $this, 'delete_reviewer' ) );
        add_action( 'wp_ajax_bar_delete_all_reviews', array( $this, 'delete_all_reviews' ) );
        add_action( 'wp_ajax_bar_save_batch_size', array( $this, 'save_batch_size' ) );
        add_action( 'wp_ajax_bar_generate_reviews', array( $this, 'generate_reviews' ) );
        add_action( 'wp_ajax_bar_delete_generated_reviews', array( $this, 'delete_generated_reviews' ) );
    }

    // Method to get the batch size
    private function get_batch_size() {
        $batch_size = get_option( $this->batch_option_name, 500 ); // Default to 500 if not set
        return intval( $batch_size );
    }

    // Add a top-level menu page
    public function add_plugin_menu() {
        add_menu_page(
            'Bulk Add Reviews',
            'Bulk Add Reviews',
            'manage_options',
            'bulk-add-reviews',
            array( $this, 'plugin_page' ),
            'dashicons-star-filled',
            58
        );
    }

    // Enqueue necessary scripts and styles
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_bulk-add-reviews' ) {
            return;
        }

        wp_enqueue_script( 'bulk-add-reviews-script', plugin_dir_url( __FILE__ ) . 'js/bulk-add-reviews.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'bulk-add-reviews-script', 'bulkAddReviews', array(
            'ajax_url'                => admin_url( 'admin-ajax.php' ),
            'nonce'                   => wp_create_nonce( 'bulk_add_reviews_nonce' ),
            'batch_size_nonce'        => wp_create_nonce( 'bar_batch_size_nonce' ),
            'generate_reviews_nonce'  => wp_create_nonce( 'bar_generate_reviews_nonce' ),
        ) );

        wp_enqueue_style( 'bulk-add-reviews-style', plugin_dir_url( __FILE__ ) . 'css/bulk-add-reviews.css' );
    }

    // Display the plugin's main page
    public function plugin_page() {
        // Fetch existing reviewers
        $reviewers = get_option( $this->option_name, array() );
        // Fetch current batch size
        $batch_size = get_option( $this->batch_option_name, 500 );
        ?>
        <div class="wrap">
            <h1>Bulk Add Reviews</h1>

            <!-- Batch Size Setting -->
            <h2>Batch Size Setting</h2>
            <form id="bar-batch-size-form">
                <?php wp_nonce_field( 'bar_batch_size_nonce', 'bar_batch_size_nonce_field' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bar_batch_size">Batch Size</label></th>
                        <td><input type="number" id="bar_batch_size" name="bar_batch_size" class="small-text" value="<?php echo esc_attr( $batch_size ); ?>" min="1" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Batch Size</button>
                </p>
            </form>

            <!-- Reviewers Management Section -->
            <h2>Add Reviewers and Reviews</h2>
            <form id="bar-add-reviewer-form">
                <?php wp_nonce_field( 'bar_add_reviewer_nonce', 'bar_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bar_reviewer_name">Reviewer Name</label></th>
                        <td><input type="text" id="bar_reviewer_name" name="bar_reviewer_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="bar_reviewer_email">Reviewer Email</label></th>
                        <td><input type="email" id="bar_reviewer_email" name="bar_reviewer_email" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="bar_review_message">Review Message</label></th>
                        <td><textarea id="bar_review_message" name="bar_review_message" class="large-text" rows="3" required></textarea></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Add Reviewer</button>
                </p>
            </form>

            <h2>Existing Reviewers</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Review Message</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $reviewers ) ) : ?>
                        <?php foreach ( $reviewers as $index => $reviewer ) : ?>
                            <tr>
                                <td><?php echo esc_html( $reviewer['name'] ); ?></td>
                                <td><?php echo esc_html( $reviewer['email'] ); ?></td>
                                <td><?php echo esc_html( $reviewer['message'] ); ?></td>
                                <td>
                                    <button class="button bar-delete-reviewer" data-index="<?php echo esc_attr( $index ); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4">No reviewers added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Start Process Button -->
            <h2>Add Reviews to Products</h2>
            <button id="start-process" class="button button-primary">Start Adding Reviews</button>
            <div id="progress-container" style="display:none;">
                <p>Processing... Please do not close this page.</p>
                <div id="progress-bar"><div id="progress-fill"></div></div>
                <p id="progress-text">0%</p>
            </div>
            <div id="completion-message" style="display:none;">
                <p>All products have been processed.</p>
            </div>

            <!-- Delete Reviews Button -->
            <h2>Delete Added Reviews</h2>
            <button id="delete-reviews" class="button">Delete Reviews Added by Plugin</button>
            <div id="delete-progress" style="display:none;">
                <p>Deleting reviews... Please do not close this page.</p>
            </div>
            <div id="delete-completion-message" style="display:none;">
                <p>All reviews added by the plugin have been deleted.</p>
            </div>

            <!-- Generate Test Reviews Section -->
            <h2>Generate Test Reviews</h2>
            <form id="bar-generate-reviews-form">
                <?php wp_nonce_field( 'bar_generate_reviews_nonce', 'bar_generate_reviews_nonce_field' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="bar_number_of_reviews">Number of Reviews to Generate</label></th>
                        <td><input type="number" id="bar_number_of_reviews" name="bar_number_of_reviews" class="small-text" value="10" min="1" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Generate Reviews</button>
                </p>
            </form>
            <div id="generate-progress-container" style="display:none;">
                <p>Generating reviews... Please do not close this page.</p>
                <div id="generate-progress-bar"><div id="generate-progress-fill"></div></div>
                <p id="generate-progress-text">0%</p>
            </div>
            <div id="generate-completion-message" style="display:none;">
                <p>Review generation completed.</p>
            </div>

            <!-- Delete Generated Reviews Button -->
            <h2>Delete Generated Test Reviews</h2>
            <button id="delete-generated-reviews" class="button">Delete Generated Test Reviews</button>
            <div id="delete-generated-progress" style="display:none;">
                <p>Deleting test reviews... Please do not close this page.</p>
            </div>
            <div id="delete-generated-completion-message" style="display:none;">
                <p>All generated test reviews have been deleted.</p>
            </div>
        </div>
        <?php
    }

    // Save a new reviewer
    public function save_reviewer() {
        check_ajax_referer( 'bar_add_reviewer_nonce', 'nonce' );

        $name = sanitize_text_field( $_POST['name'] );
        $email = sanitize_email( $_POST['email'] );
        $message = sanitize_textarea_field( $_POST['message'] );

        $reviewers = get_option( $this->option_name, array() );
        $reviewers[] = array(
            'name'    => $name,
            'email'   => $email,
            'message' => $message,
        );
        update_option( $this->option_name, $reviewers );

        wp_send_json_success( array( 'message' => 'Reviewer added successfully.' ) );
    }

    // Delete a reviewer
    public function delete_reviewer() {
        check_ajax_referer( 'bulk_add_reviews_nonce', 'nonce' );

        $index = intval( $_POST['index'] );
        $reviewers = get_option( $this->option_name, array() );

        if ( isset( $reviewers[ $index ] ) ) {
            unset( $reviewers[ $index ] );
            $reviewers = array_values( $reviewers ); // Re-index array
            update_option( $this->option_name, $reviewers );
            wp_send_json_success( array( 'message' => 'Reviewer deleted successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Reviewer not found.' ) );
        }
    }

    // Save batch size
    public function save_batch_size() {
        check_ajax_referer( 'bar_batch_size_nonce', 'nonce' );

        $batch_size = intval( $_POST['batch_size'] );
        if ( $batch_size < 1 ) {
            wp_send_json_error( array( 'message' => 'Batch size must be at least 1.' ) );
        }

        update_option( $this->batch_option_name, $batch_size );

        wp_send_json_success( array( 'message' => 'Batch size updated successfully.' ) );
    }

    // Process a batch of reviews via AJAX
    public function process_reviews_batch() {
        check_ajax_referer( 'bulk_add_reviews_nonce', 'nonce' );

        // Get current batch number from AJAX request
        $batch_number = isset( $_POST['batch_number'] ) ? intval( $_POST['batch_number'] ) : 0;

        // Get batch size
        $batch_size = $this->get_batch_size();

        // Calculate offset
        $offset = $batch_number * $batch_size;

        // Get total number of products (cached)
        $total_products = get_transient( 'bar_total_products' );
        if ( $total_products === false ) {
            $total_products = wp_count_posts( 'product' )->publish;
            set_transient( 'bar_total_products', $total_products, HOUR_IN_SECONDS );
        }

        // Get products for this batch
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $batch_size,
            'fields'         => 'ids',
            'offset'         => $offset,
        );

        $product_ids = get_posts( $args );

        if ( empty( $product_ids ) ) {
            // No more products to process
            wp_send_json_success( array( 'complete' => true ) );
        }

        // Get reviewers
        $reviewers = get_option( $this->option_name, array() );
        if ( empty( $reviewers ) ) {
            wp_send_json_error( array( 'message' => 'No reviewers available. Please add reviewers first.' ) );
        }

        foreach ( $product_ids as $product_id ) {
            // Check if the product already has reviews
            $existing_reviews = get_comments( array(
                'post_id' => $product_id,
                'status'  => 'approve',
                'type'    => 'review',
                'count'   => true,
            ) );

            if ( $existing_reviews > 0 ) {
                // Skip products that already have reviews
                continue;
            }

            // Randomly select a reviewer
            $reviewer = $reviewers[ array_rand( $reviewers ) ];

            // Prepare comment data
            $commentdata = array(
                'comment_post_ID'      => $product_id,
                'comment_author'       => $reviewer['name'],
                'comment_author_email' => $reviewer['email'],
                'comment_content'      => $reviewer['message'],
                'comment_type'         => 'review',
                'comment_approved'     => 1,
            );

            // Insert comment
            $comment_id = wp_insert_comment( $commentdata );

            // Add rating meta data
            if ( $comment_id ) {
                update_comment_meta( $comment_id, 'rating', 5 );
                update_comment_meta( $comment_id, 'verified', 1 );
            }
        }

        // Calculate progress
        $processed = min( ( $batch_number + 1 ) * $batch_size, $total_products );
        $percentage = round( ( $processed / $total_products ) * 100 );

        wp_send_json_success( array(
            'batch_number' => $batch_number + 1,
            'percentage'   => $percentage,
            'complete'     => false,
        ) );
    }

    // Delete all reviews added by the plugin
    public function delete_all_reviews() {
        check_ajax_referer( 'bulk_add_reviews_nonce', 'nonce' );

        // Get reviewers
        $reviewers = get_option( $this->option_name, array() );

        if ( empty( $reviewers ) ) {
            wp_send_json_error( array( 'message' => 'No reviewers found.' ) );
        }

        // Collect reviewer emails
        $emails = wp_list_pluck( $reviewers, 'email' );

        // Get all comments by these emails
        $args = array(
            'type'         => 'review',
            'status'       => 'approve',
            'number'       => 0, // Get all
            'author_email' => $emails,
        );

        $comments = get_comments( $args );

        if ( ! empty( $comments ) ) {
            foreach ( $comments as $comment ) {
                wp_delete_comment( $comment->comment_ID, true );
            }
        }

        wp_send_json_success( array( 'message' => 'All reviews added by the plugin have been deleted.' ) );
    }

    // Generate Test Reviews
    public function generate_reviews() {
        check_ajax_referer( 'bar_generate_reviews_nonce', 'nonce' );

        $number_of_reviews = isset( $_POST['number_of_reviews'] ) ? intval( $_POST['number_of_reviews'] ) : 10;

        if ( $number_of_reviews < 1 ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid number of reviews to generate.' ) );
        }

        // Get all product IDs
        $product_ids = get_posts( array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        if ( empty( $product_ids ) ) {
            wp_send_json_error( array( 'message' => 'No products found to add reviews to.' ) );
        }

        // Prepare sample data
        $sample_names = array( 'Alice', 'Bob', 'Charlie', 'David', 'Eve', 'Frank', 'Grace', 'Hannah', 'Ivy', 'Jack' );
        $sample_emails = array( 'alice@example.com', 'bob@example.com', 'charlie@example.com', 'david@example.com', 'eve@example.com', 'frank@example.com', 'grace@example.com', 'hannah@example.com', 'ivy@example.com', 'jack@example.com' );
        $sample_messages = array(
            'Excellent product! Highly recommended.',
            'Good value for the price.',
            'Satisfied with the quality.',
            'Exceeded my expectations!',
            'Would buy again.',
            'Fast shipping and great service.',
            'Five stars!',
            'Not bad, could be better.',
            'Loved it!',
            'Fantastic, just what I needed.',
        );

        // Loop to generate reviews
        for ( $i = 0; $i < $number_of_reviews; $i++ ) {
            // Randomly select a product
            $product_id = $product_ids[ array_rand( $product_ids ) ];

            // Randomly select reviewer details
            $index = array_rand( $sample_names );
            $name = $sample_names[ $index ];
            $email = $sample_emails[ $index ];
            $message = $sample_messages[ array_rand( $sample_messages ) ];
            $rating = rand( 4, 5 ); // Random rating between 4 and 5

            // Prepare comment data
            $commentdata = array(
                'comment_post_ID'      => $product_id,
                'comment_author'       => $name,
                'comment_author_email' => $email,
                'comment_content'      => $message,
                'comment_type'         => 'review',
                'comment_approved'     => 1,
            );

            // Insert comment
            $comment_id = wp_insert_comment( $commentdata );

            // Add rating meta data
            if ( $comment_id ) {
                update_comment_meta( $comment_id, 'rating', $rating );
                update_comment_meta( $comment_id, 'verified', 1 );
                update_comment_meta( $comment_id, 'is_generated', 1 ); // Mark as generated
            }
        }

        wp_send_json_success( array( 'message' => 'Reviews generated successfully.' ) );
    }

    // Delete Generated Test Reviews
    public function delete_generated_reviews() {
        check_ajax_referer( 'bulk_add_reviews_nonce', 'nonce' );

        // Get all comments marked as generated
        $args = array(
            'type'       => 'review',
            'status'     => 'approve',
            'meta_key'   => 'is_generated',
            'meta_value' => 1,
            'number'     => 0, // Get all
        );

        $comments = get_comments( $args );

        if ( ! empty( $comments ) ) {
            foreach ( $comments as $comment ) {
                wp_delete_comment( $comment->comment_ID, true );
            }
        }

        wp_send_json_success( array( 'message' => 'All generated test reviews have been deleted.' ) );
    }
}

new Bulk_Add_Reviews_Plugin();