<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunReportRequest;

try {
    $property_id = env('ANALYTICS_PROPERTY_ID');

    // تغییر این خط در فایل test-ga.php
    $key_file = base_path(env('ANALYTICS_SERVICE_ACCOUNT_KEY_FILE'));
// به این تغییر بده تا دیباگ کنیم:
    echo "Searching for file at: " . base_path(env('ANALYTICS_SERVICE_ACCOUNT_KEY_FILE')) . "\n";

    echo "Testing connection for Property ID: $property_id\n";
    echo "Using Key File: $key_file\n";

    $client = new BetaAnalyticsDataClient([
        'credentials' => $key_file
    ]);

    $request = (new RunReportRequest())
        ->setProperty('properties/' . $property_id)
        ->setDateRanges([new \Google\Analytics\Data\V1beta\DateRange(['start_date' => 'yesterday', 'end_date' => 'today'])])
        ->setMetrics([new \Google\Analytics\Data\V1beta\Metric(['name' => 'activeUsers'])]);

    $response = $client->runReport($request);

    echo "✅ Success! Connection established.\n";
    echo "Active Users (Yesterday/Today): " . ($response->getRows()[0]->getMetricValues()[0]->getValue() ?? 0) . "\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
