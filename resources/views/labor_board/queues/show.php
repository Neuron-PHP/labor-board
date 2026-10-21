<?php
/**
 * @var \Neuron\LaborBoard\Services\Board $Board
 * @var string $Queue
 * @var array $Job
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$stateClass = [ 'pending' => 'primary', 'delayed' => 'info text-dark', 'reserved' => 'warning text-dark' ];
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">
			<i class="bi bi-box me-2"></i><?= $h( $Job['short_class'] ) ?>
			<span class="badge bg-<?= $stateClass[ $Job['state'] ] ?? 'secondary' ?> ms-2"><?= $h( $Job['state'] ) ?></span>
			<?php if( $Job['stale'] ): ?><span class="badge bg-warning text-dark">stale</span><?php endif; ?>
		</h2>
		<div class="d-flex gap-2">
			<?php if( $Job['can_release'] ): ?>
			<form method="post" action="<?= $h( route_path( 'labor_board_job_release', [ 'queue' => $Queue, 'id' => $Job['id'] ] ) ) ?>">
				<?= csrf_field() ?>
				<button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-skip-forward me-1"></i>Release now</button>
			</form>
			<?php endif; ?>
			<?php if( $Job['can_delete'] ): ?>
			<form method="post" action="<?= $h( route_path( 'labor_board_job_delete', [ 'queue' => $Queue, 'id' => $Job['id'] ] ) ) ?>"
				onsubmit="return confirm('Delete this job?');">
				<?= csrf_field() ?>
				<input type="hidden" name="_method" value="DELETE">
				<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
			</form>
			<?php endif; ?>
		</div>
	</div>

	<?php $Active = 'queue'; $FailedCount = $Board->failedCount(); include __DIR__ . '/../_tabs.php'; ?>

	<div class="row g-3">
		<div class="col-lg-5">
			<div class="card h-100">
				<div class="card-header">Job</div>
				<div class="card-body p-0">
					<table class="table table-sm mb-0">
						<tbody>
							<tr><th class="text-muted fw-normal" style="width: 9rem">ID</th><td class="font-monospace small"><?= $h( $Job['id'] ) ?></td></tr>
							<tr><th class="text-muted fw-normal">Class</th><td><code><?= $h( $Job['class'] ) ?></code></td></tr>
							<tr><th class="text-muted fw-normal">Queue</th><td><a href="<?= $h( route_path( 'labor_board_queue', [ 'queue' => $Queue ] ) ) ?>"><?= $h( $Queue ) ?></a></td></tr>
							<tr><th class="text-muted fw-normal">Attempts</th><td><?= (int)$Job['attempts'] ?></td></tr>
							<tr><th class="text-muted fw-normal">Created</th><td><?= $h( labor_board_time( $Job['created_at'] ) ) ?></td></tr>
							<tr><th class="text-muted fw-normal">Available</th><td><?= $h( labor_board_time( $Job['available_at'] ) ) ?></td></tr>
							<tr>
								<th class="text-muted fw-normal">Reserved</th>
								<td>
									<?= $h( labor_board_time( $Job['reserved_at'] ) ) ?>
									<?php if( $Job['state'] === 'reserved' ): ?>
										<span class="text-muted small">(<?= $h( labor_board_duration( $Job['age'] ) ) ?> ago<?= $Job['stale'] ? ', past the ' . $h( labor_board_duration( $Board->staleAfter() ) ) . ' stale threshold' : '' ?>)</span>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="card h-100">
				<div class="card-header">Payload</div>
				<div class="card-body">
					<pre class="mb-0 small"><code><?= $h( $Job['payload'] ) ?></code></pre>
				</div>
			</div>
		</div>
	</div>
</div>
