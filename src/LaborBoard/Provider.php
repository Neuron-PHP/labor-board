<?php

namespace Neuron\LaborBoard;

use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\Log\Log;
use Neuron\Mvc\Components\IComponentProvider;
use Neuron\Mvc\Components\INavigationProvider;
use Neuron\Mvc\IMvcApplication;

/**
 * Web component entry point, discovered through composer.json
 * `extra.neuron.mvc-provider`.
 *
 * With `labor_board.enabled: false` the component declares nothing: no
 * routes, no views, no navigation.
 *
 * @package Neuron\LaborBoard
 */
class Provider implements IComponentProvider, INavigationProvider
{
	public const ROUTE_OVERVIEW = 'labor_board';

	/**
	 * @inheritDoc
	 */
	public static function controllerPaths(): array
	{
		if( !Config::fromRegistry()->enabled )
		{
			return [];
		}

		return [
			[ 'path' => __DIR__ . '/Controllers', 'namespace' => __NAMESPACE__ . '\\Controllers' ],
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function viewPaths(): array
	{
		if( !Config::fromRegistry()->enabled )
		{
			return [];
		}

		return [ self::resourcePath( 'views' ) ];
	}

	/**
	 * @inheritDoc
	 */
	public static function register( IMvcApplication $app ): void
	{
		$config = Config::fromRegistry();

		if( !$config->enabled )
		{
			return;
		}

		if( $config->authFilter === null )
		{
			Log::warning( 'Labor Board: labor_board.auth_filter is null; the queue dashboard runs without authentication.' );
		}

		$app->getRouter()->registerFilter( DelegatingAuthFilter::NAME, new DelegatingAuthFilter( $app->getRouter(), $config ) );
	}

	/**
	 * @inheritDoc
	 */
	public static function navigation(): array
	{
		if( !Config::fromRegistry()->enabled )
		{
			return [];
		}

		return [
			[ 'group' => 'system', 'label' => 'Queue', 'route' => self::ROUTE_OVERVIEW, 'icon' => 'bi-stack', 'order' => 20 ],
		];
	}

	/**
	 * Absolute path under the package's resources directory.
	 */
	public static function resourcePath( string $relative = '' ): string
	{
		$base = dirname( __DIR__, 2 ) . '/resources';

		return $relative === '' ? $base : $base . '/' . ltrim( $relative, '/' );
	}
}
