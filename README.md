# Episode Duplication Technical Test

This project demonstrates a scalable and resilient approach to duplicate a deeply nested Episode structure in a Laravel application.

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

Technical Stack
Laravel
PHP 8.3
MySQL 8
Redis
Laravel Queues
Docker
phpMyAdmin
PHPUnit / Feature tests

Redis is used only as the queue driver.
The database remains the source of truth for duplication status, mappings, progress and errors.

Architecture
User
 └── POST /api/episodes/{id}/duplicate
      └── Create episode_duplications record
           └── Dispatch DuplicateEpisodeJob
                └── Redis Queue
                     └── Worker processes duplication in batches

The HTTP request only starts the duplication process.
The actual duplication is handled asynchronously by a queue worker.

Main Concepts

This implementation focuses on:

asynchronous processing
Redis queue usage
batch processing with chunkById
short-lived database transactions
idempotency
safe retries
progress tracking
structured logs
failure recovery
minimal impact on other users
Database Tracking
episode_duplications

Tracks the duplication lifecycle:

source episode
target episode
status
progress
metadata
error message
timestamps
duplication_mappings

Stores old ID to new ID mappings.

This makes the duplication idempotent and retry-safe.

Example:

old part ID 1 → new part ID 24
old article ID 7 → new article ID 41

If a job is retried, already duplicated entities are skipped.

Docker Installation
1. Clone the project
git clone <repository-url>
cd episode-duplication-technical-test
2. Copy environment file
cp .env.example .env
3. Configure .env
APP_NAME="Episode Duplication"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=episode_duplication
DB_USERNAME=episode_user
DB_PASSWORD=episode_pass

QUEUE_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
4. Start Docker
docker compose up -d --build
5. Install dependencies
docker compose exec app composer install
6. Generate app key
docker compose exec app php artisan key:generate
7. Run migrations and seeders
docker compose exec app php artisan migrate:fresh --seed
Services
Service	URL
Laravel API	http://localhost:8000
phpMyAdmin	http://localhost:8081
MySQL from host	127.0.0.1:3307
MySQL inside Docker	mysql:3306
Redis inside Docker	redis:6379

phpMyAdmin credentials:

Server: mysql
Username: episode_user
Password: episode_pass
Database: episode_duplication
Queue Worker

The worker is defined in Docker and processes jobs from Redis.

To view worker logs:

docker compose logs -f worker

To manually run the worker:

docker compose exec app php artisan queue:work redis --queue=episode-duplications
API Endpoints
Start duplication
POST /api/episodes/{episode}/duplicate

Example:

curl -X POST http://localhost:8000/api/episodes/1/duplicate

Response:

{
  "message": "Duplication has started",
  "duplication_id": "uuid",
  "status": "pending"
}
Check duplication status
GET /api/episode-duplications/{duplication}

Example:

curl http://localhost:8000/api/episode-duplications/{duplication_id}

Response example:

{
  "id": "uuid",
  "status": "completed",
  "progress": 100,
  "target_episode_id": 2,
  "error_message": null
}
Fixtures / Seeders

The project includes seeders to generate realistic nested data.

Generated structure example:

Episodes
 └── Parts
      └── Articles
           └── Blocks
                ├── BlockFields
                └── Medias

Run fixtures:

docker compose exec app php artisan migrate:fresh --seed

This allows testing the duplication flow with real nested data.

Tests

Feature tests are included to validate:

duplication job dispatching
status endpoint
job execution
idempotency behavior

Run tests:

docker compose exec app php artisan test

If needed, clear config before running tests:

docker compose exec app php artisan config:clear
Manual Validation Flow
1. Seed the database
docker compose exec app php artisan migrate:fresh --seed
2. Check that an episode exists
docker compose exec app php artisan tinker
\App\Models\Episode::first();
3. Start duplication
curl -X POST http://localhost:8000/api/episodes/1/duplicate
4. Check worker logs
docker compose logs -f worker
5. Check duplication status
curl http://localhost:8000/api/episode-duplications/{duplication_id}
6. Verify database tables

In phpMyAdmin, check:

episodes
episode_duplications
duplication_mappings
parts
articles
blocks
block_fields
medias

Expected result:

a new episode is created
nested data is duplicated
mappings are stored
duplication status becomes completed
progress becomes 100
Idempotency

The duplication process is designed to be retry-safe.

Before duplicating an entity, the system checks the duplication_mappings table.

If the entity has already been duplicated, it is skipped.

This protects the system if:

a worker crashes
a job is retried
the process is interrupted
the same step is executed again

A unique constraint prevents duplicate mappings:

duplication_id + entity_type + old_id
Transactions

The system avoids one large transaction.

Instead, it uses short-lived transactions per batch.

This approach:

reduces database lock duration
limits deadlock risks
improves scalability
allows partial recovery
avoids blocking other users for too long
Impact on Other Users

The source episode remains readable and editable during duplication.

The duplicated episode is created with a temporary status:

duplicating

Once the process is complete, the target episode becomes:

draft

The duplication is based on the source data at the time the process starts.
Changes made afterward are not included in the current duplication.

This avoids long locks and keeps the platform responsive.

Observability

The system provides observability through:

structured Laravel logs
duplication status in database
progress tracking
error storage
worker logs

Useful metrics to monitor in a production environment:

duplication_started
duplication_completed
duplication_failed
duplication_duration

These metrics help detect:

failures
performance regressions
overloaded workers
long-running duplications
Redis Usage

Redis is used strictly as the Laravel queue driver.

QUEUE_CONNECTION=redis

Redis is not used to store critical duplication state.

The database remains responsible for:

duplication status
progress
mappings
errors
final consistency
Production Considerations

For a production environment, the same architecture could be extended with:

AWS SQS instead of Redis for more durable queues
AWS CloudWatch for logs and metrics
AWS S3 for media storage
horizontal scaling of workers
alerting on failed duplications
queue monitoring with Laravel Horizon if Redis is used
Useful Commands

Start containers:

docker compose up -d

Stop containers:

docker compose down

Rebuild containers:

docker compose up -d --build

Run migrations:

docker compose exec app php artisan migrate

Fresh migration with fixtures:

docker compose exec app php artisan migrate:fresh --seed

Run tests:

docker compose exec app php artisan test

View app logs:

docker compose logs -f app

View worker logs:

docker compose logs -f worker

List routes:

docker compose exec app php artisan route:list

Clear config:

docker compose exec app php artisan config:clear
Conclusion

This solution provides a production-oriented approach to duplicating complex hierarchical data.

It ensures:

asynchronous processing
controlled database usage
idempotency
safe retries
progress tracking
failure visibility
minimal impact on other users

The system can handle both small and large episodes while maintaining performance and data consistency.
