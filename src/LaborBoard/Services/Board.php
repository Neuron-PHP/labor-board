<?php

namespace Neuron\LaborBoard\Services;

use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\Source\ISettingSource;
use Neuron\Jobs\IJob;
use Neuron\Jobs\Queue\DatabaseQueue;
use Neuron\Jobs\Queue\IUniqueJob;
use Neuron\Jobs\Queue\IUniqueQueue;
use Neuron\Jobs\Queue\JobState;
use Neuron\Jobs\Queue\QueuedJob;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\Jobs\Schedule\DatabaseScheduleLock;
use Neuron\Jobs\Schedule\ScheduleEntry;
use Neuron\Jobs\Schedule\ScheduleFile;
use Neuron\LaborBoard\Config;
use Neuron\Log\Log;
use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Registry;

/**
 * Everything the pages need, in one place: queue statistics with
 * thresholds applied, paged job and failed-job listings as plain arrays,
 * the job actions, and the schedule strip.
 *
 * A Board is built once per request. If the QueueManager cannot be
 * constructed (unreachable store, bad config) the Board still exists with
 * `error()` set, so the overview can say so instead of a 500.
 *
 * @package Neuron\LaborBoard\Services
 */
class Board
{
	public const LEVEL_OK = 'ok';
	public const LEVEL_WARN = 'warn';
	public const LEVEL_ALERT = 'alert';

	private ?QueueManager $_manager;
	private Config $_config;
	private ?ScheduleFile $_schedule;
	private ?ISettingSource $_settings;
	private ?IContainer $_container;
	private ?string $_error;
	private int $_now;

	/**
	 * @param QueueManager|null $manager Null when construction failed; pass $error
	 * @param Config $config
	 * @param ScheduleFile|null $schedule
	 * @param ISettingSource|null $settings For queue.schedule_lock
	 * @param IContainer|null $container Used to build job instances for Dispatch now
	 * @param string|null $error
	 * @param int|null $now Clock override for tests
	 */
	public function __construct(
		?QueueManager $manager,
		Config $config,
		?ScheduleFile $schedule = null,
		?ISettingSource $settings = null,
		?IContainer $container = null,
		?string $error = null,
		?int $now = null
	)
	{
		$this->_manager = $manager;
		$this->_config = $config;
		$this->_schedule = $schedule;
		$this->_settings = $settings;
		$this->_container = $container;
		$this->_error = $error;
		$this->_now = $now ?? time();
	}

	/**
	 * Build from the running application's Registry state.
	 *
	 * @param Config $config
	 * @param string|null $basePath For schedule.yaml; null skips the schedule
	 * @param IContainer|null $container
	 * @return self
	 */
	public static function fromRegistry( Config $config, ?string $basePath = null, ?IContainer $container = null ): self
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );
		$settings = $settings instanceof ISettingSource ? $settings : null;

		$schedule = $basePath !== null && $basePath !== '' ? ScheduleFile::fromBasePath( $basePath ) : null;

		try
		{
			$manager = QueueManager::fromRegistry();
		}
		catch( \Throwable $e )
		{
			Log::error( 'Labor Board: queue unavailable: ' . $e->getMessage() );

			return new self( null, $config, $schedule, $settings, $container, $e->getMessage() );
		}

		return new self( $manager, $config, $schedule, $settings, $container );
	}

	// -- status -----------------------------------------------------------

	public function config(): Config
	{
		return $this->_config;
	}

	/**
	 * Why the queue is unavailable, or null when it is fine.
	 */
	public function error(): ?string
	{
		return $this->_error;
	}

	public function isAvailable(): bool
	{
		return $this->_manager !== null;
	}

	public function driverName(): string
	{
		return $this->_manager?->getDriverName() ?? 'unknown';
	}

	public function isSync(): bool
	{
		return $this->driverName() === 'sync';
	}

	public function supportsInspection(): bool
	{
		return $this->_manager?->supportsInspection() ?? false;
	}

	public function defaultQueue(): string
	{
		return $this->_manager?->getDefaultQueue() ?? 'default';
	}

	/**
	 * Seconds after which a reserved job is considered abandoned.
	 */
	public function retryAfter(): int
	{
		return (int)( $this->_manager?->getConfig()['retry_after'] ?? 90 );
	}

	/**
	 * Reserved longer than this is flagged stale.
	 */
	public function staleAfter(): int
	{
		return $this->_config->staleAfter ?? $this->retryAfter();
	}

	// -- overview ---------------------------------------------------------

	/**
	 * One row per queue with thresholds applied.
	 *
	 * @return list<array{name:string,pending:int,delayed:?int,reserved:?int,oldest_pending_age:?int,level:string}>
	 */
	public function queues(): array
	{
		if( $this->_manager === null )
		{
			return [];
		}

		$names = $this->_manager->getQueueNames();

		if( $names === [] )
		{
			$names = [ $this->defaultQueue() ];
		}

		sort( $names );

		$rows = [];

		foreach( $names as $name )
		{
			$stats = $this->_manager->getStats( $name );

			$rows[] = [
				'name' => $name,
				'pending' => (int)( $stats['pending'] ?? 0 ),
				'delayed' => isset( $stats['delayed'] ) ? (int)$stats['delayed'] : null,
				'reserved' => isset( $stats['reserved'] ) ? (int)$stats['reserved'] : null,
				'oldest_pending_age' => isset( $stats['oldest_pending_age'] ) ? (int)$stats['oldest_pending_age'] : null,
				'level' => $this->level( (int)( $stats['pending'] ?? 0 ), $stats['oldest_pending_age'] ?? null ),
			];
		}

		return $rows;
	}

	/**
	 * Threshold colour for a queue.
	 */
	public function level( int $pending, ?int $oldestAge ): string
	{
		if( $this->_config->alertPending > 0 && $pending >= $this->_config->alertPending )
		{
			return self::LEVEL_ALERT;
		}

		if( $this->_config->warnPending > 0 && $pending >= $this->_config->warnPending )
		{
			return self::LEVEL_WARN;
		}

		if( $oldestAge !== null && $this->_config->warnOldestAge > 0 && $oldestAge >= $this->_config->warnOldestAge )
		{
			return self::LEVEL_WARN;
		}

		return self::LEVEL_OK;
	}

	public function failedCount( ?string $queue = null ): int
	{
		if( $this->_manager === null )
		{
			return 0;
		}

		try
		{
			return $this->_manager->countFailedJobs( $queue );
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Labor Board: failed count unavailable: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Everything the auto-refresh endpoint returns.
	 */
	public function stats(): array
	{
		return [
			'available' => $this->isAvailable(),
			'error' => $this->_error,
			'driver' => $this->driverName(),
			'inspectable' => $this->supportsInspection(),
			'retry_after' => $this->retryAfter(),
			'queues' => $this->queues(),
			'failed' => $this->failedCount(),
			'generated_at' => $this->_now,
		];
	}

	// -- jobs -------------------------------------------------------------

	/**
	 * Counts for the three tabs on a queue page.
	 *
	 * @return array<string,int> state value => count
	 */
	public function stateCounts( string $queue ): array
	{
		$counts = [];

		foreach( JobState::cases() as $state )
		{
			$counts[ $state->value ] = $this->supportsInspection() ? $this->_manager->countJobs( $queue, $state ) : 0;
		}

		return $counts;
	}

	/**
	 * A page of jobs in one state, oldest-first as a worker would see them.
	 *
	 * @return array{rows:list<array>,total:int,page:int,pages:int,page_size:int}
	 */
	public function jobs( string $queue, JobState $state, int $page = 1 ): array
	{
		if( !$this->supportsInspection() )
		{
			return $this->emptyPage( $page );
		}

		$total = $this->_manager->countJobs( $queue, $state );
		$paging = $this->paging( $total, $page );

		$rows = array_map(
			fn( QueuedJob $job ) => $this->jobRow( $job ),
			$this->_manager->listJobs( $queue, $state, $paging['page_size'], $paging['offset'] )
		);

		return [ 'rows' => $rows ] + $paging;
	}

	/**
	 * Full detail for one job, or null when it is gone.
	 */
	public function job( string $id ): ?array
	{
		if( !$this->supportsInspection() )
		{
			return null;
		}

		$job = $this->_manager->findJob( $id );

		return $job === null ? null : $this->jobRow( $job, true );
	}

	/**
	 * @return bool False when the job is missing or reserved
	 */
	public function deleteJob( string $id ): bool
	{
		return $this->supportsInspection() && $this->_manager->deleteJob( $id );
	}

	/**
	 * Make a delayed job available immediately.
	 */
	public function releaseNow( string $id ): bool
	{
		return $this->supportsInspection() && $this->_manager->releaseNow( $id );
	}

	/**
	 * @return int Jobs removed
	 */
	public function clearQueue( string $queue ): int
	{
		return $this->_manager?->clear( $queue ) ?? 0;
	}

	// -- failed -----------------------------------------------------------

	/**
	 * A page of failed jobs, newest first.
	 *
	 * @return array{rows:list<array>,total:int,page:int,pages:int,page_size:int}
	 */
	public function failed( ?string $queue = null, int $page = 1 ): array
	{
		if( $this->_manager === null )
		{
			return $this->emptyPage( $page );
		}

		$total = $this->_manager->countFailedJobs( $queue );
		$paging = $this->paging( $total, $page );

		$rows = array_map(
			fn( array $row ) => $this->failedRow( $row ),
			$this->_manager->listFailedJobs( $paging['page_size'], $paging['offset'], $queue )
		);

		return [ 'rows' => $rows ] + $paging;
	}

	public function failedJob( string $id ): ?array
	{
		$row = $this->_manager?->findFailedJob( $id );

		return $row === null ? null : $this->failedRow( $row, true );
	}

	/**
	 * Queues that have failed jobs, for the filter dropdown.
	 *
	 * @return list<string>
	 */
	public function failedQueues(): array
	{
		if( $this->_manager === null )
		{
			return [];
		}

		$queues = [];

		foreach( $this->_manager->listFailedJobs( PHP_INT_MAX, 0 ) as $row )
		{
			$queue = (string)( $row['queue'] ?? '' );

			if( $queue !== '' )
			{
				$queues[ $queue ] = true;
			}
		}

		$queues = array_keys( $queues );
		sort( $queues );

		return $queues;
	}

	public function retry( string $id ): bool
	{
		return $this->_manager?->retryFailedJob( $id ) ?? false;
	}

	public function retryAll(): int
	{
		return $this->_manager?->retryAllFailedJobs() ?? 0;
	}

	public function forget( string $id ): bool
	{
		return $this->_manager?->forgetFailedJob( $id ) ?? false;
	}

	public function clearFailed(): int
	{
		return $this->_manager?->clearFailedJobs() ?? 0;
	}

	// -- schedule ---------------------------------------------------------

	public function hasSchedule(): bool
	{
		return $this->_schedule !== null && $this->_schedule->exists();
	}

	/**
	 * The next few scheduled runs with their last claim, soonest first.
	 *
	 * @return list<array> ScheduleEntry::toArray() + lastTriggered/lastTriggeredAt
	 */
	public function upcoming( int $limit = 5 ): array
	{
		if( !$this->hasSchedule() )
		{
			return [];
		}

		$claims = $this->lastClaims();
		$rows = [];

		foreach( $this->_schedule->upcoming( $limit ) as $entry )
		{
			$row = $entry->toArray();
			$claimed = $claims[ $entry->getName() ] ?? null;
			$row['lastTriggeredAt'] = $claimed;
			$row['lastTriggered'] = $claimed === null ? null : date( 'Y-m-d H:i:s', $claimed );
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Whether "Last triggered" has a source (database schedule lock on a
	 * database queue).
	 */
	public function lastTriggeredAvailable(): bool
	{
		return $this->_settings?->get( 'queue', 'schedule_lock' ) === 'database'
			&& $this->_manager?->getDriver() instanceof DatabaseQueue;
	}

	/**
	 * Queue a scheduled entry right now, independent of its cron.
	 *
	 * @param string $name Entry name
	 * @return array{ok:bool,message:string,id?:string,deduplicated?:bool}
	 */
	public function dispatchScheduled( string $name ): array
	{
		if( $this->_manager === null )
		{
			return [ 'ok' => false, 'message' => 'The queue is unavailable.' ];
		}

		$entry = $this->_schedule?->find( $name );

		if( $entry === null )
		{
			return [ 'ok' => false, 'message' => "No scheduled job named '{$name}'." ];
		}

		if( !$entry->classExists() )
		{
			return [ 'ok' => false, 'message' => "Class {$entry->getClass()} for '{$name}' does not exist." ];
		}

		try
		{
			$job = $this->instantiate( $entry );
		}
		catch( \Throwable $e )
		{
			return [ 'ok' => false, 'message' => "Could not build {$entry->getClass()}: {$e->getMessage()}" ];
		}

		$queue = $entry->getQueue() ?? $this->defaultQueue();
		$driver = $this->_manager->getDriver();

		$duplicateOf = null;

		if( $job instanceof IUniqueJob && $driver instanceof IUniqueQueue )
		{
			$duplicateOf = $driver->findPending( get_class( $job ), $entry->getArgs(), $queue );
		}

		try
		{
			$id = $this->_manager->dispatch( $job, $entry->getArgs(), $queue );
		}
		catch( \Throwable $e )
		{
			Log::error( "Labor Board: dispatch of '{$name}' failed: {$e->getMessage()}" );

			return [ 'ok' => false, 'message' => "Dispatch failed: {$e->getMessage()}" ];
		}

		if( $duplicateOf !== null )
		{
			return [
				'ok' => true,
				'id' => $id,
				'deduplicated' => true,
				'message' => "'{$name}' is already pending on '{$queue}' ({$id}); not queued again.",
			];
		}

		return [
			'ok' => true,
			'id' => $id,
			'deduplicated' => false,
			'message' => "Queued '{$name}' on '{$queue}' as {$id}.",
		];
	}

	// -- internals --------------------------------------------------------

	/**
	 * @return array<string,int>
	 */
	private function lastClaims(): array
	{
		if( !$this->lastTriggeredAvailable() )
		{
			return [];
		}

		try
		{
			/** @var DatabaseQueue $driver */
			$driver = $this->_manager->getDriver();

			return ( new DatabaseScheduleLock( $driver->getConnection() ) )->getLastClaims();
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Labor Board: schedule lock claims unavailable: ' . $e->getMessage() );
			return [];
		}
	}

	private function instantiate( ScheduleEntry $entry ): IJob
	{
		$class = $entry->getClass();

		$job = $this->_container !== null ? $this->_container->make( $class ) : new $class();

		if( !$job instanceof IJob )
		{
			throw new \RuntimeException( "{$class} does not implement " . IJob::class );
		}

		return $job;
	}

	/**
	 * @param QueuedJob $job
	 * @param bool $full Include the pretty-printed payload
	 * @return array
	 */
	private function jobRow( QueuedJob $job, bool $full = false ): array
	{
		$state = JobState::of( $job->getReservedAt(), $job->getAvailableAt(), $this->_now );
		$args = $job->getArguments();

		$row = [
			'id' => $job->getId(),
			'queue' => $job->getQueue(),
			'class' => $job->getJobClass(),
			'short_class' => self::shortClass( $job->getJobClass() ),
			'args_preview' => self::preview( $args ),
			'attempts' => $job->getAttempts(),
			'state' => $state->value,
			'created_at' => $job->getCreatedAt(),
			'available_at' => $job->getAvailableAt(),
			'reserved_at' => $job->getReservedAt(),
			// How long until it runs (delayed), has waited (pending), or has been held (reserved)
			'age' => match( $state )
			{
				JobState::Delayed => max( 0, $job->getAvailableAt() - $this->_now ),
				JobState::Pending => max( 0, $this->_now - $job->getAvailableAt() ),
				JobState::Reserved => max( 0, $this->_now - (int)$job->getReservedAt() ),
			},
			'stale' => $state === JobState::Reserved && ( $this->_now - (int)$job->getReservedAt() ) >= $this->staleAfter(),
			'can_delete' => $state !== JobState::Reserved,
			'can_release' => $state === JobState::Delayed,
		];

		if( $full )
		{
			$row['args'] = $args;
			$row['payload'] = self::pretty( $job->getPayload() );
		}

		return $row;
	}

	/**
	 * @param array $row Driver failed-job row: id, queue, payload, exception, failed_at
	 * @param bool $full
	 * @return array
	 */
	private function failedRow( array $row, bool $full = false ): array
	{
		$payload = (string)( $row['payload'] ?? '' );
		$decoded = json_decode( $payload, true );
		$class = is_array( $decoded ) ? (string)( $decoded['class'] ?? '' ) : '';
		$args = is_array( $decoded ) && is_array( $decoded['args'] ?? null ) ? $decoded['args'] : [];
		$exception = (string)( $row['exception'] ?? '' );
		$failedAt = isset( $row['failed_at'] ) ? (int)$row['failed_at'] : null;

		$out = [
			'id' => (string)( $row['id'] ?? '' ),
			'queue' => (string)( $row['queue'] ?? '' ),
			'class' => $class,
			'short_class' => self::shortClass( $class ),
			'args_preview' => self::preview( $args ),
			'exception_summary' => self::firstLine( $exception ),
			'failed_at' => $failedAt,
			'failed_ago' => $failedAt === null ? null : max( 0, $this->_now - $failedAt ),
		];

		if( $full )
		{
			$out['args'] = $args;
			$out['payload'] = self::pretty( $payload );
			$out['exception'] = $exception;
		}

		return $out;
	}

	/**
	 * @return array{total:int,page:int,pages:int,page_size:int,offset:int}
	 */
	private function paging( int $total, int $page ): array
	{
		$size = $this->_config->pageSize;
		$pages = max( 1, (int)ceil( $total / $size ) );
		$page = min( max( 1, $page ), $pages );

		return [
			'total' => $total,
			'page' => $page,
			'pages' => $pages,
			'page_size' => $size,
			'offset' => ( $page - 1 ) * $size,
		];
	}

	private function emptyPage( int $page ): array
	{
		return [ 'rows' => [] ] + $this->paging( 0, $page );
	}

	public static function shortClass( string $class ): string
	{
		$pos = strrpos( $class, '\\' );

		return $pos === false ? $class : substr( $class, $pos + 1 );
	}

	/**
	 * First 80 characters of the arguments as JSON.
	 */
	public static function preview( array $args, int $length = 80 ): string
	{
		if( $args === [] )
		{
			return '';
		}

		$json = json_encode( $args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if( $json === false )
		{
			return '[unencodable]';
		}

		return mb_strlen( $json ) > $length ? mb_substr( $json, 0, $length - 1 ) . '…' : $json;
	}

	public static function firstLine( string $text ): string
	{
		$line = strtok( $text, "\r\n" );

		return $line === false ? '' : trim( $line );
	}

	public static function pretty( string $json ): string
	{
		$decoded = json_decode( $json, true );

		if( $decoded === null && trim( $json ) !== 'null' )
		{
			return $json;
		}

		return (string)json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
