<?php

declare(strict_types=1);

namespace App;

class DemoActivity implements DemoActivityInterface
{
    public function process(string $workflowId): string
    {
        // Simulate some work
        usleep(100000); // 100ms

        return sprintf('Processed workflow: %s', $workflowId);
    }
}