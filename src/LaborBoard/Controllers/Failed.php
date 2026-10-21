<?php

namespace Neuron\LaborBoard\Controllers;

use Neuron\Core\Exceptions\NotFound;
use Neuron\LaborBoard\Filters\DelegatingAuthFilter;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Failed jobs: inspect the exception, retry, forget.
 *
 * @package Neuron\LaborBoard\Controllers
 */
#[RouteGroup( prefix: '/admin/queue', filters: [ DelegatingAuthFilter::NAME ] )]
class Failed extends Base
{
	#[Get( '/failed', name: 'labor_board_failed' )]
	public function index( Request $request ): string
	{
		if( ( $redirect = $this->requireInspection() ) !== null )
		{
			return $redirect;
		}

		$queue = trim( (string)$request->get( 'queue', '' ) );
		$queue = $queue === '' ? null : $queue;
		$page = max( 1, (int)$request->get( 'page', 1 ) );

		$board = $this->board();

		return $this->page( 'index', [
			'QueueFilter' => $queue,
			'FailedQueues' => $board->failedQueues(),
			'Failed' => $board->failed( $queue, $page ),
		], 'Failed Jobs' );
	}

	#[Get( '/failed/:id', name: 'labor_board_failed_show' )]
	public function show( Request $request ): string
	{
		if( ( $redirect = $this->requireInspection() ) !== null )
		{
			return $redirect;
		}

		$id = (string)$request->getRouteParameter( 'id' );
		$job = $this->board()->failedJob( $id );

		if( $job === null )
		{
			throw new NotFound( "Failed job {$id} not found." );
		}

		return $this->page( 'show', [ 'Job' => $job ], "Failed: {$job['short_class']}" );
	}

	#[Post( '/failed/:id/retry', name: 'labor_board_failed_retry', filters: [ 'csrf' ] )]
	public function retry( Request $request ): string
	{
		$id = (string)$request->getRouteParameter( 'id' );

		return $this->board()->retry( $id )
			? $this->redirect( 'labor_board_failed', [], "Failed job {$id} queued again." )
			: $this->redirect( 'labor_board_failed', [], null, "Failed job {$id} not found." );
	}

	#[Post( '/failed/retry-all', name: 'labor_board_failed_retry_all', filters: [ 'csrf' ] )]
	public function retryAll( Request $request ): string
	{
		$count = $this->board()->retryAll();

		return $this->redirect( 'labor_board_failed', [], "Queued {$count} failed job(s) again." );
	}

	#[Delete( '/failed/:id', name: 'labor_board_failed_forget', filters: [ 'csrf' ] )]
	public function forget( Request $request ): string
	{
		$id = (string)$request->getRouteParameter( 'id' );

		return $this->board()->forget( $id )
			? $this->redirect( 'labor_board_failed', [], "Failed job {$id} forgotten." )
			: $this->redirect( 'labor_board_failed', [], null, "Failed job {$id} not found." );
	}

	#[Delete( '/failed', name: 'labor_board_failed_clear', filters: [ 'csrf' ] )]
	public function clear( Request $request ): string
	{
		$count = $this->board()->clearFailed();

		return $this->redirect( 'labor_board_failed', [], "Cleared {$count} failed job(s)." );
	}
}
