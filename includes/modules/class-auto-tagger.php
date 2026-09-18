<?php
/**
 * Auto-tagger module.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector\Modules;

defined( 'ABSPATH' ) || exit;

use JevConnector\Question;
use JevConnector\Response;

/**
 * Suggests existing taxonomy terms for a post, and never applies them itself.
 *
 * This is a fan-out: one noul per candidate term, all in a single call, which
 * is what System One models are built for. Terms are only ever suggested, and
 * only an editor clicking Apply writes anything. Nothing hooks save_post,
 * because autosaves, revisions and REST writes all fire it and you would pay
 * three times to tag once.
 */
final class Auto_Tagger extends Module {

	public const MAX_CANDIDATES = 40;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'auto_tagger';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public static function label(): string {
		return __( 'Term suggestions', 'connector-for-typesafe-jev' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public static function description(): string {
		return __( 'Adds a panel to the post editor that suggests existing terms, with a probability for each. Suggestions are never applied automatically.', 'connector-for-typesafe-jev' );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'taxonomy'   => 'post_tag',
			'post_types' => array( 'post' ),
			'threshold'  => 0.70,
		);
	}

	/**
	 * Clean submitted settings.
	 *
	 * @param array<string,mixed> $input Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();

		$taxonomy = isset( $input['taxonomy'] ) ? sanitize_key( (string) $input['taxonomy'] ) : '';

		$post_types = isset( $input['post_types'] ) ? (array) $input['post_types'] : $defaults['post_types'];
		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );

		return array(
			'taxonomy'   => '' !== $taxonomy ? $taxonomy : $defaults['taxonomy'],
			'post_types' => array() !== $post_types ? $post_types : $defaults['post_types'],
			'threshold'  => max( 0.0, min( 1.0, (float) ( $input['threshold'] ?? $defaults['threshold'] ) ) ),
		);
	}

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Build one noul per candidate term.
	 *
	 * @param array<int,object> $terms Term objects.
	 * @return array<string,mixed>
	 */
	public static function questions_for( array $terms ): array {
		$questions = array();

		foreach ( array_slice( $terms, 0, self::MAX_CANDIDATES ) as $term ) {
			$questions[ 'term_' . (int) $term->term_id ] = Question::noul(
				sprintf( 'Is this post substantively about "%s"?', (string) $term->name )
			);
		}

		return $questions;
	}

	/**
	 * Read suggestions out of a response.
	 *
	 * Pure, so the threshold and ordering can be tested without WordPress.
	 *
	 * @param Response          $response  The answer.
	 * @param array<int,object> $terms     Term objects that were asked about.
	 * @param float             $threshold Minimum probability to suggest.
	 * @return array<int,array<string,mixed>> Ordered, most likely first.
	 */
	public static function suggestions_from( Response $response, array $terms, float $threshold ): array {
		$suggestions = array();

		foreach ( $terms as $term ) {
			$id    = (int) $term->term_id;
			$value = $response->noul( 'term_' . $id );

			if ( null === $value || $value < $threshold ) {
				continue;
			}

			$suggestions[] = array(
				'term_id'     => $id,
				'name'        => (string) $term->name,
				'probability' => round( $value, 3 ),
			);
		}

		usort(
			$suggestions,
			static function ( array $a, array $b ): int {
				return $b['probability'] <=> $a['probability'];
			}
		);

		return $suggestions;
	}

	/**
	 * Register the panel.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		$post_types = (array) $this->setting( 'post_types', array( 'post' ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'jevc-term-suggestions',
				__( 'Term suggestions', 'connector-for-typesafe-jev' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the panel body.
	 *
	 * @return void
	 */
	public function render_meta_box(): void {
		?>
		<div class="jevc-suggestions" data-jevc-suggestions>
			<p class="description">
				<?php esc_html_e( 'Suggestions are drawn from terms that already exist. Nothing is applied until you choose it.', 'connector-for-typesafe-jev' ); ?>
			</p>
			<p>
				<button type="button" class="button" data-jevc-suggest>
					<?php esc_html_e( 'Suggest terms', 'connector-for-typesafe-jev' ); ?>
				</button>
			</p>
			<div data-jevc-results></div>
		</div>
		<?php
	}

	/**
	 * Enqueue the panel script on post edit screens only.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, (array) $this->setting( 'post_types', array( 'post' ) ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'jevc-auto-tagger',
			plugins_url( 'assets/js/auto-tagger.js', \JevConnector\PLUGIN_FILE ),
			array( 'wp-api-fetch' ),
			\JevConnector\VERSION,
			true
		);

		wp_localize_script(
			'jevc-auto-tagger',
			'jevcTagger',
			array(
				'postId'  => (int) get_the_ID(),
				'strings' => array(
					'thinking' => __( 'Asking Jev...', 'connector-for-typesafe-jev' ),
					'none'     => __( 'No terms cleared the threshold.', 'connector-for-typesafe-jev' ),
					'apply'    => __( 'Add selected', 'connector-for-typesafe-jev' ),
					'applied'  => __( 'Terms added. Update the post to keep them.', 'connector-for-typesafe-jev' ),
					'failed'   => __( 'Could not reach TypeSafe.', 'connector-for-typesafe-jev' ),
				),
			)
		);
	}

	/**
	 * Register this module's routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$args = array(
			'post_id' => array(
				'required' => true,
				'type'     => 'integer',
			),
		);

		register_rest_route(
			'jev/v1',
			'/suggest-terms',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_suggest' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $args,
				),
			)
		);

		register_rest_route(
			'jev/v1',
			'/apply-terms',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_apply' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $args + array(
						'term_ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Permission check, scoped to the specific post.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool
	 */
	public function can_edit( \WP_REST_Request $request ): bool {
		return current_user_can( 'edit_post', (int) $request->get_param( 'post_id' ) );
	}

	/**
	 * Handle POST /suggest-terms.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_suggest( \WP_REST_Request $request ) {
		$post_id  = (int) $request->get_param( 'post_id' );
		$post     = get_post( $post_id );
		$taxonomy = (string) $this->setting( 'taxonomy', 'post_tag' );

		if ( ! $post ) {
			return new \WP_Error( 'jevc_no_post', __( 'That post does not exist.', 'connector-for-typesafe-jev' ), array( 'status' => 404 ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => self::MAX_CANDIDATES,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || array() === $terms ) {
			return rest_ensure_response( array( 'suggestions' => array() ) );
		}

		$response = \JevConnector\ask(
			array(
				'title' => (string) $post->post_title,
				'body'  => wp_strip_all_tags( (string) $post->post_content ),
			),
			self::questions_for( $terms )
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'suggestions' => self::suggestions_from(
					$response,
					$terms,
					(float) $this->setting( 'threshold', 0.70 )
				),
			)
		);
	}

	/**
	 * Handle POST /apply-terms.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_apply( \WP_REST_Request $request ) {
		$post_id  = (int) $request->get_param( 'post_id' );
		$taxonomy = (string) $this->setting( 'taxonomy', 'post_tag' );
		$term_ids = array_map( 'intval', (array) $request->get_param( 'term_ids' ) );
		$term_ids = array_values( array_filter( $term_ids ) );

		if ( array() === $term_ids ) {
			return new \WP_Error( 'jevc_no_terms', __( 'No terms were selected.', 'connector-for-typesafe-jev' ), array( 'status' => 400 ) );
		}

		$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'applied'  => $term_ids,
				'taxonomy' => $taxonomy,
			)
		);
	}

	/**
	 * Render the module fields.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		?>
		<p>
			<label>
				<?php esc_html_e( 'Taxonomy', 'connector-for-typesafe-jev' ); ?>
				<input type="text" class="regular-text"
					name="<?php echo esc_attr( $this->field_name( 'taxonomy' ) ); ?>"
					value="<?php echo esc_attr( (string) $this->setting( 'taxonomy' ) ); ?>" />
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Suggestion threshold', 'connector-for-typesafe-jev' ); ?>
				<input type="number" step="0.01" min="0" max="1" class="small-text"
					name="<?php echo esc_attr( $this->field_name( 'threshold' ) ); ?>"
					value="<?php echo esc_attr( (string) $this->setting( 'threshold' ) ); ?>" />
			</label>
			<span class="description">
				<?php esc_html_e( 'Terms below this probability are not shown.', 'connector-for-typesafe-jev' ); ?>
			</span>
		</p>
		<?php
	}
}
