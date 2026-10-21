<?php

namespace Tests\LaborBoard\Fixtures;

use Neuron\Jobs\IJob;
use Neuron\Jobs\Queue\IUniqueJob;

/**
 * Deduplicated by drivers that implement IUniqueQueue.
 */
class UniqueJob implements IJob, IUniqueJob
{
	public function getName(): string
	{
		return 'unique';
	}

	public function run( array $argv = [] ): mixed
	{
		return true;
	}
}
