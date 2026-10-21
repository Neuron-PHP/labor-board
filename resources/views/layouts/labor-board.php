<?php
/**
 * Standalone layout used when the host application has no layouts/admin.php
 * (or labor_board.layout is set to 'labor-board').
 *
 * @var string|null $Title
 * @var string|null $Success
 * @var string|null $Error
 * @var string $content
 * @var mixed $User
 */
$h = static fn( ?string $s ): string => htmlspecialchars( (string)$s );
$pageTitle = trim( ( $Title ?? '' ) . ' · Labor Board', ' ·' );
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $h( $pageTitle ) ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
	<nav class="navbar navbar-expand bg-dark border-bottom mb-4" data-bs-theme="dark">
		<div class="container-fluid">
			<a class="navbar-brand" href="<?= $h( route_path( 'labor_board' ) ) ?>"><i class="bi bi-stack me-2"></i>Labor Board</a>
			<ul class="navbar-nav me-auto">
				<li class="nav-item"><a class="nav-link" href="<?= $h( route_path( 'labor_board' ) ) ?>">Overview</a></li>
				<li class="nav-item"><a class="nav-link" href="<?= $h( route_path( 'labor_board_failed' ) ) ?>">Failed</a></li>
			</ul>
			<?php if( !empty( $User ) ): ?>
				<span class="navbar-text small">
					<i class="bi bi-person-circle me-1"></i>
					<?= $h( is_object( $User ) && method_exists( $User, 'getUsername' ) ? $User->getUsername() : ( is_object( $User ) && method_exists( $User, 'getEmail' ) ? $User->getEmail() : '' ) ) ?>
				</span>
			<?php endif; ?>
		</div>
	</nav>

	<main class="container-fluid">
		<?php if( !empty( $Success ) ): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<i class="bi bi-check-circle me-2"></i><?= $h( $Success ) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>
		<?php if( !empty( $Error ) ): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<i class="bi bi-exclamation-circle me-2"></i><?= $h( $Error ) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?= $content ?>
	</main>

	<footer class="container-fluid text-muted small py-4">
		Labor Board for Neuron PHP
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
