# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Demo project for testing RoadRunner with PHP workers. Demonstrates dynamic worker pool auto-scaling when processing Temporal workflows under load.

**Key Components:**
- **RoadRunner**: PHP application server managing worker pool lifecycle
- **Temporal**: Workflow orchestration engine (runs workflows asynchronously)
- **Dynamic Scaling**: Workers auto-scale from 2 to 25 based on load, then scale down when idle

## Architecture

**Workflow Execution Flow:**
1. `load-test.php` creates workflows via Temporal client (starts workflows asynchronously, doesn't wait for completion)
2. Temporal server queues workflows in `default` task queue
3. `worker.php` (running in RoadRunner) polls for workflows
4. RoadRunner spawns additional workers when queue depth increases
5. Each workflow executes `DemoWorkflow::handle()` which calls `DemoActivity::process()`
6. Workers scale down after 60s idle (configured in `rr.yaml`)

**Critical Type Requirement:**
- Temporal workflow methods that use `yield` MUST return `\Generator` type, not the actual return type
- Example: `DemoWorkflow::handle()` returns `\Generator`, even though the activity returns `string`

## Development Commands

**Start services:**
```bash
docker-compose up -d
docker-compose ps  # verify all containers running
```

**Install dependencies locally (required before starting):**
```bash
docker run --rm -v "$(pwd):/app" -w /app roadrunner-demo-roadrunner \
  composer install --no-dev --optimize-autoloader
```

**Run load test:**
```bash
# 1 million workflows (stress test)
docker run --rm -v "$(pwd):/app" -w /app \
  --network roadrunner-demo_default \
  -e TEMPORAL_ADDRESS=temporal:7233 \
  roadrunner-demo-roadrunner \
  php load-test.php 1000000 1000

# Quick test (10k workflows)
docker run --rm -v "$(pwd):/app" -w /app \
  --network roadrunner-demo_default \
  -e TEMPORAL_ADDRESS=temporal:7233 \
  roadrunner-demo-roadrunner \
  php load-test.php 10000 100
```

**Monitor worker scaling (correct method):**
```bash
# Real-time monitoring
watch -n 3 'docker exec roadrunner-demo rr -c rr.yaml workers'

# Single check
docker exec roadrunner-demo rr -c rr.yaml workers
```

**Check workflow status:**
```bash
# Count running workflows
docker compose exec temporal-admin-tools temporal workflow count \
  --query 'ExecutionStatus="Running"'

# Count completed workflows
docker compose exec temporal-admin-tools temporal workflow count \
  --query 'ExecutionStatus="Completed"'
```

## RoadRunner Configuration (`rr.yaml`)

**Critical settings for worker scaling:**
- `num_workers: 2` - initial worker count
- `max_workers: 25` - maximum workers under load
- `spawn_rate: 10` - workers created per scaling cycle
- `max_concurrent: 100` - MUST be high (100+) for scaling to work
- `idle_timeout: 60s` - time before idle workers are terminated (in `dynamic_allocator`)
- `idle_ttl: 0s` - MUST be 0 or very high in `supervisor` to prevent premature worker termination
- `max_worker_memory: 256` - MB per worker (adjust based on actual usage)

**Common scaling issues:**
- Workers restart constantly: `max_worker_memory` too low or `idle_ttl` too short
- Workers don't scale up: `max_concurrent` too low or `idle_ttl` killing workers prematurely
- Workers don't scale down: workflows still running or `idle_timeout` not configured

Full RoadRunner config reference: https://github.com/roadrunner-server/roadrunner/blob/master/.rr.yaml

## Docker Volume Mounting

The project uses volume mounting (`.:/app`) to allow local development:
- PHP dependencies MUST be installed locally (not in Dockerfile)
- Changes to code are immediately reflected in running containers
- `vendor/` directory is gitignored but required locally

## Service Endpoints

- Temporal UI: http://localhost:8084
- RoadRunner: http://localhost:8085
- Temporal Server: localhost:7234
- PostgreSQL: localhost:5433

## Temporal CLI

Use `temporal-admin-tools` service from docker-compose.yml (NOT deprecated `tctl`). Run commands with `docker compose exec temporal-admin-tools temporal ...`. The service is already configured with correct network and address settings.

## Git Commits

Never add `Co-Authored-By: Claude` or `Generated with Claude Code` to commits in this repository.

**Critical Git Workflow Rules:**
- **NEVER** run `git add -A` or `git add .` unless explicitly requested by the user
- Only stage specific files that are directly related to the current task
- Always verify what files will be staged before committing
- User maintains full control over which files get staged for commits