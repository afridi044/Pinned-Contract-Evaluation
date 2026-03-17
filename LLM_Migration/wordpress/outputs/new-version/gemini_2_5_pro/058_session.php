<?php

declare(strict_types=1);

/**
 * Abstract class for managing user session tokens.
 *
 * @since 4.0.0
 */
abstract class WP_Session_Tokens
{
	/**
	 * Protected constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param int $user_id User whose session to manage.
	 */
	protected function __construct(protected readonly int $user_id)
	{
	}

	/**
	 * Get a session token manager instance for a user.
	 *
	 * This method contains a filter that allows a plugin to swap out
	 * the session manager for a subclass of WP_Session_Tokens.
	 *
	 * @since 4.0.0
	 *
	 * @param int $user_id User whose session to manage.
	 * @return static An instance of a WP_Session_Tokens subclass.
	 */
	final public static function get_instance(int $user_id): static
	{
		/**
		 * Filter the session token manager used.
		 *
		 * @since 4.0.0
		 *
		 * @param class-string<WP_Session_Tokens> $session Name of class to use as the manager.
		 *                                                 Default 'WP_User_Meta_Session_Tokens'.
		 */
		$manager = apply_filters('session_token_manager', 'WP_User_Meta_Session_Tokens');
		return new $manager($user_id);
	}

	/**
	 * Hashes a session token for storage.
	 *
	 * @since 4.0.0
	 *
	 * @param string $token Session token to hash.
	 * @return string A hash of the session token (a verifier).
	 */
	final private function hash_token(string $token): string
	{
		return hash('sha256', $token);
	}

	/**
	 * Get a user's session.
	 *
	 * @since 4.0.0
	 *
	 * @param string $token Session token.
	 * @return array<string, mixed>|null User session, or null if it does not exist.
	 */
	final public function get(string $token): ?array
	{
		$verifier = $this->hash_token($token);
		return $this->get_session($verifier);
	}

	/**
	 * Validate a user's session token as authentic.
	 *
	 * Checks that the given token is present and hasn't expired.
	 *
	 * @since 4.0.0
	 *
	 * @param string $token Token to verify.
	 * @return bool Whether the token is valid for the user.
	 */
	final public function verify(string $token): bool
	{
		$verifier = $this->hash_token($token);
		return (bool) $this->get_session($verifier);
	}

	/**
	 * Generate a session token and attach session information to it.
	 *
	 * A session token is a long, random string. It is used in a cookie
	 * link that cookie to an expiration time and to ensure the cookie
	 * becomes invalidated upon logout.
	 *
	 * This function generates a token and stores it with the associated
	 * expiration time (and potentially other session information via the
	 * `attach_session_information` filter).
	 *
	 * @since 4.0.0
	 *
	 * @param int $expiration Session expiration timestamp.
	 * @return string Session token.
	 */
	final public function create(int $expiration): string
	{
		/**
		 * Filter the information attached to the newly created session.
		 *
		 * Could be used in the future to attach information such as
		 * IP address or user agent to a session.
		 *
		 * @since 4.0.0
		 *
		 * @param array<string, mixed> $session Array of extra data.
		 * @param int                  $user_id User ID.
		 */
		$session = apply_filters('attach_session_information', [], $this->user_id);
		$session['expiration'] = $expiration;

		$token = wp_generate_password(43, false, false);

		$this->update($token, $session);

		return $token;
	}

	/**
	 * Update a session token.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $token   Session token to update.
	 * @param array<string, mixed> $session Session information.
	 */
	final public function update(string $token, array $session): void
	{
		$verifier = $this->hash_token($token);
		$this->update_session($verifier, $session);
	}

	/**
	 * Destroy a session token.
	 *
	 * @since 4.0.0
	 *
	 * @param string $token Session token to destroy.
	 */
	final public function destroy(string $token): void
	{
		$verifier = $this->hash_token($token);
		$this->update_session($verifier, null);
	}

	/**
	 * Destroy all session tokens for this user,
	 * except a single token, presumably the one in use.
	 *
	 * @since 4.0.0
	 *
	 * @param string $token_to_keep Session token to keep.
	 */
	final public function destroy_others(string $token_to_keep): void
	{
		$verifier = $this->hash_token($token_to_keep);
		$session = $this->get_session($verifier);
		if ($session) {
			$this->destroy_other_sessions($verifier);
		} else {
			$this->destroy_all_sessions();
		}
	}

	/**
	 * Determine whether a session token is still valid,
	 * based on expiration.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $session Session to check.
	 * @return bool Whether session is valid.
	 */
	final protected function is_still_valid(array $session): bool
	{
		return ($session['expiration'] ?? 0) >= time();
	}

	/**
	 * Destroy all session tokens for a user.
	 *
	 * @since 4.0.0
	 */
	final public function destroy_all(): void
	{
		$this->destroy_all_sessions();
	}

	/**
	 * Destroy all session tokens for all users.
	 *
	 * @since 4.0.0
	 */
	final public static function destroy_all_for_all_users(): void
	{
		/**
		 * Filter the session token manager used.
		 *
		 * @since 4.0.0
		 *
		 * @param class-string<WP_Session_Tokens> $session Name of class to use as the manager.
		 *                                                 Default 'WP_User_Meta_Session_Tokens'.
		 */
		$manager = apply_filters('session_token_manager', 'WP_User_Meta_Session_Tokens');
		$manager::drop_sessions();
	}

	/**
	 * Retrieve all sessions of a user.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>> Sessions of a user.
	 */
	final public function get_all(): array
	{
		return array_values($this->get_sessions());
	}

	/**
	 * This method should retrieve all sessions of a user, keyed by verifier.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, mixed>> Sessions of a user, keyed by verifier.
	 */
	abstract protected function get_sessions(): array;

	/**
	 * This method should look up a session by its verifier (token hash).
	 *
	 * @since 4.0.0
	 *
	 * @param string $verifier Verifier of the session to retrieve.
	 * @return array<string, mixed>|null The session, or null if it does not exist.
	 */
	abstract protected function get_session(string $verifier): ?array;

	/**
	 * This method should update a session by its verifier.
	 *
	 * Omitting the second argument should destroy the session.
	 *
	 * @since 4.0.0
	 *
	 * @param string                     $verifier Verifier of the session to update.
	 * @param array<string, mixed>|null  $session  The session data. Null to destroy the session.
	 */
	abstract protected function update_session(string $verifier, ?array $session = null): void;

	/**
	 * This method should destroy all session tokens for this user,
	 * except a single session passed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $verifier Verifier of the session to keep.
	 */
	abstract protected function destroy_other_sessions(string $verifier): void;

	/**
	 * This method should destroy all sessions for a user.
	 *
	 * @since 4.0.0
	 */
	abstract protected function destroy_all_sessions(): void;

	/**
	 * This static method should destroy all session tokens for all users.
	 *
	 * @since 4.0.0
	 */
	public static function drop_sessions(): void
	{
	}
}

/**
 * Meta-based user sessions token manager.
 *
 * @since 4.0.0
 */
class WP_User_Meta_Session_Tokens extends WP_Session_Tokens
{
	/**
	 * Get all sessions of a user.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, mixed>> Sessions of a user.
	 */
	#[Override]
	protected function get_sessions(): array
	{
		$sessions = get_user_meta($this->user_id, 'session_tokens', true);

		if (!is_array($sessions)) {
			return [];
		}

		$sessions = array_map([$this, 'prepare_session'], $sessions);
		return array_filter($sessions, [$this, 'is_still_valid']);
	}

	/**
	 * Converts an expiration to an array of session information.
	 *
	 * @param mixed $session Session or expiration.
	 * @return array<string, mixed> Session.
	 */
	protected function prepare_session(mixed $session): array
	{
		if (is_int($session)) {
			return ['expiration' => $session];
		}

		return $session;
	}

	/**
	 * Retrieve a session by its verifier (token hash).
	 *
	 * @since 4.0.0
	 *
	 * @param string $verifier Verifier of the session to retrieve.
	 * @return array<string, mixed>|null The session, or null if it does not exist.
	 */
	#[Override]
	protected function get_session(string $verifier): ?array
	{
		$sessions = $this->get_sessions();
		return $sessions[$verifier] ?? null;
	}

	/**
	 * Update a session by its verifier.
	 *
	 * @since 4.0.0
	 *
	 * @param string                     $verifier Verifier of the session to update.
	 * @param array<string, mixed>|null  $session  Optional. Session. Omitting this argument destroys the session.
	 */
	#[Override]
	protected function update_session(string $verifier, ?array $session = null): void
	{
		$sessions = $this->get_sessions();

		if ($session) {
			$sessions[$verifier] = $session;
		} else {
			unset($sessions[$verifier]);
		}

		$this->update_sessions($sessions);
	}

	/**
	 * Update a user's sessions in the usermeta table.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, array<string, mixed>> $sessions Sessions.
	 */
	protected function update_sessions(array $sessions): void
	{
		if (!has_filter('attach_session_information')) {
			$sessions = wp_list_pluck($sessions, 'expiration');
		}

		if ($sessions) {
			update_user_meta($this->user_id, 'session_tokens', $sessions);
		} else {
			delete_user_meta($this->user_id, 'session_tokens');
		}
	}

	/**
	 * Destroy all session tokens for a user, except a single session passed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $verifier Verifier of the session to keep.
	 */
	#[Override]
	protected function destroy_other_sessions(string $verifier): void
	{
		$session = $this->get_session($verifier);
		// The parent's final destroy_others() method ensures $session is not null here.
		$this->update_sessions([$verifier => $session]);
	}

	/**
	 * Destroy all session tokens for a user.
	 *
	 * @since 4.0.0
	 */
	#[Override]
	protected function destroy_all_sessions(): void
	{
		$this->update_sessions([]);
	}

	/**
	 * Destroy all session tokens for all users.
	 *
	 * @since 4.0.0
	 */
	#[Override]
	public static function drop_sessions(): void
	{
		delete_metadata('user', 0, 'session_tokens', false, true);
	}
}