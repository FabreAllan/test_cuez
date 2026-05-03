I designed an asynchronous solution based on Laravel queues, batch processing, short-lived transactions, a tracking table, and an idempotent mapping strategy.

The architecture is compatible with AWS services such as SQS and CloudWatch. However, as my hands-on experience is primarily with S3, I focused on integrating S3-related aspects where relevant.

---

## Proposed Approach

The duplication is handled asynchronously.
The HTTP request only initiates the process and delegates the actual duplication to a background job.

```
User
 └── POST /episodes/{id}/duplicate
      └── Create episode_duplications record
           └── Dispatch DuplicateEpisodeJob
                └── Queue (Redis)
                     └── Worker processes duplication in batches
```

Immediate API response:

```json
{
  "message": "Duplication has started",
  "duplication_id": "uuid",
  "status": "pending"
}
```

---

## Tracking Tables

This structure provides full visibility over the duplication lifecycle, enabling us to track the current status, the source and target episodes, the progression of the process, and capture any errors during execution.

### episode_duplications

Tracks the lifecycle of the duplication:

```php
Schema::create('episode_duplications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('source_episode_id')->constrained('episodes');
    $table->foreignId('target_episode_id')->nullable()->constrained('episodes');

    $table->string('status'); // pending, processing, completed, failed
    $table->unsignedInteger('progress')->default(0);
    $table->json('metadata')->nullable();
    $table->text('error_message')->nullable();

    $table->timestamps();
    $table->timestamp('finished_at')->nullable();
});
```

### duplication_mappings

Ensures idempotency and safe retries:

```php
Schema::create('duplication_mappings', function (Blueprint $table) {
    $table->id();
    $table->uuid('duplication_id');

    $table->string('entity_type');
    $table->unsignedBigInteger('old_id');
    $table->unsignedBigInteger('new_id');

    $table->timestamps();

    $table->unique(['duplication_id', 'entity_type', 'old_id']);
});
```

---

## Controller

```php
public function duplicate(Episode $episode): JsonResponse
{
    $duplication = EpisodeDuplication::create([
        'id' => Str::uuid(),
        'source_episode_id' => $episode->id,
        'status' => 'pending',
        'progress' => 0,
    ]);

    DuplicateEpisodeJob::dispatch($duplication->id)
        ->onQueue('episode-duplications');

    return response()->json([
        'message' => 'Duplication has started',
        'duplication_id' => $duplication->id,
        'status' => $duplication->status,
    ], 202);
}
```

Status endpoint:

```php
public function status(EpisodeDuplication $duplication): JsonResponse
{
    return response()->json([
        'id' => $duplication->id,
        'status' => $duplication->status,
        'progress' => $duplication->progress,
        'target_episode_id' => $duplication->target_episode_id,
        'error_message' => $duplication->error_message,
    ]);
}
```

---

## Job Implementation

```php
class DuplicateEpisodeJob implements ShouldQueue
{
    public int $tries = 5;
    public int $timeout = 900;
    public int $backoff = 60;

    public function __construct(public string $duplicationId) {}

    public function handle(EpisodeDuplicationService $service): void
    {
        $duplication = EpisodeDuplication::findOrFail($this->duplicationId);

        try {
            $duplication->update(['status' => 'processing']);

            Log::info('Episode duplication started', [
                'duplication_id' => $duplication->id,
            ]);

            $service->duplicate($duplication);

            $duplication->update([
                'status' => 'completed',
                'progress' => 100,
                'finished_at' => now(),
            ]);

        } catch (Throwable $e) {
            $duplication->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Episode duplication failed', [
                'duplication_id' => $duplication->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

The job is executed asynchronously by Laravel queue workers.
Redis is used strictly as the queue driver, allowing fast job dispatching and efficient background processing.

---

## Transactions Strategy

I avoid using a single long-running transaction.

Instead, I use short-lived transactions per batch, which:

* reduce lock duration
* prevent deadlocks
* improve scalability
* allow partial recovery

---

## Batch Duplication

Each level is duplicated independently using chunking:

```php
Part::where('episode_id', $sourceEpisode->id)
    ->orderBy('id')
    ->chunkById(500, function ($parts) use ($targetEpisode, $duplication) {
        DB::transaction(function () use ($parts, $targetEpisode, $duplication) {
            foreach ($parts as $part) {
                if ($this->alreadyDuplicated($duplication, 'part', $part->id)) {
                    continue;
                }

                $newPart = $part->replicate();
                $newPart->episode_id = $targetEpisode->id;
                $newPart->save();

                $this->saveMapping($duplication, 'part', $part->id, $newPart->id);
            }
        });
    });
```

Same logic applies to all levels:
Parts → Articles → Blocks → BlockFields → Medias

---

## Idempotency & Failure Recovery

To ensure safe retries, each duplicated entity is tracked in `duplication_mappings`.

Before duplication, the system checks whether the entity was already processed.
If not, it duplicates and stores the mapping.

A unique constraint guarantees consistency and prevents race conditions.

This ensures that even if:

* a worker crashes
* a job is retried
* the process is interrupted

the duplication can safely resume without creating duplicate data.

---

## Medias Handling

If multiple episodes can reference the same S3 file, I only duplicate the database record:

```php
$newMedia = $media->replicate();
$newMedia->block_id = $newBlockId;
$newMedia->save();
```

This approach is fast and avoids unnecessary file duplication.

---

## Impact on Other Users

This design minimizes impact by:

* avoiding long HTTP requests
* using background processing
* relying on short-lived transactions

The source episode remains readable and editable during duplication.

The duplicated episode is based on a snapshot at the start of the process.
Changes made afterward are not included.

This avoids long locks and keeps the platform responsive.

---

## Observability

Observability is essential for asynchronous processes.

* Structured logs (Laravel Log) track each step
* The `episode_duplications` table provides real-time progress
* Metrics provide system-level visibility

Key metrics:

* duplication_started
* duplication_completed
* duplication_failed
* duplication_duration

These metrics help monitor system health, detect regressions, and identify bottlenecks.

---

## Conclusion

This solution provides a scalable, resilient, and production-ready approach to duplicating complex hierarchical data.

It ensures:

* asynchronous processing
* controlled database usage
* idempotency and safe retries
* minimal impact on other users
* compatibility with AWS infrastructure

The system can efficiently handle both small and very large episodes while maintaining performance and data consistency.


---

## RUN 

```php
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan queue:work redis --queue=episode-duplications
```


The local development stack uses Docker with PHP, MySQL, Redis and phpMyAdmin. Redis is used as the Laravel queue driver, while MySQL remains the source of truth for duplication status, mappings and progress.
