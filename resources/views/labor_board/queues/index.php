<?php
/**
 * @var \Neuron\LaborBoard\Services\Board $Board
 * @var string $Queue
 * @var \Neuron\Jobs\Queue\JobState $State
 * @var \Neuron\Jobs\Queue\JobState[] $States
 * @var array<string,int> $Counts
 * @var array $Jobs rows,total,page,pages,page_size
 */
use Neuron\Jobs\Queue\JobState;

$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$queueUrl = static fn( JobState $s, int $page = 1 ): string =>
	route_path( 'labor_board_queue', [ 'queue' => $Queue ] ) . '?state=' . $s->value . ( $page > 1 ? '&page=' . $page : '' );
$ageLabel = match( $State )
{
	JobState::Pending => 'Waiting',
	JobState::Delayed => 'Runs in',
	JobState::Reserved => 'Held for',
};
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0"><i class="bi bi-list-task me-2"></i>Queue: <code><?= $h( $Queue ) ?></code></h2>
		<?php if( ( ( $Counts['pending'] ?? 0 ) + ( $Counts['delayed'] ?? 0 ) + ( $Counts['reserved'] ?? 0 ) ) > 0 ): ?>
		<form method="post" action="<?= $h( route_path( 'labor_board_queue_clear', [ 'queue' => $Queue ] ) ) ?>"
			onsubmit="return confirm('Remove every pending, delayed and reserved job from <?= $h( addslashes( $Queue ) ) ?>? This cannot be undone.');">
			<?= csrf_field() ?>
			<input type="hidden" name="_method" value="DELETE">
			<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Clear queue</button>
		</form>
		<?php endif; ?>
	</div>

	<?php $Active = 'queue'; $FailedCount = $Board->failedCount(); include __DIR__ . '/../_tabs.php'; ?>

	<ul class="nav nav-pills mb-3">
		<?php foreach( $States as $s ): ?>
		<li class="nav-item">
			<a class="nav-link <?= $s === $State ? 'active' : '' ?>" href="<?= $h( $queueUrl( $s ) ) ?>">
				<?= ucfirst( $s->value ) ?>
				<span class="badge <?= $s === $State ? 'bg-light text-dark' : 'bg-secondary' ?> ms-1"><?= (int)( $Counts[ $s->value ] ?? 0 ) ?></span>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>

	<?php if( empty( $Inspectable ) ): ?>
		<div class="alert alert-info" role="alert">
			<i class="bi bi-info-circle me-2"></i>
			The <code><?= $h( $Board->driverName() ) ?></code> driver reports counts only; it cannot list individual jobs.
			Failed jobs below still work.
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body p-0">
			<?php if( empty( $Inspectable ) ): ?>
				<div class="p-3 text-muted">This driver does not support listing jobs in a queue.</div>
			<?php elseif( $Jobs['rows'] === [] ): ?>
				<div class="p-3 text-muted">No <?= $h( $State->value ) ?> jobs in <code><?= $h( $Queue ) ?></code>.</div>
			<?php else: ?>
				<table class="table table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Job</th>
							<th>Arguments</th>
							<th class="text-end">Attempts</th>
							<th class="text-end"><?= $ageLabel ?></th>
							<th>Created</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach( $Jobs['rows'] as $job ): ?>
						<tr class="<?= $job['stale'] ? 'table-warning' : '' ?>">
							<td>
								<a href="<?= $h( route_path( 'labor_board_job', [ 'queue' => $Queue, 'id' => $job['id'] ] ) ) ?>" title="<?= $h( $job['class'] ) ?>">
									<?= $h( $job['short_class'] ) ?>
								</a>
								<?php if( $job['stale'] ): ?>
									<span class="badge bg-warning text-dark ms-1" title="Reserved longer than <?= $h( labor_board_duration( $Board->staleAfter() ) ) ?>; the worker may have died">stale</span>
								<?php endif; ?>
								<div class="small text-muted font-monospace"><?= $h( $job['id'] ) ?></div>
							</td>
							<td><code class="small"><?= $h( $job['args_preview'] ) ?></code></td>
							<td class="text-end"><?= (int)$job['attempts'] ?></td>
							<td class="text-end"><?= $h( labor_board_duration( $job['age'] ) ) ?></td>
							<td class="small"><?= $h( labor_board_time( $job['created_at'] ) ) ?></td>
							<td class="text-end text-nowrap">
								<?php if( $job['can_release'] ): ?>
								<form method="post" action="<?= $h( route_path( 'labor_board_job_release', [ 'queue' => $Queue, 'id' => $job['id'] ] ) ) ?>" class="d-inline">
									<?= csrf_field() ?>
									<button type="submit" class="btn btn-sm btn-outline-primary" title="Make available now"><i class="bi bi-skip-forward"></i></button>
								</form>
								<?php endif; ?>
								<?php if( $job['can_delete'] ): ?>
								<form method="post" action="<?= $h( route_path( 'labor_board_job_delete', [ 'queue' => $Queue, 'id' => $job['id'] ] ) ) ?>" class="d-inline"
									onsubmit="return confirm('Delete this job?');">
									<?= csrf_field() ?>
									<input type="hidden" name="_method" value="DELETE">
									<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
								</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<?php $Paging = $Jobs; $PageUrl = static fn( int $p ): string => $queueUrl( $State, $p ); include __DIR__ . '/../_pager.php'; ?>
</div>
