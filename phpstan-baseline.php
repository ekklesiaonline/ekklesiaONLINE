<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#4 \\$signature \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/ReportSignatoryModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Strict comparison using \\=\\=\\= between true and false will always evaluate to false\\.$#',
	'identifier' => 'identical.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/ParishReportApprovalService.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function in_array\\(\\) with arguments \'clergy\'\\|\'finance\'\\|\'followup_coordinator\'\\|\'member\'\\|\'parishadmin\'\\|\'vestry_secretary\'\\|\'volunteer…\'\\|\'worship_coordinator\'\\|\'worship_volunteer\', array\\{\'member\', \'clergy\', \'parishadmin\', \'finance\', \'vestry_secretary\', \'volunteer…\', \'followup_coordinator\', \'worship_coordinator\', \\.\\.\\.\\} and true will always evaluate to true\\.$#',
	'identifier' => 'function.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/RegistrationApprovalService.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
