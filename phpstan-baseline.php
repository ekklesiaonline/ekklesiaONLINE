<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#5 \\$description \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/DioceseSettingModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#4 \\$category \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/OrganisationSettingModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#5 \\$description \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/OrganisationSettingModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#2 \\$year \\(int\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 2,
	'path' => __DIR__ . '/app/Models/ParishReportModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#3 \\$reason \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/ParishReportModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#5 \\$description \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/ProvinceSettingModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#4 \\$signature \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/ReportSignatoryModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Array has 2 duplicate keys with value \'parish_id\' \\(\'parish_id\', \'parish_id\'\\)\\.$#',
	'identifier' => 'array.duplicateKey',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/UserRegistrationRequestModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Array has 2 duplicate keys with value \'requested_parish_id\' \\(\'requested_parish_id\', \'requested_parish_id\'\\)\\.$#',
	'identifier' => 'array.duplicateKey',
	'count' => 1,
	'path' => __DIR__ . '/app/Models/UserRegistrationRequestModel.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#4 \\$category \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 1,
	'path' => __DIR__ . '/app/Services/ConfigurationService.php',
];
$ignoreErrors[] = [
	'message' => '#^Deprecated in PHP 8\\.4\\: Parameter \\#5 \\$description \\(string\\) is implicitly nullable via default value null\\.$#',
	'identifier' => 'parameter.implicitlyNullable',
	'count' => 3,
	'path' => __DIR__ . '/app/Services/ConfigurationService.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
