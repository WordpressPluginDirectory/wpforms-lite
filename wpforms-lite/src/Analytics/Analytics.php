<?php

namespace WPForms\Analytics;

/**
 * Form Analytics bootstrap.
 *
 * Form Analytics feature gate and static utilities.
 *
 * @since 2.0.0
 */
class Analytics {

	/**
	 * Documentation URL for the Form Analytics feature.
	 *
	 * Shared by the "Learn More" links in the feature-discovery tooltip and the
	 * analytics page conversion-goal popover.
	 *
	 * @since 2.0.0
	 */
	public const DOC_URL = 'https://wpforms.com/docs/complete-guide-to-form-analytics/';

	/**
	 * Single source of truth for the Analytics kill-switch.
	 *
	 * Wraps the `wpforms_analytics_is_enabled` filter that the Loader uses to
	 * gate the entire feature.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {

		/**
		 * Filter Form Analytics feature availability.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $status Current Form Analytics status.
		 */
		return (bool) apply_filters( 'wpforms_analytics_is_enabled', true );
	}

	/**
	 * Whether the current user's frontend activity should be tracked.
	 *
	 * Excludes site staff — anyone who can publish posts, which in default
	 * WordPress is the Author, Editor, and Administrator roles — so their own
	 * form views and submissions never pollute visitor analytics. Anonymous
	 * visitors and low-privilege roles (Subscriber, Contributor) are always
	 * tracked. The decision is read fresh on every request, so it is immune
	 * to full-page caching.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public static function should_track_user(): bool {

		$is_staff = is_user_logged_in() && current_user_can( 'publish_posts' );

		/**
		 * Filters whether the current user's frontend activity is tracked by Form Analytics.
		 *
		 * Return false to skip tracking for the current user, true to force it. By
		 * default, users who can publish posts (Author, Editor, Administrator) are
		 * excluded; everyone else is tracked.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $should_track Whether to track the current user.
		 */
		return (bool) apply_filters( 'wpforms_analytics_should_track_user', ! $is_staff );
	}
}
