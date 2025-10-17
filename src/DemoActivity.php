<?php

declare(strict_types=1);

namespace App;

class DemoActivity implements DemoActivityInterface
{
    public function process(string $workflowId): string
    {
        // Simulate long-running work that might block worker shutdown
        // 5-10 seconds to reproduce the issue from production logs
        sleep(rand(5, 10));

        return sprintf('Processed workflow: %s', $workflowId);
    }
}