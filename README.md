# Introduction

This document is structured into two sections using collapsible tabs:

- **Episode Duplication Technical Test** → explains the architecture, design decisions and implementation
- **Installation & Commands** → provides setup instructions, fixtures, tests and useful commands

Please expand each section to explore the full content.

<details>
<summary>Episode Duplication Technical Test</summary>

# Episode Duplication Technical Test

I designed an asynchronous solution based on Laravel Queues, batch processing, short-lived transactions, a tracking table, and an idempotent mapping strategy. The architecture can integrate with AWS services such as SQS and CloudWatch. However, since my practical experience is primarily with S3, I chose to incorporate only service S3 from aws in my approach.

## Context

An Episode contains a nested hierarchy:

```txt
Episode
 └── Parts
      └── Articles
           └── Blocks
                ├── BlockFields
                └── Medias
```

Each level can contain many children, so the duplication process must be asynchronous, scalable, observable and retry-safe.

## Technical Stack

- Laravel
- PHP 8.3
- MySQL 8
- Redis
- Laravel Queues
- Docker
- phpMyAdmin
- PHPUnit / Feature tests

Redis is used only as the queue driver.

The database remains the source of truth for duplication status, mappings, progress and errors.

## Proposed Approach

The duplication is handled asynchronously.
The HTTP request only initiates the process and delegates the actual duplication to a background job.

```txt
  User
    └── POST /api/episodes/{id}/duplicate
        └── Create episode_duplications record
            └── Dispatch DuplicateEpisodeJob
                └── Redis Queue
                    └── Worker processes duplication in batches
```

## 2 Tracking Tables

This structure provides full visibility over the duplication lifecycle, enabling us to track the current status, the source and target episodes, the progression of the process, and to capture and diagnose any errors that may occur during execution.

table : episode_duplications

Tracks the duplication lifecycle:

- source episode
- target episode
- status
- progress
- metadata
- error message
- timestamps

table : duplication_mappings

Stores old ID to new ID mappings.

This makes the duplication idempotent and retry-safe.

Example:

```txt
old part ID 1 → new part ID 24
old article ID 7 → new article ID 41
```

If a job is retried, already duplicated entities are skipped.

## Idempotency & Failure Recovery

To prevent duplicate data during retries:

- every duplicated entity is tracked in ‘duplication_mappings’
- before duplication → check if already processed
- after duplication → persist mapping
- unique constraint prevents race conditions

This ensures safe retries even if:

- a worker crashes
- a job is retried
- the process is interrupted

## Transaction

I explicitly avoid using a single large transaction. Instead, I use short-lived transactions per batch:

- reduces lock duration
- prevents deadlocks
- improves scalability
- allows partial recovery

## API Endpoints

Start duplication

```txt
POST /api/episodes/{episode}/duplicate
```

Response

```txt
{
  "message": "Duplication has started",
  "duplication_id": "uuid",
  "status": "pending"
}
```

A status endpoint allows progress tracking:

```txt
GET /api/episode-duplications/{duplication}
```

Response

```txt
{
  "id": "uuid",
  "status": "completed",
  "progress": 100,
  "target_episode_id": 2,
  "error_message": null
}
```

## Job Implementation

The worker is defined in Docker and processes jobs from Redis.

## Media Handling
If multiple episodes can point to the same S3 file, I only duplicate the line in the database.

```txt
  $newMedia = $media->replicate();
  $newMedia->block_id = $newBlockId;
  $newMedia->save();
```

Fast and efficient.

## Impact on Other Users
This design minimizes impact with no long HTTP requests, a background processing and short transactions. The target episode remains hidden until fully duplicated (status = 'duplicating' → then 'draft')
The duplication should not block other users from reading or editing the original episode.
The duplicated episode should be based on a snapshot of the source episode at the time the duplication starts. Any changes made after that moment would not be included in the current duplication.

This keeps the platform responsive and avoids long locks. If the product requires stronger consistency, we could introduce a short-lived lock or mark the source episode as “duplicating”, but I would avoid locking it for the full duration of the job.

## Observability

Observability is important because the duplication process is asynchronous and can potentially run for a long time. Since the user does not directly wait for the HTTP request to finish, the system must provide enough visibility to understand what is happening during the job execution.

I use structured logs with Laravel `Log` to track important steps of the duplication lifecycle:

```php
Log::info('Episode duplication started', [
    'duplication_id' => $duplication->id,
    'source_episode_id' => $duplication->source_episode_id,
]);

Log::info('Episode duplication completed', [
    'duplication_id' => $duplication->id,
    'target_episode_id' => $duplication->target_episode_id,
]);

Log::error('Episode duplication failed', [
    'duplication_id' => $duplication->id,
    'error' => $e->getMessage(),
]);
```

The episode_duplications table also stores the current status, progress, metadata and potential error message. This allows the frontend or support team to check the state of a duplication without reading logs directly.

Useful metrics to monitor include:

- duplication_started
- duplication_completed
- duplication_failed
- duplication_duration

These metrics help answer operational questions such as how many duplications are running, how many fail, how long they take on average, whether a recent deployment increased the failure rate, and whether workers are overloaded.

# Conclusion

This solution provides a production-oriented approach to duplicating complex hierarchical data.

It ensures:

- asynchronous processing
- controlled database usage
- idempotency
- safe retries
- progress tracking
- failure visibility
- minimal impact on other users

The system can handle both small and large episodes while maintaining performance and data consistency.
</details>
<details>
<summary>Installation & Others Commands</summary>

## Docker Installation

```txt
git clone git@github.com:FabreAllan/test_cuez.git
cd episode-duplication-technical-test
cp .env.example .env
docker compose up -d --build
```

- Install dependencies / Generate app key / Run migrations and seeders
```txt
  docker compose exec app composer install
  docker compose exec app php artisan key:generate
  docker compose exec app php artisan migrate --seed
```

## Fixtures / Seeders

The database is seeded with sample data for testing purposes. You can modify the seeders to create different scenarios.

Run fixtures:
```txt
  docker compose exec app php artisan migrate:fresh --seed
```

This allows testing the duplication flow with real nested data.

## Services

```txt
Service	             |   URL
---------------------------------------------
Laravel API         => http://localhost:8000
phpMyAdmin          => http://localhost:8081
MySQL from host     => 127.0.0.1:3307
MySQL inside Docker => mysql:3306
Redis inside Docker => redis:6379
```

## Queue Worker

The worker is defined in Docker and processes jobs from Redis.

To view worker logs:

```txt
  docker compose logs -f worker
```

To manually run the worker:
```txt
  docker compose exec app php artisan queue:work redis --queue=episode-duplications
```

## Tests

Feature tests are included to validate:

- duplication job dispatching
- status endpoint
- job execution
- idempotency behavior

Run tests:
```txt
  docker compose exec app php artisan test
```

If needed, clear config before running tests:
```txt
  docker compose exec app php artisan config:clear
```
</details>
