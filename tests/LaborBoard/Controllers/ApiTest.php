<?php

namespace Tests\LaborBoard\Controllers;

use Neuron\LaborBoard\Controllers\Api;

class ApiTest extends ControllerTestCase
{
	public function testStatsReturnsJson(): void
	{
		$json = $this->controller( Api::class )->stats( $this->request() );
		$data = json_decode( $json, true );

		$this->assertIsArray( $data );
		$this->assertTrue( $data['available'] );
		$this->assertSame( 'array', $data['driver'] );
		$this->assertArrayHasKey( 'queues', $data );
		$this->assertArrayHasKey( 'failed', $data );
	}
}
