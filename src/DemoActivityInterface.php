<?php

declare(strict_types=1);

namespace App;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface]
interface DemoActivityInterface
{
    #[ActivityMethod(name: 'DemoActivity.process')]
    public function process(string $workflowId): string;
}