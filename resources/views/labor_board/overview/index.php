<?php
/**
 * @var \Neuron\LaborBoard\Services\Board $Board
 * @var \Neuron\LaborBoard\Config $Config
 * @var array $Queues
 * @var int $FailedCount
 * @var array $Upcoming
 * @var bool $HasSchedule
 * @var bool $LastTriggeredAvailable
 * @var string|null $StatsUrl
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$levelClass = [ 'ok' => 'success', 'warn' => 'warning', 'alert' => 'danger' ];
$totalPending = array_sum( array_column( $Queues, 'pending' ) );
?>
<div class="container-fluid" data-lb-overview data-lb-stats-url="<?= $h( $StatsUrl ) ?>" data-lb-refresh="<?= (int)$Config->refreshSeconds ?>">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0"><i class="bi bi-stack me-2"></i>Queue</h2>
		<div class="d-flex align-items-center gap-3 small text-muted">
			<span>Driver <code><?= $h( $Board->driverName() ) ?></code></span>
			<?php if( $StatsUrl && $Config->refreshSeconds > 0 ): ?>
			<div class="form-check form-switch mb-0">
				<input class="form-check-input" type="checkbox" role="switch" id="lb-auto-refresh" data-lb-toggle checked>
				<label class="form-check-label" for="lb-auto-refresh">Auto-refresh (<?= (int)$Config->refreshSeconds ?>s)</label>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php $Active = 'overview'; include __DIR__ . '/../_tabs.php'; ?>

	<?php if( !$Board->isAvailable() ): ?>
		<div class="alert alert-danger" role="alert">
			<i class="bi bi-x-octagon me-2"></i>
			<strong>The queue is unavailable.</strong>
			<?= $h( $Board->error() ) ?>
			<div class="small mt-1">Check the <code>queue:</code> section of your configuration and that the queue store is reachable.</div>
		</div>
	<?php elseif( $Board->isSync() ): ?>
		<div class="alert alert-info" role="alert">
			<i class="bi bi-lightning-charge me-2"></i>
			<strong>Sync driver.</strong> Jobs run immediately when dispatched, so nothing waits in a queue and there is
			nothing to list here. Set <code>queue.driver</code> to <code>database</code> to see pending and failed jobs.
		</div>
	<?php else: ?>
		<div class="row g-3 mb-4">
			<div class="col-sm-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Pending</div>
						<div class="fs-2" data-lb-total-pending><?= (int)$totalPending ?></div>
					</div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Queues</div>
						<div class="fs-2"><?= count( $Queues ) ?></div>
					</div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3">
				<div class="card h-100 <?= $FailedCount > 0 ? 'border-danger' : '' ?>">
					<div class="card-body">
						<div class="text-muted small">Failed</div>
						<div class="fs-2 <?= $FailedCount > 0 ? 'text-danger' : '' ?>">
							<a href="<?= $h( route_path( 'labor_board_failed' ) ) ?>" class="text-reset text-decoration-none" data-lb-failed-total><?= (int)$FailedCount ?></a>
						</div>
					</div>
				</div>
			</div>
			<div class="col-sm-6 col-lg-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Stale after</div>
						<div class="fs-2"><?= $h( labor_board_duration( $Board->staleAfter() ) ) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span><i class="bi bi-list-task me-2"></i>Queues</span>
				<?php if( !$Board->supportsInspection() ): ?>
					<span class="badge bg-secondary" title="This driver reports counts only">counts only</span>
				<?php endif; ?>
			</div>
			<div class="card-body p-0">
				<table class="table table-striped align-middle mb-0" data-lb-queues>
					<thead>
						<tr>
							<th>Queue</th>
							<th class="text-end">Pending</th>
							<th class="text-end">Delayed</th>
							<th class="text-end">Reserved</th>
							<th class="text-end">Oldest waiting</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach( $Queues as $q ): ?>
						<tr data-lb-queue="<?= $h( $q['name'] ) ?>">
							<td>
								<span class="badge bg-<?= $levelClass[ $q['level'] ] ?? 'secondary' ?> me-2" data-lb-level>&nbsp;</span>
								<?php if( $Board->supportsInspection() ): ?>
									<a href="<?= $h( route_path( 'labor_board_queue', [ 'queue' => $q['name'] ] ) ) ?>"><?= $h( $q['name'] ) ?></a>
								<?php else: ?>
									<?= $h( $q['name'] ) ?>
								<?php endif; ?>
							</td>
							<td class="text-end" data-lb-pending><?= (int)$q['pending'] ?></td>
							<td class="text-end" data-lb-delayed><?= $q['delayed'] === null ? '—' : (int)$q['delayed'] ?></td>
							<td class="text-end" data-lb-reserved><?= $q['reserved'] === null ? '—' : (int)$q['reserved'] ?></td>
							<td class="text-end" data-lb-oldest><?= $h( labor_board_duration( $q['oldest_pending_age'] ) ) ?></td>
							<td class="text-end">
								<?php if( $Board->supportsInspection() ): ?>
									<a class="btn btn-sm btn-outline-secondary" href="<?= $h( route_path( 'labor_board_queue', [ 'queue' => $q['name'] ] ) ) ?>">View</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-header"><i class="bi bi-calendar-event me-2"></i>Upcoming scheduled runs</div>
		<div class="card-body p-0">
			<?php if( !$HasSchedule ): ?>
				<div class="p-3 text-muted">No <code>config/schedule.yaml</code> found.</div>
			<?php elseif( $Upcoming === [] ): ?>
				<div class="p-3 text-muted">No valid schedule entries.</div>
			<?php else: ?>
				<table class="table table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Name</th>
							<th>Class</th>
							<th>Cron</th>
							<th>Next run</th>
							<?php if( $LastTriggeredAvailable ): ?><th>Last triggered</th><?php endif; ?>
							<th>Queue</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach( $Upcoming as $e ): ?>
						<tr>
							<td><?= $h( $e['name'] ) ?></td>
							<td>
								<code><?= $h( $e['class'] ) ?></code>
								<?php if( empty( $e['classExists'] ) ): ?><span class="badge bg-danger ms-1">Class missing</span><?php endif; ?>
							</td>
							<td><code><?= $h( $e['cron'] ) ?></code></td>
							<td><?= $h( $e['nextRun'] ?? '—' ) ?></td>
							<?php if( $LastTriggeredAvailable ): ?>
							<td><?= $e['lastTriggered'] ? $h( $e['lastTriggered'] ) : '<span class="text-muted">never</span>' ?></td>
							<?php endif; ?>
							<td>
								<?php if( !empty( $e['queue'] ) ): ?>
									<span class="badge bg-info text-dark"><?= $h( $e['queue'] ) ?></span>
								<?php else: ?>
									<span class="badge bg-secondary" title="Dispatched to the default queue"><?= $h( $Board->defaultQueue() ) ?></span>
								<?php endif; ?>
							</td>
							<td class="text-end">
								<?php if( $Board->isAvailable() && !empty( $e['classExists'] ) ): ?>
								<form method="post" action="<?= $h( route_path( 'labor_board_schedule_dispatch', [ 'name' => $e['name'] ] ) ) ?>" class="d-inline"
									onsubmit="return confirm('Queue <?= $h( addslashes( $e['name'] ) ) ?> now?');">
									<?= csrf_field() ?>
									<button type="submit" class="btn btn-sm btn-outline-primary" title="Queue this job now, independent of its cron">
										<i class="bi bi-play-fill"></i> Dispatch now
									</button>
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
</div>

<?php if( $StatsUrl && $Config->refreshSeconds > 0 && $Board->isAvailable() && !$Board->isSync() ): ?>
<script>
(function () {
	var root = document.querySelector('[data-lb-overview]');
	if (!root) return;
	var url = root.getAttribute('data-lb-stats-url');
	var every = parseInt(root.getAttribute('data-lb-refresh'), 10) * 1000;
	var toggle = root.querySelector('[data-lb-toggle]');
	var timer = null;
	var levels = { ok: 'bg-success', warn: 'bg-warning', alert: 'bg-danger' };

	function dur(s) {
		if (s === null || s === undefined) return '—';
		if (s < 60) return s + 's';
		var u = [['d', 86400], ['h', 3600], ['m', 60], ['s', 1]], out = [];
		for (var i = 0; i < u.length && out.length < 2; i++) {
			if (s >= u[i][1]) { out.push(Math.floor(s / u[i][1]) + u[i][0]); s %= u[i][1]; }
		}
		return out.join(' ');
	}

	function apply(data) {
		if (!data || !data.available) return;
		var total = 0;
		(data.queues || []).forEach(function (q) {
			total += q.pending;
			var row = root.querySelector('[data-lb-queue="' + CSS.escape(q.name) + '"]');
			if (!row) { window.location.reload(); return; }
			row.querySelector('[data-lb-pending]').textContent = q.pending;
			row.querySelector('[data-lb-delayed]').textContent = q.delayed === null ? '—' : q.delayed;
			row.querySelector('[data-lb-reserved]').textContent = q.reserved === null ? '—' : q.reserved;
			row.querySelector('[data-lb-oldest]').textContent = dur(q.oldest_pending_age);
			var lvl = row.querySelector('[data-lb-level]');
			lvl.className = 'badge me-2 ' + (levels[q.level] || 'bg-secondary');
		});
		var tp = root.querySelector('[data-lb-total-pending]');
		if (tp) tp.textContent = total;
		var ft = root.querySelector('[data-lb-failed-total]');
		if (ft) ft.textContent = data.failed;
		var fc = root.querySelector('[data-lb-failed-count]');
		if (fc) fc.textContent = data.failed;
	}

	function tick() {
		fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(apply)
			.catch(function () {});
	}

	function start() { if (!timer) timer = setInterval(tick, every); }
	function stop() { if (timer) { clearInterval(timer); timer = null; } }

	if (toggle) {
		toggle.addEventListener('change', function () { toggle.checked ? start() : stop(); });
		if (toggle.checked) start();
	}
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) stop(); else if (!toggle || toggle.checked) start();
	});
})();
</script>
<?php endif; ?>
