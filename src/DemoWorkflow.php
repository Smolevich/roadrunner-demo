<?php

declare(strict_types=1);

namespace App;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class DemoWorkflow
{
    #[WorkflowMethod(name: 'DemoWorkflow')]
    public function handle(string $workflowId): \Generator
    {
        $activity = \Temporal\Workflow::newActivityStub(
            DemoActivityInterface::class,
            \Temporal\Activity\ActivityOptions::new()
                ->withStartToCloseTimeout(\DateInterval::createFromDateString('30 seconds'))
        );

        $result = yield $activity->process($workflowId);

        return $result;
    }
}