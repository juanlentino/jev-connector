<?php
/**
 * Comment guardrail module.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector\Modules;

defined( 'ABSPATH' ) || exit;

use JevConnector\Question;
use JevConnector\Response;

/**
 * Routes incoming comments using two questions that have to agree.
 *
 * The design rule here is that every failure mode lands somewhere a human can
 * undo. The worst verdict this module will issue is "spam", which is
 * recoverable from the spam folder. It never returns "trash", and when the API
 * is unreachable it hands back whatever WordPress already decided.
 */
final class Comment_Guardrail extends Module {

	/**
	 * Decisions waiting to be written to comment meta, keyed by content hash.
	 *
	 * The pre_comment_approved filter runs before the comment exists, so the audit trail
	 * is stashed here and written once there is an id to attach it to.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $pending = array();

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'comment_guardrail';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public static function label(): string {
		return __( 'Comment guardrail', 'connector-for-typesafe-jev' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public static function description(): string {
		return __( 'Evaluate incoming comments and route them to approve, hold, or spam. Holds anything it is not sure about, and never deletes.', 'connector-for-typesafe-jev' );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'spam_threshold'    => 0.95,
			'approve_threshold' => 0.90,
			'trust_logged_in'   => true,
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

		return array(
			'spam_threshold'    => self::clamp( $input['spam_threshold'] ?? $defaults['spam_threshold'] ),
			'approve_threshold' => self::clamp( $input['approve_threshold'] ?? $defaults['approve_threshold'] ),
			'trust_logged_in'   => ! empty( $input['trust_logged_in'] ),
		);
	}

	/**
	 * Force a value into the 0 to 1 range.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	private static function clamp( $value ): float {
		return max( 0.0, min( 1.0, (float) $value ) );
	}

	/**
	 * Attach hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'pre_comment_approved', array( $this, 'filter_approved' ), 20, 2 );
		add_action( 'wp_insert_comment', array( $this, 'store_decision' ), 10, 2 );
	}

	/**
	 * The questions this module asks.
	 *
	 * Two independent readings of the same comment. A spam verdict needs both
	 * to agree, which is the cheapest defense against a single confident
	 * misread throwing away a real comment.
	 *
	 * @return array<string,mixed>
	 */
	public static function questions(): array {
		return array(
			'spam'  => Question::noul(
				'Is this comment spam: unsolicited promotion, link farming, or machine-generated filler?'
			),
			'route' => Question::choice(
				'How should a moderator handle this comment?',
				array(
					'approve' => 'A real comment from a real person, on topic and civil',
					'hold'    => 'Plausible but needs a human look: borderline, off topic, or hard to read',
					'trash'   => 'Spam, link farming, abuse, or automated promotion',
				)
			),
		);
	}

	/**
	 * Turn a response into a WordPress comment status.
	 *
	 * Pure, so the decision matrix can be tested without WordPress.
	 *
	 * @param Response            $response  The answer.
	 * @param array<string,mixed> $settings  Module settings.
	 * @return int|string 1 to approve, 0 to hold, or 'spam'.
	 */
	public static function decide( Response $response, array $settings ) {
		$defaults = self::defaults();
		$spam_at  = (float) ( $settings['spam_threshold'] ?? $defaults['spam_threshold'] );
		$ok_at    = (float) ( $settings['approve_threshold'] ?? $defaults['approve_threshold'] );

		$route      = $response->choice( 'route' );
		$confidence = $response->confidence( 'route' );
		$spam       = $response->noul( 'spam' );

		if ( null === $route || null === $confidence || null === $spam ) {
			return 0;
		}

		// Both questions must agree before anything is called spam.
		if ( 'trash' === $route && $confidence >= $spam_at && $spam >= $spam_at ) {
			return 'spam';
		}

		// And both must agree before anything skips moderation.
		if ( 'approve' === $route && $confidence >= $ok_at && $spam <= ( 1 - $ok_at ) ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Evaluate a comment on the way in.
	 *
	 * @param int|string|\WP_Error $approved    Current status.
	 * @param array<string,mixed>  $commentdata Comment data.
	 * @return int|string|\WP_Error
	 */
	public function filter_approved( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		if ( ! is_array( $commentdata ) || empty( $commentdata['comment_content'] ) ) {
			return $approved;
		}

		if ( $this->is_trusted( $commentdata ) ) {
			return $approved;
		}

		$response = \JevConnector\ask( $this->state( $commentdata ), self::questions() );

		if ( is_wp_error( $response ) ) {
			/**
			 * Fires when the guardrail could not reach TypeSafe.
			 *
			 * The comment is left exactly as WordPress decided. An outage must
			 * never silently change moderation behavior.
			 *
			 * @param \WP_Error           $response    The failure.
			 * @param array<string,mixed> $commentdata Comment data.
			 */
			do_action( 'jevc_guardrail_unavailable', $response, $commentdata );

			return $approved;
		}

		$decision = self::decide( $response, $this->settings() );

		$this->pending[ $this->hash( $commentdata ) ] = array(
			'decision'      => $decision,
			'previous'      => $approved,
			'spam'          => $response->noul( 'spam' ),
			'route'         => $response->choice( 'route' ),
			'confidence'    => $response->confidence( 'route' ),
			'probabilities' => $response->probabilities( 'route' ),
			'model'         => $response->model(),
		);

		/**
		 * Filters the guardrail's verdict before it is applied.
		 *
		 * @param int|string          $decision    1, 0, or 'spam'.
		 * @param Response            $response    The answer.
		 * @param array<string,mixed> $commentdata Comment data.
		 */
		return apply_filters( 'jevc_guardrail_decision', $decision, $response, $commentdata );
	}

	/**
	 * Write the audit trail once the comment has an id.
	 *
	 * @param int    $comment_id The new comment id.
	 * @param object $comment    The comment object.
	 * @return void
	 */
	public function store_decision( $comment_id, $comment ): void {
		$key = $this->hash( array( 'comment_content' => $comment->comment_content ?? '' ) );

		if ( ! isset( $this->pending[ $key ] ) ) {
			return;
		}

		add_comment_meta( (int) $comment_id, '_jevc_decision', $this->pending[ $key ] );

		unset( $this->pending[ $key ] );
	}

	/**
	 * Whether this comment should skip evaluation entirely.
	 *
	 * @param array<string,mixed> $commentdata Comment data.
	 * @return bool
	 */
	private function is_trusted( array $commentdata ): bool {
		if ( ! $this->setting( 'trust_logged_in', true ) ) {
			return false;
		}

		$user_id = isset( $commentdata['user_id'] ) ? (int) $commentdata['user_id'] : 0;

		return $user_id > 0 && user_can( $user_id, 'edit_posts' );
	}

	/**
	 * Build the state sent for evaluation.
	 *
	 * Deliberately narrow. The comment, who signed it, and what it is replying
	 * to is enough to judge it; email addresses and IP addresses are not sent.
	 *
	 * @param array<string,mixed> $commentdata Comment data.
	 * @return array<string,mixed>
	 */
	private function state( array $commentdata ): array {
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;

		$state = array(
			'comment' => (string) $commentdata['comment_content'],
			'author'  => isset( $commentdata['comment_author'] ) ? (string) $commentdata['comment_author'] : '',
		);

		if ( $post_id > 0 ) {
			$state['post_title'] = (string) get_the_title( $post_id );
		}

		/**
		 * Filters the state sent for a comment.
		 *
		 * @param array<string,mixed> $state       What will be sent.
		 * @param array<string,mixed> $commentdata Comment data.
		 */
		return (array) apply_filters( 'jevc_guardrail_state', $state, $commentdata );
	}

	/**
	 * Key used to match a stashed decision to the inserted comment.
	 *
	 * @param array<string,mixed> $commentdata Comment data.
	 * @return string
	 */
	private function hash( array $commentdata ): string {
		return md5( (string) ( $commentdata['comment_content'] ?? '' ) );
	}

	/**
	 * Render the threshold fields.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		?>
		<p>
			<label>
				<?php esc_html_e( 'Spam threshold', 'connector-for-typesafe-jev' ); ?>
				<input type="number" step="0.01" min="0" max="1" class="small-text"
					name="<?php echo esc_attr( $this->field_name( 'spam_threshold' ) ); ?>"
					value="<?php echo esc_attr( (string) $this->setting( 'spam_threshold' ) ); ?>" />
			</label>
			<span class="description">
				<?php esc_html_e( 'Both questions must clear this before a comment is marked spam.', 'connector-for-typesafe-jev' ); ?>
			</span>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Approve threshold', 'connector-for-typesafe-jev' ); ?>
				<input type="number" step="0.01" min="0" max="1" class="small-text"
					name="<?php echo esc_attr( $this->field_name( 'approve_threshold' ) ); ?>"
					value="<?php echo esc_attr( (string) $this->setting( 'approve_threshold' ) ); ?>" />
			</label>
			<span class="description">
				<?php esc_html_e( 'Below this, the comment is held for moderation instead.', 'connector-for-typesafe-jev' ); ?>
			</span>
		</p>
		<p>
			<label>
				<input type="checkbox" value="1"
					name="<?php echo esc_attr( $this->field_name( 'trust_logged_in' ) ); ?>"
					<?php checked( (bool) $this->setting( 'trust_logged_in' ) ); ?> />
				<?php esc_html_e( 'Skip evaluation for logged-in users who can edit posts', 'connector-for-typesafe-jev' ); ?>
			</label>
		</p>
		<?php
	}
}
