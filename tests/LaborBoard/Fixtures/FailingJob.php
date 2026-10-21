<?php

namespace Tests\LaborBoard\Fixtures;

use Neuron\Jobs\IJob;

/**
 * Throws so a worker records a failed job.
 */
class FailingJob implements IJob
{
	public function getName(): string
	{
		return 'failing';
	}

	public function run( array $argv = [] ): mixed
	{
		throw new \RuntimeException( 'intentional failure' );
	}
}
