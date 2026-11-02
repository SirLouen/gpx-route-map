<?php
/**
 * A stand-in for the locks of WP_Upgrader, as its own file loads the whole of
 * WordPress's updater. Required by bootstrap.php.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Upgrader' ) ) {
	/**
	 * Keeps which locks are held, with how long each may be held for.
	 */
	class WP_Upgrader {

		/**
		 * The locks held.
		 *
		 * @var array<string, int|null>
		 */
		public static $locks = array();

		/**
		 * Take a lock, if it is free.
		 *
		 * @param string   $lock_name       The lock.
		 * @param int|null $release_timeout How long it may be held for.
		 * @return bool
		 */
		public static function create_lock( string $lock_name, ?int $release_timeout = null ): bool {
			if ( array_key_exists( $lock_name, self::$locks ) ) {
				return false;
			}

			self::$locks[ $lock_name ] = $release_timeout;
			return true;
		}

		/**
		 * Let go of a lock.
		 *
		 * @param string $lock_name The lock.
		 * @return bool
		 */
		public static function release_lock( string $lock_name ): bool {
			unset( self::$locks[ $lock_name ] );
			return true;
		}
	}
}
