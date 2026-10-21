<?php

namespace Tests\LaborBoard\Controllers;

use Neuron\Core\Exceptions\NotFound;
use Neuron\LaborBoard\Controllers\Failed;
use Tests\LaborBoard\Fixtures\RecordingJob;

class FailedTest extends ControllerTestCase
{
	private function failOne(): string
	{
		$this->manager->dispatch( new RecordingJob(), [ 'n' => 1 ] );
		$job = $this->manager->getDriver()->pop( 'default' );
		$this->manager->getDriver()->failed( $job, new \RuntimeException( 'boom' ) );

		return $job->getId();
	}

	public function testIndexAndShow(): void
	{
		$id = $this->failOne();

		$this->controller( Failed::class )->index( $this->request() );

		$this->assertSame( 1, $this->viewData['Failed']['total'] );
		$this->assertNull( $this->viewData['QueueFilter'] );

		$this->viewData = [];
		$this->controller( Failed::class )->show( $this->request( [ 'id' => $id ] ) );
		$this->assertSame( $id, $this->viewData['Job']['id'] );
	}

	public function testShowMissingIsNotFound(): void
	{
		$this->expectException( NotFound::class );

		$this->controller( Failed::class )->show( $this->request( [ 'id' => 'missing' ] ) );
	}

	public function testRetryAndForget(): void
	{
		$id = $this->failOne();
		$controller = $this->controller( Failed::class );

		$controller->retry( $this->request( [ 'id' => $id ] ) );
		$this->assertSame( 0, $this->board()->failedCount() );

		$this->failOne();
		$controller->forget( $this->request( [ 'id' => $this->board()->failed()['rows'][0]['id'] ] ) );
		$this->assertSame( 0, $this->board()->failedCount() );
	}

	public function testRetryAllAndClear(): void
	{
		$this->failOne();
		$controller = $this->controller( Failed::class );

		$controller->retryAll( $this->request() );
		$this->assertSame( 0, $this->board()->failedCount() );

		$this->failOne();
		$controller->clear( $this->request() );
		$this->assertSame( 0, $this->board()->failedCount() );
	}
}
