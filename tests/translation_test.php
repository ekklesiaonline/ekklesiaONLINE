<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Assuming bootstrap.php is in app/Config/bootstrap.php as per the prompt
// However, CodeIgniter 4 typically uses system/bootstrap.php or similar.
// If the prompt specifies this path, we'll try it.
if (file_exists(__DIR__ . '/../app/Config/bootstrap.php')) {
    require_once __DIR__ . '/../app/Config/bootstrap.php';
} elseif (file_exists(__DIR__ . '/../spark')) {
    // Basic CI4 initialization if bootstrap.php doesn't exist where specified
    define('FCPATH', __DIR__ . '/../public/');
    require_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
}

use App\Models\TranslatableNameModel;

$model = model(TranslatableNameModel::class);

// Test 1: Get English translation for Province 1
$en1 = $model->getTranslation('province', 1, 'en');
echo "Province 1 (EN): " . ($en1 ?: 'NULL') . "\n";

// Test 2: Get Spanish translation for Province 1
$es1 = $model->getTranslation('province', 1, 'es');
echo "Province 1 (ES): " . ($es1 ?: 'NULL') . "\n";

// Test 3: Get Province 9 (with ampersand)
$en9 = $model->getTranslation('province', 9, 'en');
echo "Province 9 (EN): " . ($en9 ?: 'NULL') . "\n";

// Test 4: Batch fetch
$batch = $model->getTranslationsBatch('province', [1, 2, 3], 'en');
echo "Batch fetch count: " . count($batch) . "\n";
echo "Batch[1]: " . ($batch[1] ?? 'NULL') . "\n";

// Test 5: Check total translations
$db = \Config\Database::connect();
$total = $db->table('translatable_names')->countAllResults();
echo "Total translations in DB: " . $total . "\n";
