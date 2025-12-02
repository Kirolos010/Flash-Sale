# Flash-Sale Checkout API

A Laravel 12 API for handling flash sales with high concurrency, temporary stock holds, and idempotent payment webhooks. Built to prevent overselling under heavy load while maintaining data correctness.

## Features

- **Product Management**: Get product details with accurate real-time available stock
- **Temporary Holds**: Create 2-minute reservations that reduce available stock
- **Order Creation**: Convert valid holds into orders
- **Payment Webhooks**: Idempotent webhook processing with out-of-order delivery support
- **Concurrency Safety**: Row-level locking prevents overselling
- **Automatic Expiry**: Background job processes expired holds and releases stock
- **Caching**: Fast reads with cache invalidation on stock changes

## Assumptions and Invariants

### Core Invariants

1. **Stock Integrity**: Total stock = available stock + active holds + paid orders
2. **Hold Uniqueness**: Each hold can only be used once to create an order
3. **Hold Expiry**: Holds expire after 2 minutes and automatically release stock
4. **Webhook Idempotency**: Same `idempotency_key` processed only once
5. **Order States**: Orders transition: `pending` → `paid` or `cancelled`

### Design Decisions

- **Row-Level Locking**: Uses `lockForUpdate()` to prevent race conditions
- **Cache Strategy**: Stock cache invalidated on any stock-affecting operation
- **Transaction Retries**: Deadlock retry up to 5 times
- **Background Processing**: Expired holds processed via scheduled job/command
- **Webhook Storage**: Webhooks stored even if order doesn't exist yet (out-of-order safety)

## Installation

### Prerequisites

- PHP 8.2+
- MySQL 5.7+ (or MariaDB equivalent)
- Composer
- Node.js & NPM (for frontend assets, optional)

### Setup

1. **Clone and install dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure database in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=flash_sale
   DB_USERNAME=root
   DB_PASSWORD=
   
   CACHE_DRIVER=database  # or file, redis, memcached
   QUEUE_CONNECTION=database
   ```

4. **Run migrations and seed:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Start the server:**
   ```bash
   php artisan serve
   ```

   Or use the dev script:
   ```bash
   composer run dev
   ```

## API Endpoints

### 1. Get Product

**GET** `/api/products/{id}`

Returns product details including accurate available stock.

**Response:**
```json
{
  "id": 1,
  "name": "Flash Sale Product",
  "sku": "FLASH-001",
  "price": "99.99",
  "stock": 100,
  "available_stock": 95
}
```

### 2. Create Hold

**POST** `/api/holds`

Creates a temporary 2-minute reservation on product stock.

**Request:**
```json
{
  "product_id": 1,
  "qty": 5
}
```

**Response (201):**
```json
{
  "hold_id": 123,
  "expires_at": "2024-01-01T12:02:00Z"
}
```

**Error (409):**
```json
{
  "error": "Insufficient stock available"
}
```

### 3. Create Order

**POST** `/api/orders`

Creates an order from a valid, unexpired hold.

**Request:**
```json
{
  "hold_id": 123
}
```

**Response (201):**
```json
{
  "id": 456,
  "hold_id": 123,
  "product_id": 1,
  "quantity": 5,
  "total_amount": "499.95",
  "status": "pending",
  "created_at": "2024-01-01T12:00:00Z"
}
```

**Errors (409):**
- `"Hold has expired"`
- `"Hold has already been used"`
- `"Order already exists for this hold"`

### 4. Payment Webhook

**POST** `/api/payments/webhook`

Processes payment result with idempotency. Safe to receive multiple times.

**Request:**
```json
{
  "idempotency_key": "payment-abc123",
  "status": "success",
  "order_id": 456,
  "payload": {}
}
```

**Response (200):**
```json
{
  "message": "Webhook processed successfully",
  "webhook": {
    "id": 789,
    "idempotency_key": "payment-abc123",
    "order_id": 456,
    "status": "success",
    "created_at": "2024-01-01T12:00:00Z"
  }
}
```

**Duplicate Response (200):**
```json
{
  "message": "Webhook already processed",
  "webhook": { ... }
}
```

## Running Tests

**Note**: Tests require a database. By default, tests use MySQL. Ensure your `.env` or `phpunit.xml` is configured with a test database.

Create a test database:
```sql
CREATE DATABASE flash_sale_test;
```

Run all tests:
```bash
php artisan test
```

Run specific test suite:
```bash
php artisan test --filter ConcurrencyTest
php artisan test --filter HoldExpiryTest
php artisan test --filter PaymentWebhookTest
```

### Test Coverage

- ✅ **Concurrency**: Parallel hold attempts at stock boundary (no oversell)
- ✅ **Hold Expiry**: Expired holds release availability
- ✅ **Webhook Idempotency**: Same key processed only once
- ✅ **Out-of-Order Webhooks**: Webhook arriving before order creation

## Background Processing

### Process Expired Holds

Run manually:
```bash
php artisan holds:process-expired
```

Schedule in `app/Console/Kernel.php` or via cron:
```php
$schedule->command('holds:process-expired')->everyMinute();
```

Or use Laravel's task scheduler:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Logging and Metrics

### Logs Location

- **Application logs**: `storage/logs/laravel.log`
- **View logs in real-time**: `php artisan pail` (if using Laravel Pail)

### Key Log Events

- **Stock Operations**: Stock calculations, reservations, releases
- **Hold Processing**: Hold creation, expiry, usage
- **Webhook Processing**: Webhook received, duplicate detection, order updates
- **Concurrency**: Deadlock retries, contention metrics

### View Logs

```bash
# Real-time logs
php artisan pail

# Or tail the log file
tail -f storage/logs/laravel.log
```

## Architecture

### Concurrency Handling

- **Row-Level Locking**: `lockForUpdate()` on product/order/hold rows
- **Transaction Isolation**: All stock-affecting operations in transactions
- **Deadlock Retry**: Automatic retry up to 5 times on deadlock
- **Cache Invalidation**: Stock cache cleared on any stock change

### Stock Calculation

Available stock = Total stock - Sum of active holds

Active holds = Non-expired (`expires_at > now()`) AND unused (`is_used = false`)

### Webhook Idempotency

1. Check for existing webhook by `idempotency_key`
2. If exists, return existing result (idempotent)
3. If new, create webhook record
4. Process order update if order exists
5. If order doesn't exist yet, store webhook for later processing

### Hold Expiry

1. Background job finds expired, unused holds
2. Locks hold row to prevent double-processing
3. Marks hold as used
4. Invalidates stock cache (availability automatically recalculated)

## Performance Considerations

- **Caching**: Product stock cached for 60 seconds, invalidated on changes
- **Database Indexes**: Indexed on `product_id`, `expires_at`, `is_used` for fast queries
- **Batch Processing**: Expired holds processed in chunks of 100
- **Connection Pooling**: Use connection pooling in production

## Troubleshooting

### Stock Not Updating

- Check cache: `php artisan cache:clear`
- Verify hold expiry job is running
- Check logs for deadlock/transaction errors

### Webhook Not Processing

- Verify `idempotency_key` is unique
- Check if order exists
- Review logs for webhook processing errors

### Deadlocks

- Normal under high concurrency
- System automatically retries (up to 5 times)
- Monitor deadlock frequency in logs

## License

MIT License
