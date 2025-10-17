<?php

declare(strict_types=1);

namespace App;

use DateInterval;
use Generator;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class DemoWorkflow
{
    #[WorkflowMethod(name: 'DemoWorkflow')]
    public function handle(string $workflowId): Generator
    {
        $activity = Workflow::newActivityStub(
            DemoActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(DateInterval::createFromDateString('10 minutes'))
                ->withScheduleToCloseTimeout(DateInterval::createFromDateString('1 hour'))
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(5)
                )
        );

        return yield $activity->process($workflowId);
    }
}