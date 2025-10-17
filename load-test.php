<?php

declare(strict_types=1);

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

require __DIR__ . '/vendor/autoload.php';

$workflowsCount = (int)($argv[1] ?? 1000);
$batchSize = (int)($argv[2] ?? 100);

echo sprintf("Starting load test: %d workflows, batch size: %d\n", $workflowsCount, $batchSize);

$serviceClient = ServiceClient::create(getenv('TEMPORAL_ADDRESS') ?: 'localhost:7233');
$workflowClient = WorkflowClient::create($serviceClient);

$startTime = microtime(true);
$completed = 0;

for ($i = 0; $i < $workflowsCount; $i++) {
    $workflowId = sprintf('demo-workflow-%d-%d', time(), $i);

    try {
        $workflow = $workflowClient->newWorkflowStub(
            App\DemoWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId($workflowId)
                ->withTaskQueue('default')
        );

        // Start workflow asynchronously
        $workflowClient->start($workflow, $workflowId);

        $completed++;

        if ($completed % $batchSize === 0) {
            $elapsed = microtime(true) - $startTime;
            $rate = $completed / $elapsed;
            echo sprintf(
                "Progress: %d/%d workflows started (%.2f wf/s)\n",
                $completed,
                $workflowsCount,
                $rate
            );
        }
    } catch (\Throwable $e) {
        echo sprintf("Error starting workflow %s: %s\n", $workflowId, $e->getMessage());
    }
}

$totalTime = microtime(true) - $startTime;
$avgRate = $completed / $totalTime;

echo sprintf(
    "\nLoad test completed:\n" .
    "- Total workflows started: %d\n" .
    "- Total time: %.2f seconds\n" .
    "- Average rate: %.2f workflows/second\n",
    $completed,
    $totalTime,
    $avgRate
);

echo "\nNow workflows are executing. Monitor RoadRunner worker scaling in the logs.\n";
echo "Check Temporal UI at http://localhost:8080\n";
