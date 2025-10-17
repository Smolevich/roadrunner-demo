<?php

declare(strict_types=1);

use Spiral\RoadRunner\Worker;
use Temporal\WorkerFactory;

require __DIR__ . '/vendor/autoload.php';

$factory = WorkerFactory::create();

$worker = $factory->newWorker('default');

$worker->registerWorkflowTypes(App\DemoWorkflow::class);
$worker->registerActivity(App\DemoActivity::class);

$factory->run();