<?php

namespace Tests\LaborBoard\Controllers;

use Neuron\Jobs\Queue\QueueManager;
use Neuron\LaborBoard\Config;
use Neuron\LaborBoard\Controllers\Overview;
use Neuron\LaborBoard\Services\Board;
use Tests\LaborBoard\Fixtures\RecordingJob;

class OverviewTest extends ControllerTestCase
{
	public function testIndexExposesQueuesAndFailedCount(): void
	{
		$this->manager->dispatch( new RecordingJob(), [ 'n' => 1 ], 'mail' );

		$html = $this->controller( Overview::class )->index( $this->request() );

		$this->assertSame( $this->rendered, $html );
		$this->assertSame( 0, $this->viewData['FailedCount'] );
		$mail = array_values( array_filter( $this->viewData['Queues'], fn( $q ) => $q['name'] === 'mail' ) );
		$this->assertSame( 1, $mail[0]['pending'] );
		$this->assertFalse( $this->viewData['HasSchedule'] );
	}

	public function testDispatchRedirectsWithAFlash(): void
	{
		$controller = $this->controller( Overview::class );
		$result = $controller->dispatch( $this->request( [ 'name' => 'missing' ] ) );

		$this->assertSame( '', $result );
		$this->assertNotNull( $controller->redirectedTo() );
	}

	public function testIndexWithAnUnavailableBoard(): void
	{
		$board = new Board( null, new Config(), null, null, null, 'no store' );

		$this->controller( Overview::class, $board )->index( $this->request() );

		$this->assertSame( $board, $this->viewData['Board'] );
	}
}
