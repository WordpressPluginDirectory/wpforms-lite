<?php

namespace WPForms\Integrations\ProductApi\Events;

use Throwable;
use WPForms\Integrations\AI\Admin\Ajax\Forms as AiForms;
use WPForms\Integrations\IntegrationInterface;
use WPForms\Integrations\ProductApi\ProductEvents;
use WPForms\Integrations\UsageTracking\UsageTracking;
use WPForms\Lite\Admin\Connect;
use WPForms\Vendor\ProductApi\ProductApi;

/**
 * Product events for what an administrator does in the dashboard.
 *
 * Every event here is sent on the request that produced it, in the `user` context, so it is
 * attributed to the person who acted. Nothing is buffered: each path runs inside an admin,
 * AJAX or REST request with a logged-in user behind it.
 *
 * There is no backfill. A license activated, a wizard completed, a form created or an
 * integration connected before consent is never reconstructed. Guards are written before the
 * send and released when the send is refused, so a refused gate never silences a site for good.
 *
 * @since 2.0.2.1
 */
class AdminEvents implements IntegrationInterface {

	/**
	 * Event sent when the site's license changes to a new valid key.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_LICENSE = 'license_activated';

	/**
	 * Event sent when the plugin is deactivated.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_DEACTIVATED = 'plugin_deactivated';

	/**
	 * Event sent once per onboarding flow the site finished.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_ONBOARDING = 'onboarding_completed';

	/**
	 * Event sent once per form created.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_FORM_CREATED = 'form_created';

	/**
	 * Event sent once per provider connected on a form.
	 *
	 * @since 2.0.2.1
	 */
	public const EVENT_INTEGRATION = 'integration_connected';

	/**
	 * Option holding a keyed hash of the last license key reported.
	 *
	 * Not the key: the key already sits in `wpforms_license`, and a salted hash is enough to
	 * tell a re-entered key from a new one without storing a second copy of the secret.
	 *
	 * @since 2.0.2.1
	 */
	public const LICENSE_OPTION = 'wpforms_product_events_license';

	/**
	 * Option holding the onboarding flow types already reported.
	 *
	 * @since 2.0.2.1
	 */
	public const ONBOARDING_OPTION = 'wpforms_product_events_onboarding';

	/**
	 * Form meta holding the provider slugs already reported for the form.
	 *
	 * @since 2.0.2.1
	 */
	public const INTEGRATIONS_META = 'wpforms_product_events_integrations';

	/**
	 * Onboarding flow: the Setup Wizard.
	 *
	 * @since 2.0.2.1
	 */
	public const FLOW_WIZARD = 'wizard';

	/**
	 * Onboarding flow: the Setup Checklist.
	 *
	 * @since 2.0.2.1
	 */
	public const FLOW_CHECKLIST = 'checklist';

	/**
	 * Onboarding flow: the Challenge.
	 *
	 * @since 2.0.2.1
	 */
	public const FLOW_CHALLENGE = 'challenge';

	/**
	 * License option flags that mean the write is a failed validation, not an activation.
	 *
	 * @since 2.0.2.1
	 */
	private const LICENSE_FLAGS = [ 'is_expired', 'is_disabled', 'is_invalid', 'is_limit_reached', 'is_flagged' ];

	/**
	 * Upper bound => label for `prior_forms_count`, the sheet's enum.
	 *
	 * @since 2.0.2.1
	 */
	private const FORMS_BUCKETS = [
		0           => '0',
		1           => '1',
		5           => '2-5',
		PHP_INT_MAX => '6+',
	];

	/**
	 * Upper bound => label for `prior_entries_bucket`, the sheet's enum.
	 *
	 * @since 2.0.2.1
	 */
	private const ENTRIES_BUCKETS = [
		0           => '0',
		5           => '1-5',
		20          => '6-20',
		100         => '21-100',
		PHP_INT_MAX => '100+',
	];

	/**
	 * Form statuses that are not a form the site built.
	 *
	 * @since 2.0.2.1
	 */
	private const IGNORED_FORM_STATUSES = [ 'trash', 'auto-draft' ];

	/**
	 * Wizard outcomes that finish onboarding.
	 *
	 * `forms` is the final button a site that already has forms gets instead of build or
	 * import, so it is a completion too. `exit` is the skip button.
	 *
	 * @since 2.0.2.1
	 */
	private const WIZARD_COMPLETED_OUTCOMES = [ 'build', 'import', 'forms' ];

	/**
	 * Indicate if the integration is allowed to load.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	public function allow_load(): bool {

		// The same test ProductEvents uses, so the two load or stay out together.
		return class_exists( ProductApi::class );
	}

	/**
	 * Load the integration.
	 *
	 * @since 2.0.2.1
	 */
	public function load(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.2.1
	 */
	private function hooks(): void {

		// The option, not the writers: four code paths write the license, and the Connect
		// upgrade never verifies the key inside the plugin at all. Both hooks pass the new
		// value second, so one callback serves the rewrite and the first write alike.
		add_action( 'update_option_wpforms_license', [ $this, 'maybe_track_license' ], 10, 2 );
		add_action( 'add_option_wpforms_license', [ $this, 'maybe_track_license' ], 10, 2 );

		// Pro validates the key while constructing its license object on wpforms_loaded,
		// before this integration is loaded, so a key defined as WPFORMS_LICENSE_KEY is
		// written with nobody listening. The admin request picks that write up.
		add_action( 'admin_init', [ $this, 'maybe_track_missed_license' ] );

		// The WordPress hook Install::deactivate() already uses, registered here as well so
		// class-install.php stays untouched. Shutdown still runs in that request.
		register_deactivation_hook( WPFORMS_PLUGIN_FILE, [ $this, 'maybe_track_deactivation' ] );

		add_action( 'wpforms_setup_wizard_service_state_manager_complete', [ $this, 'maybe_track_wizard' ] );
		add_action( 'wpforms_setup_checklist_state_dismiss', [ $this, 'maybe_track_checklist' ] );
		add_action( 'wpforms_admin_challenge_set_challenge_option', [ $this, 'maybe_track_challenge' ], 10, 2 );

		add_action( 'wpforms_create_form', [ $this, 'maybe_track_form_created' ], 10, 3 );
		add_action( 'wpforms_builder_save_form', [ $this, 'maybe_track_integrations' ] );
	}

	/**
	 * Report a license the site did not hold before, off a write to the license option.
	 *
	 * @since 2.0.2.1
	 *
	 * @param mixed $old_value The previous value on a rewrite, the option name on a first write.
	 * @param mixed $value     The license option value as written.
	 */
	public function maybe_track_license( $old_value, $value ): void {

		try {
			$license = is_array( $value ) ? $value : [];

			if ( ! $this->is_valid_license( $license ) ) {
				return;
			}

			$product_events = $this->get_product_events();

			if ( ! $product_events ) {
				return;
			}

			// The key in force, not the one the option carries: a site that defines the
			// constant after entering a key keeps the old key in the option for good.
			$key  = (string) wpforms_get_license_key();
			$type = strtolower( (string) $license['type'] );
			$hash = wp_hash( $key );

			// The same key written again is the daily re-validation, a renewal clearing a
			// flag, or a key re-entered after deactivation. None is a new license.
			if ( hash_equals( (string) get_option( self::LICENSE_OPTION, '' ), $hash ) ) {
				return;
			}

			update_option( self::LICENSE_OPTION, $hash, false );

			// A site licensed before this release has no hash yet, and its first rewrite of
			// the option would read as an activation with years of forms and entries behind
			// it. The previous value tells that apart: a key that was already verified is
			// being re-validated, not activated. The hash stays written, so the next rewrite
			// returns above without reading the previous value.
			if ( $this->was_licensed( $old_value, $key ) ) {
				return;
			}

			$sent = $product_events->track(
				self::EVENT_LICENSE,
				[
					'license_tier'         => $type,
					'prior_lite'           => $this->had_lite(),
					'prior_forms_count'    => $this->bucket( $this->count_forms(), self::FORMS_BUCKETS ),
					'prior_entries_bucket' => $this->bucket( $this->count_entries(), self::ENTRIES_BUCKETS ),
				]
			);

			// A hash with no event behind it would silence the site for good.
			if ( ! $sent ) {
				delete_option( self::LICENSE_OPTION );
			}
		} catch ( Throwable $e ) {
			// This runs inside the license code's own option write. A missing analytics row
			// costs less than an activation that fails.
			unset( $e );
		}
	}

	/**
	 * Report a license that was written while nothing was listening.
	 *
	 * A valid license whose key does not match the guard is a write the option hooks
	 * never saw: the first validation of a WPFORMS_LICENSE_KEY constant, a change of that
	 * constant, or a key written from outside the plugin. A site licensed before this
	 * release is not one of them, the 2.0.2.1 upgrade wrote its guard before Pro
	 * validated anything. With no previous value to read, the write counts as an
	 * activation, the same way a first write to the option does.
	 *
	 * @since 2.0.2.1
	 */
	public function maybe_track_missed_license(): void {

		$license = get_option( 'wpforms_license' );

		$this->maybe_track_license( false, is_array( $license ) ? $license : [] );
	}

	/**
	 * Report the plugin being switched off.
	 *
	 * @since 2.0.2.1
	 */
	public function maybe_track_deactivation(): void {

		try {
			// Every Lite-to-Pro upgrade deactivates Lite from Lite's own code, with Pro already
			// on disk. That is a swap, and reporting it would read as churn from the best cohort.
			if ( $this->is_upgrade_swap() ) {
				return;
			}

			$product_events = $this->get_product_events();

			if ( ! $product_events ) {
				return;
			}

			$counts = (array) wp_count_posts( 'wpforms' );

			$product_events->track(
				self::EVENT_DEACTIVATED,
				[
					'days_active'       => $this->get_days_active(),
					'active_form_count' => (int) ( $counts['publish'] ?? 0 ),
				]
			);
		} catch ( Throwable $e ) {
			// Deactivation must complete whatever happens here.
			unset( $e );
		}
	}

	/**
	 * Whether this is Lite being deactivated in favour of a Pro build already installed.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	private function is_upgrade_swap(): bool {

		return ! wpforms()->is_pro() && file_exists( trailingslashit( WP_PLUGIN_DIR ) . Connect::PRO_PLUGIN );
	}

	/**
	 * Whole days since the plugin was first activated, either edition.
	 *
	 * @since 2.0.2.1
	 *
	 * @return int
	 */
	private function get_days_active(): int {

		$activated = wpforms_get_activated_timestamp();

		if ( ! $activated ) {
			return 0;
		}

		return (int) max( 0, floor( ( time() - (int) $activated ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Report a Setup Wizard that finished with a form to build, to import or to go to.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $outcome Wizard outcome: `build`, `import`, `forms` or `exit`.
	 */
	public function maybe_track_wizard( $outcome ): void {

		if ( in_array( (string) $outcome, self::WIZARD_COMPLETED_OUTCOMES, true ) ) {
			$this->track_onboarding( self::FLOW_WIZARD );
		}
	}

	/**
	 * Report the Setup Checklist being completed.
	 *
	 * The "Complete Setup Checklist" link is the only way to dismiss the checklist and it is
	 * offered at any progress, so the dismissal alone is not a completion: only one with
	 * every section done is, the same way the wizard's exit is not.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $progress_percent Completion percentage at the moment of dismissal.
	 */
	public function maybe_track_checklist( $progress_percent ): void {

		if ( (int) $progress_percent < 100 ) {
			return;
		}

		$this->track_onboarding( self::FLOW_CHECKLIST );
	}

	/**
	 * Report the Challenge reaching the completed status.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $replace Parameters written.
	 * @param array $option  Option as it was before the write.
	 */
	public function maybe_track_challenge( $replace, $option ): void {

		$replace = (array) $replace;
		$option  = (array) $option;

		if ( ( $replace['status'] ?? '' ) === 'completed' && ( $option['status'] ?? '' ) !== 'completed' ) {
			$this->track_onboarding( self::FLOW_CHALLENGE );
		}
	}

	/**
	 * Report an onboarding flow, once per flow type for the site.
	 *
	 * Pro relaunches the wizard once after a Lite-to-Pro upgrade, so without the guard every
	 * upgraded site would complete onboarding twice.
	 *
	 * @since 2.0.2.1
	 *
	 * @param string $flow_type Flow type.
	 */
	private function track_onboarding( string $flow_type ): void {

		try {
			$product_events = $this->get_product_events();

			if ( ! $product_events ) {
				return;
			}

			$reported = array_values( array_filter( (array) get_option( self::ONBOARDING_OPTION, [] ), 'is_string' ) );

			if ( in_array( $flow_type, $reported, true ) ) {
				return;
			}

			$reported[] = $flow_type;

			update_option( self::ONBOARDING_OPTION, $reported, false );

			if ( ! $product_events->track( self::EVENT_ONBOARDING, [ 'flow_type' => $flow_type ] ) ) {
				// The next completion retries; a flow type with no event behind it would not.
				update_option( self::ONBOARDING_OPTION, array_values( array_diff( $reported, [ $flow_type ] ) ), false );
			}
		} catch ( Throwable $e ) {
			// This runs inside the wizard's completion request or an AJAX save.
			unset( $e );
		}
	}

	/**
	 * Report a form that was just created.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int   $form_id Form ID.
	 * @param array $form    Form post data.
	 * @param array $data    Creation data.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function maybe_track_form_created( $form_id, $form, $data ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed

		try {
			$form_id        = (int) $form_id;
			$product_events = $form_id > 0 ? $this->get_product_events() : null;

			if ( ! $product_events ) {
				return;
			}

			// Not track(): create_forms is granted to editors through Access Control, and
			// track() would drop their forms without a trace.
			$product_events->track_system(
				self::EVENT_FORM_CREATED,
				[
					'form_id'    => (string) $form_id,
					'built_with' => $this->get_built_with( (array) $data ),
				]
			);
		} catch ( Throwable $e ) {
			// This runs inside the builder's new-form request.
			unset( $e );
		}
	}

	/**
	 * Which of the three ways a form was built.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $data Creation data as handed to wpforms_create_form.
	 *
	 * @return string
	 */
	private function get_built_with( array $data ): string {

		if ( ( $data['source'] ?? '' ) === AiForms::CREATION_SOURCE ) {
			return 'ai';
		}

		$template = (string) ( $data['template'] ?? '' );

		return $template === '' || $template === 'blank' ? 'blank' : 'template';
	}

	/**
	 * Report each provider a form saved in the builder connects for the first time.
	 *
	 * Only the builder's own save is a person connecting a provider. Form::update() also
	 * runs for a duplicate, an import, a template and an addon migration, all of which
	 * carry copied or years-old connections, so those writes are not listened to.
	 *
	 * The connections are read from the saved form, not from the submitted data. The
	 * builder posts a `__lock__` sentinel for every provider panel that was not opened,
	 * which tells the save to keep the stored connections and says nothing about them.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id Form ID.
	 */
	public function maybe_track_integrations( $form_id ): void {

		try {
			$form_id        = (int) $form_id;
			$product_events = $form_id > 0 ? $this->get_product_events() : null;

			if ( ! $product_events ) {
				return;
			}

			$connected = UsageTracking::get_connected_providers( $this->get_saved_providers( $form_id ) );

			if ( ! $connected ) {
				return;
			}

			$reported = $this->get_reported_providers( $form_id );
			$new      = array_diff( $connected, $reported );

			// Saves the meta round trip a same-value update_post_meta() would still make on
			// every later save of a form that keeps its connections.
			if ( ! $new ) {
				return;
			}

			$sent = $this->report_providers( $product_events, $form_id, $new );

			// Nothing sent means nothing to remember; the next save retries every slug.
			if ( $sent ) {
				update_post_meta( $form_id, self::INTEGRATIONS_META, array_values( array_unique( array_merge( $reported, $sent ) ) ) );
			}
		} catch ( Throwable $e ) {
			// This runs inside the builder's save request.
			unset( $e );
		}
	}

	/**
	 * The providers block of a saved form, with the builder's lock sentinels removed.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array Connections keyed by provider slug.
	 */
	private function get_saved_providers( int $form_id ): array {

		$form_handler = wpforms()->obj( 'form' );
		$form         = $form_handler ? $form_handler->get( $form_id, [ 'content_only' => true ] ) : null;
		$providers    = is_array( $form ) ? (array) ( $form['providers'] ?? [] ) : [];

		foreach ( $providers as $slug => $connections ) {
			$connections = (array) $connections;

			// A provider whose lock outlived the save still has no connection behind it.
			unset( $connections['__lock__'] );

			$providers[ $slug ] = $connections;
		}

		return $providers;
	}

	/**
	 * Report each provider and return the slugs the tracker accepted.
	 *
	 * @since 2.0.2.1
	 *
	 * @param ProductEvents $product_events Product events integration, consent already checked.
	 * @param int           $form_id        Form ID.
	 * @param array         $slugs          Provider slugs not reported for this form yet.
	 *
	 * @return array
	 */
	private function report_providers( ProductEvents $product_events, int $form_id, array $slugs ): array {

		$sent = [];

		foreach ( $slugs as $slug ) {
			// Not track(): the builder save is an edit_forms action, which editors hold.
			$accepted = $product_events->track_system(
				self::EVENT_INTEGRATION,
				[
					'addon_slug' => $slug,
					'form_id'    => (string) $form_id,
				]
			);

			if ( $accepted ) {
				$sent[] = $slug;
			}
		}

		return $sent;
	}

	/**
	 * Provider slugs already reported for a form.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_reported_providers( int $form_id ): array {

		$reported = get_post_meta( $form_id, self::INTEGRATIONS_META, true );

		return is_array( $reported ) ? array_values( array_filter( $reported, 'is_string' ) ) : [];
	}

	/**
	 * Whether the option already held this key as a verified license before the write.
	 *
	 * The install hand-over writes the key without a type and the first write of a fresh
	 * site has no previous array at all, so both still count as activations.
	 *
	 * @since 2.0.2.1
	 *
	 * @param mixed  $old_value Previous option value, or the option name on a first write.
	 * @param string $key       Key that was just written.
	 *
	 * @return bool
	 */
	private function was_licensed( $old_value, string $key ): bool {

		if ( ! is_array( $old_value ) || empty( $old_value['type'] ) ) {
			return false;
		}

		return $this->get_license_key( $old_value ) === $key;
	}

	/**
	 * The key a license option value stands for.
	 *
	 * A site that defines WPFORMS_LICENSE_KEY never gets the key written into the option:
	 * the license code validates the constant and stores only the type and the flags. The
	 * option alone would then never describe a valid license, so a previous value with a
	 * type and no key is read as the constant.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $license License option value.
	 *
	 * @return string Empty when the site holds no key at all.
	 */
	private function get_license_key( array $license ): string {

		$key = (string) ( $license['key'] ?? '' );

		return $key !== '' ? $key : (string) wpforms_get_license_key();
	}

	/**
	 * Whether a license option value describes a key the license server accepted.
	 *
	 * Pro's install hand-over writes the key alone and validates afterwards, and a validation
	 * that failed rewrites the option with a flag set. Neither write is an activation.
	 *
	 * @since 2.0.2.1
	 *
	 * @param array $license License option value.
	 *
	 * @return bool
	 */
	private function is_valid_license( array $license ): bool {

		if ( empty( $license['type'] ) || $this->get_license_key( $license ) === '' ) {
			return false;
		}

		foreach ( self::LICENSE_FLAGS as $flag ) {
			if ( ! empty( $license[ $flag ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether Lite was ever activated on this site.
	 *
	 * @since 2.0.2.1
	 *
	 * @return bool
	 */
	private function had_lite(): bool {

		$activated = (array) get_option( 'wpforms_activated', [] );

		return ! empty( $activated['lite'] );
	}

	/**
	 * How many forms the site holds, drafts included, trash excluded.
	 *
	 * @since 2.0.2.1
	 *
	 * @return int
	 */
	private function count_forms(): int {

		$total = 0;

		foreach ( (array) wp_count_posts( 'wpforms' ) as $status => $count ) {
			if ( ! in_array( $status, self::IGNORED_FORM_STATUSES, true ) ) {
				$total += (int) $count;
			}
		}

		return $total;
	}

	/**
	 * How many entries the site holds.
	 *
	 * Usage tracking already answers this for both editions: the entries table on Pro, the
	 * per-form counter meta on Lite, form templates excluded either way.
	 *
	 * @since 2.0.2.1
	 *
	 * @return int
	 */
	private function count_entries(): int {

		$usage_tracking = wpforms()->obj( 'UsageTracking\\UsageTracking' );

		return $usage_tracking instanceof UsageTracking ? $usage_tracking->get_entries_total() : 0;
	}

	/**
	 * Put a count into the sheet's bucket for it.
	 *
	 * @since 2.0.2.1
	 *
	 * @param int   $count   Count.
	 * @param array $buckets Upper bound => label, ascending, the last bound open-ended.
	 *
	 * @return string
	 */
	private function bucket( int $count, array $buckets ): string {

		foreach ( $buckets as $upper_bound => $label ) {
			if ( $count <= $upper_bound ) {
				return $label;
			}
		}

		return (string) end( $buckets );
	}

	/**
	 * Resolve the product events instance, or null when the site has not consented.
	 *
	 * @since 2.0.2.1
	 *
	 * @return ProductEvents|null
	 */
	private function get_product_events(): ?ProductEvents {

		$product_events = wpforms()->obj( 'ProductApi\ProductEvents' );

		return $product_events instanceof ProductEvents && $product_events->is_enabled() ? $product_events : null;
	}
}
