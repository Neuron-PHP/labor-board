<?php

namespace Neuron\LaborBoard;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\ISettingSource;
use Neuron\Patterns\Registry;

/**
 * The `labor_board:` section of the host application's settings, with
 * defaults for everything so the package works with no configuration.
 *
 *   labor_board:
 *     enabled: true
 *     auth_filter: admin      # host filter run before every page; null = none
 *     role: admin             # AUTH_USER_ROLE required after auth; null = any
 *     layout: auto            # auto | admin | labor-board | <layout name>
 *     page_size: 50
 *     refresh_seconds: 10     # 0 disables auto-refresh
 *     stale_after: 90         # defaults to queue.retry_after
 *     warn_pending: 100
 *     alert_pending: 1000
 *     warn_oldest_age: 300
 *
 * @package Neuron\LaborBoard
 */
final class Config
{
	public const SECTION = 'labor_board';
	public const LAYOUT_AUTO = 'auto';
	public const LAYOUT_FALLBACK = 'labor-board';
	public const LAYOUT_ADMIN = 'admin';

	private const FALSE_VALUES = [ false, 0, '0', 'false', 'off', 'no' ];

	public function __construct(
		public readonly bool $enabled = true,
		public readonly ?string $authFilter = 'admin',
		/** @var list<string> empty = no role check */
		public readonly array $roles = [ 'admin' ],
		public readonly string $layout = self::LAYOUT_AUTO,
		public readonly int $pageSize = 50,
		public readonly int $refreshSeconds = 10,
		public readonly ?int $staleAfter = null,
		public readonly int $warnPending = 100,
		public readonly int $alertPending = 1000,
		public readonly int $warnOldestAge = 300
	)
	{
	}

	/**
	 * Build from a settings source; missing keys take the defaults above.
	 *
	 * `auth_filter` and `role` distinguish "not set" (default) from an
	 * explicit null / "none" / empty (feature off).
	 *
	 * @param ISettingSource|null $settings
	 * @return self
	 */
	public static function fromSettings( ?ISettingSource $settings ): self
	{
		if( $settings === null )
		{
			return new self();
		}

		$get = static fn( string $name ): mixed => $settings->get( self::SECTION, $name );

		$defaults = new self();

		$authFilter = $defaults->authFilter;
		$roles = $defaults->roles;

		if( self::isSet( $settings, 'auth_filter' ) )
		{
			$authFilter = self::nullableName( $get( 'auth_filter' ) );
		}

		if( self::isSet( $settings, 'role' ) )
		{
			$roles = self::roleList( $get( 'role' ) );
		}

		$staleAfter = $get( 'stale_after' );

		return new self(
			enabled: !in_array( $get( 'enabled' ), self::FALSE_VALUES, true ),
			authFilter: $authFilter,
			roles: $roles,
			layout: self::stringOr( $get( 'layout' ), $defaults->layout ),
			pageSize: max( 1, self::intOr( $get( 'page_size' ), $defaults->pageSize ) ),
			refreshSeconds: max( 0, self::intOr( $get( 'refresh_seconds' ), $defaults->refreshSeconds ) ),
			staleAfter: is_numeric( $staleAfter ) ? max( 1, (int)$staleAfter ) : null,
			warnPending: max( 0, self::intOr( $get( 'warn_pending' ), $defaults->warnPending ) ),
			alertPending: max( 0, self::intOr( $get( 'alert_pending' ), $defaults->alertPending ) ),
			warnOldestAge: max( 0, self::intOr( $get( 'warn_oldest_age' ), $defaults->warnOldestAge ) )
		);
	}

	/**
	 * Build from whatever settings the running application put in the Registry.
	 *
	 * @return self
	 */
	public static function fromRegistry(): self
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		return self::fromSettings( $settings instanceof ISettingSource ? $settings : null );
	}

	/**
	 * Whether a role check applies at all.
	 */
	public function requiresRole(): bool
	{
		return $this->roles !== [];
	}

	/**
	 * Whether the given role satisfies the role gate.
	 */
	public function allowsRole( ?string $role ): bool
	{
		return !$this->requiresRole() || ( $role !== null && in_array( $role, $this->roles, true ) );
	}

	/**
	 * Whether a settings key exists in our section (so an explicit null can
	 * be told apart from "not configured").
	 */
	private static function isSet( ISettingSource $settings, string $name ): bool
	{
		try
		{
			$names = $settings->getSectionSettingNames( self::SECTION );
		}
		catch( \Throwable )
		{
			return $settings->get( self::SECTION, $name ) !== null;
		}

		return is_array( $names ) ? in_array( $name, $names, true ) : $settings->get( self::SECTION, $name ) !== null;
	}

	private static function nullableName( mixed $value ): ?string
	{
		if( $value === null || $value === false || in_array( $value, [ '', 'none', 'null', '~' ], true ) )
		{
			return null;
		}

		return (string)$value;
	}

	/**
	 * @return list<string>
	 */
	private static function roleList( mixed $value ): array
	{
		if( is_array( $value ) )
		{
			return array_values( array_filter( array_map( 'strval', $value ), fn( $r ) => $r !== '' ) );
		}

		$name = self::nullableName( $value );

		if( $name === null )
		{
			return [];
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $name ) ), fn( $r ) => $r !== '' ) );
	}

	private static function stringOr( mixed $value, string $default ): string
	{
		return is_string( $value ) && $value !== '' ? $value : $default;
	}

	private static function intOr( mixed $value, int $default ): int
	{
		return is_numeric( $value ) ? (int)$value : $default;
	}
}
