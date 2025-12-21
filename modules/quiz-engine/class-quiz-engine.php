<?php
namespace Cotex\Modules\Quiz_Engine;

use Cotex\Core\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Module
 */
class Module extends Abstract_Module {

	/**
	 * Init hooks.
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_shortcode( 'cortex-quiz', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		// AJAX for quiz submission
		add_action( 'wp_ajax_cortex_submit_quiz', [ $this, 'ajax_submit_quiz' ] );
		add_action( 'wp_ajax_nopriv_cortex_submit_quiz', [ $this, 'ajax_submit_quiz' ] );
	}

	/**
	 * Register Quiz CPT.
	 */
	public function register_cpt() {
		register_post_type( 'cortex_quiz', [
			'labels' => [
				'name'          => 'Quizzes',
				'singular_name' => 'Quiz',
			],
			'public'      => false,
			'show_ui'     => true,
			'show_in_menu' => 'cotex',
			'supports'    => [ 'title', 'editor', 'custom-fields' ], // Editor for description, custom-fields for Q&A data
			'capability_type' => 'post',
		] );
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_assets() {
		wp_register_style( 'cotex-quiz', $this->get_url() . '/assets/quiz.css', [], '1.0.0' );
		wp_register_script( 'cotex-quiz', $this->get_url() . '/assets/quiz.js', [ 'jquery' ], '1.0.0', true );
		
		wp_localize_script( 'cotex-quiz', 'cotexQuizVars', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		] );
	}

	/**
	 * Render Quiz Shortcode.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'id' => 0,
		], $atts );

		if ( ! $atts['id'] ) {
			return '';
		}

		$post = get_post( $atts['id'] );
		if ( ! $post || 'cortex_quiz' !== $post->post_type ) {
			return '<p>Quiz not found.</p>';
		}

		wp_enqueue_style( 'cotex-quiz' );
		wp_enqueue_script( 'cotex-quiz' );

		// Demo data for now, ideally fetched from post meta
		// $questions = get_post_meta($post->ID, '_cortex_quiz_data', true);
		// Mocking structure for demonstration
		$questions = [
			[
				'id' => 1,
				'type' => 'mcq',
				'question' => 'What is the capital of France?',
				'options' => ['London', 'Berlin', 'Paris', 'Madrid'],
			],
			[
				'id' => 2,
				'type' => 'tf',
				'question' => 'The earth is flat.',
				'options' => ['True', 'False'],
			]
		];

		ob_start();
		?>
		<div class="cotex-quiz-container" data-id="<?php echo esc_attr( $atts['id'] ); ?>">
			<h2><?php echo esc_html( $post->post_title ); ?></h2>
			<div class="cotex-quiz-questions">
				<?php foreach ( $questions as $index => $q ) : ?>
					<div class="cotex-question" data-qid="<?php echo esc_attr( $q['id'] ); ?>">
						<h3><?php echo esc_html( ( $index + 1 ) . '. ' . $q['question'] ); ?></h3>
						<div class="cotex-options">
							<?php foreach ( $q['options'] as $opt ) : ?>
								<label>
									<input type="radio" name="q_<?php echo esc_attr( $q['id'] ); ?>" value="<?php echo esc_attr( $opt ); ?>">
									<?php echo esc_html( $opt ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button class="cotex-btn cotex-submit-quiz">Submit Quiz</button>
			<div class="cotex-quiz-result"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle AJAX Submission.
	 */
	public function ajax_submit_quiz() {
		// Verify nonce implied
		$quiz_id = intval( $_POST['quiz_id'] );
		$answers = isset($_POST['answers']) ? $_POST['answers'] : [];

		// Grading logic here (mocked)
		$score = 0;
		// calculate score...
		$score = count($answers) * 10; // Dummy score

		wp_send_json_success( [ 'score' => $score, 'message' => 'Quiz submitted successfully!' ] );
	}
}
