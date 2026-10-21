<?php

namespace Neuron\LaborBoard\Controllers;

use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\LaborBoard\Provider;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * The dashboard: per-queue counts, failed total, upcoming schedule.
 *
 * @package Neuron\LaborBoard\Controllers
 */
#[RouteGroup( prefix: '/admin/queue', filters: [ DelegatingAuthFilter::NAME ] )]
class Overview extends Base
{
	#[Get( '/', name: Provider::ROUTE_OVERVIEW )]
	public function index( Request $request ): string
	{
		$board = $this->board();

		return $this->page( 'index', [
			'Queues' => $board->queues(),
			'FailedCount' => $board->failedCount(),
			'Upcoming' => $board->upcoming( 5 ),
			'HasSchedule' => $board->hasSchedule(),
			'LastTriggeredAvailable' => $board->lastTriggeredAvailable(),
		], 'Queue' );
	}

	#[Post( '/schedule/:name/dispatch', name: 'labor_board_schedule_dispatch', filters: [ 'csrf' ] )]
	public function dispatch( Request $request ): string
	{
		$name = (string)$request->getRouteParameter( 'name' );
		$result = $this->board()->dispatchScheduled( $name );

		return $result['ok']
			? $this->redirect( Provider::ROUTE_OVERVIEW, [], $result['message'] )
			: $this->redirect( Provider::ROUTE_OVERVIEW, [], null, $result['message'] );
	}
}
