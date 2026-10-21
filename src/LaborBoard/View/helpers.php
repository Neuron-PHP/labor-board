<?php

/**
 * View helpers for Labor Board templates.
 *
 * Guarded with function_exists so a host that already defines these (the
 * CMS defines route_path() and csrf_field()) keeps its own; the
 * implementations are equivalent.
 */

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\Helpers\UrlHelper;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;

if( !function_exists( 'route_path' ) )
{
	/**
	 * Relative URL for a named route, or '' when the route is unknown.
	 */
	function route_path( string $routeName, array $parameters = [] ): string
	{
		$app = Registry::getInstance()->get( RegistryKeys::APP );

		if( !$app instanceof IMvcApplication )
		{
			return '';
		}

		return ( new UrlHelper( $app->getRouter() ) )->routePath( $routeName, $parameters ) ?? '';
	}
}

if( !function_exists( 'csrf_field' ) )
{
	/**
	 * Hidden input carrying the CSRF token MVC's `csrf` filter expects.
	 */
	function csrf_field(): string
	{
		$token = Registry::getInstance()->get( RegistryKeys::AUTH_CSRF_TOKEN );

		return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars( (string)$token ) . '">';
	}
}

if( !function_exists( 'labor_board_duration' ) )
{
	/**
	 * Compact human duration: 42s, 3m 10s, 2h 5m, 3d 4h.
	 */
	function labor_board_duration( ?int $seconds ): string
	{
		if( $seconds === null )
		{
			return '—';
		}

		$seconds = max( 0, $seconds );

		if( $seconds < 60 )
		{
			return $seconds . 's';
		}

		$units = [ 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1 ];
		$parts = [];

		foreach( $units as $label => $size )
		{
			if( $seconds >= $size )
			{
				$parts[] = intdiv( $seconds, $size ) . $label;
				$seconds %= $size;
			}

			if( count( $parts ) === 2 )
			{
				break;
			}
		}

		return implode( ' ', $parts );
	}
}

if( !function_exists( 'labor_board_time' ) )
{
	/**
	 * Timestamp as Y-m-d H:i:s, or a dash.
	 */
	function labor_board_time( ?int $timestamp ): string
	{
		return $timestamp === null ? '—' : date( 'Y-m-d H:i:s', $timestamp );
	}
}
