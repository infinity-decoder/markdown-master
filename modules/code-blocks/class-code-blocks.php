<?php
namespace Cotex\Modules\Code_Blocks;

use Cotex\Core\Abstract_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Module
 */
class Module extends Abstract_Module {

	/**
	 * Init module hooks.
	 */
	public function init() {
		// Enqueue Assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register Shortcode
		add_shortcode( 'cortex-code', [ $this, 'render_shortcode' ] );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_assets() {
		// PrismsJS
		wp_register_style( 'cotex-prism', $this->get_url() . '/assets/prism.css', [], '1.29.0' );
		wp_register_script( 'cotex-prism', $this->get_url() . '/assets/prism.js', [], '1.29.0', true );

		// Custom Copy Script (inline for now or separate file)
		wp_register_script( 'cotex-code-blocks', $this->get_url() . '/assets/code-blocks.js', [ 'cotex-prism', 'jquery' ], '1.0.0', true );
	}

	/**
	 * Render [cortex-code] shortcode.
	 *
	 * Attributes:
	 * - id: (optional) DB ID if we were storing code snippets, but prompt implies simple usage or maybe ID reference.
	 *       Prompt says: [cortex-code id="56"]. This implies fetching from a CPT or Table.
	 *       However, for "Code Blocks", usually it's content enclosed.
	 *       If ID is required, I need to know WHERE the content is.
	 *       The prompt "Code Blocks" purpose is "High-performance syntax highlighted code blocks".
	 *       If it functions like a CPT "Code Snippet", then `id` makes sense.
	 *       I will assume a "Code Snippet" CPT is needed or at least a way to retrieve content.
	 *       Actually, I'll make it support both: enclosed content OR ID lookup.
	 *       Given "Part 4: c. Code Blocks", it lists "Syntax highlighting, Multi-language support".
	 *       And "Shortcode: [cortex-code id="56"]".
	 *       This strongly implies a Custom Post Type "cortex_code".
	 * 
	 * @param array  $atts
	 * @param string $content
	 * @return string
	 */
	public function render_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts( [
			'id'       => 0,
			'lang'     => 'text',
			'title'    => '',
		], $atts );

		$code = $content;
		$lang = $atts['lang'];

		if ( ! empty( $atts['id'] ) ) {
			$post = get_post( $atts['id'] );
			if ( $post && 'cortex_code' === $post->post_type ) {
				$code = $post->post_content;
				// Maybe retrieve language from meta
				$meta_lang = get_post_meta( $post->ID, '_cortex_code_lang', true );
				if ( $meta_lang ) {
					$lang = $meta_lang;
				}
			}
		}

		if ( empty( $code ) ) {
			return '';
		}

		// Enqueue assets only if shortcode is used
		wp_enqueue_style( 'cotex-prism' );
		wp_enqueue_script( 'cotex-prism' );
		wp_enqueue_script( 'cotex-code-blocks' );

		// Clean up content
		$code = trim( $code ); // Basic trim, beware of eating indentation
		
		ob_start();
		?>
		<div class="cotex-code-block">
			<div class="cotex-code-header">
				<span class="cotex-lang-badge"><?php echo esc_html( strtoupper( $lang ) ); ?></span>
				<button class="cotex-copy-btn" data-clipboard-target="#code-<?php echo esc_attr( $atts['id'] ); ?>">Copy</button>
			</div>
			<pre><code id="code-<?php echo esc_attr( $atts['id'] ); ?>" class="language-<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( $code ); ?></code></pre>
		</div>
		<?php
		return ob_get_clean();
	}
}
