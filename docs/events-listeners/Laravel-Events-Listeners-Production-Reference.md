# Laravel Events & Listeners — Production & Advanced Patterns Reference

A companion reference to the Implementation Guide. Covers event subscribers, broadcasting, transaction handling, testing strategies, and advanced patterns.

---

## Table of Contents

1. [Event Subscribers](#1-event-subscribers)
2. [Stopping Event Propagation](#2-stopping-event-propagation)
3. [Transaction-Aware Events](#3-transaction-aware-events)
4. [Broadcasting (Real-Time Events)](#4-broadcasting-real-time-events)
5. [Queued Listener Management](#5-queued-listener-management)
6. [Testing Strategies](#6-testing-strategies)
7. [Performance Considerations](#7-performance-considerations)
8. [Error Handling](#8-error-handling)
9. [Event Sourcing Concepts](#9-event-sourcing-concepts)
10. [Best Practices](#10-best-practices)

---

## 1. Event Subscribers

Subscribers are classes that can listen to multiple events, keeping related listeners organized.

### Creating a Subscriber

```php
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\OrderShipped;
use App\Events\OrderDelivered;
use App\Events\OrderCancelled;
use Illuminate\Events\Dispatcher;

class OrderEventSubscriber
{
    /**
     * Handle order placed events.
     */
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        // Send confirmation, update inventory, etc.
    }

    /**
     * Handle order shipped events.
     */
    public function handleOrderShipped(OrderShipped $event): void
    {
        // Send shipping notification
    }

    /**
     * Handle order delivered events.
     */
    public function handleOrderDelivered(OrderDelivered $event): void
    {
        // Request review, update status
    }

    /**
     * Handle order cancelled events.
     */
    public function handleOrderCancelled(OrderCancelled $event): void
    {
        // Refund, restore inventory
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'handleOrderPlaced',
            OrderShipped::class => 'handleOrderShipped',
            OrderDelivered::class => 'handleOrderDelivered',
            OrderCancelled::class => 'handleOrderCancelled',
        ];
    }
}
```

### Registering Subscribers

In `AppServiceProvider`:

```php
use App\Listeners\OrderEventSubscriber;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::subscribe(OrderEventSubscriber::class);
}
```

### When to Use Subscribers

| Use Case                      | Benefit                     |
| ----------------------------- | --------------------------- |
| Related events for one domain | Organized in one file       |
| Shared dependencies           | Single constructor          |
| Complex event handling logic  | Methods can call each other |

---

## 2. Stopping Event Propagation

Prevent subsequent listeners from receiving an event.

### Return False

```php
public function handle(OrderPlaced $event): bool
{
    if ($event->order->is_fraudulent) {
        // Stop other listeners from processing
        return false;
    }

    // Process normally
    $this->process($event);

    return true; // Continue to other listeners
}
```

### Use Cases

- Fraud detection (stop order processing)
- Validation listeners (halt if invalid)
- Access control (prevent unauthorized actions)

---

## 3. Transaction-Aware Events

Handle events that depend on database transactions.

### The Problem

```php
DB::transaction(function () {
    $order = Order::create([...]);

    // Event dispatched, but transaction might roll back!
    event(new OrderPlaced($order));

    // If this throws, transaction rolls back
    // but listeners might have already sent emails
    $this->chargePayment($order);
});
```

### Solution: afterCommit

On individual listeners:

```php
class SendOrderConfirmation implements ShouldQueue
{
    public $afterCommit = true;

    public function handle(OrderPlaced $event): void
    {
        // Only runs if transaction committed
    }
}
```

On queue connection (global):

```php
// config/queue.php
'redis' => [
    'after_commit' => true,
],
```

### Manual Transaction Events

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $order = Order::create([...]);
    $this->chargePayment($order);

    // Store event to dispatch later
    DB::afterCommit(function () use ($order) {
        event(new OrderPlaced($order));
    });
});
```

---

## 4. Broadcasting (Real-Time Events)

Send events to frontend via WebSockets.

### Setup

Install broadcasting:

```bash
php artisan install:broadcasting
```

Configure driver in `.env`:

```bash
BROADCAST_CONNECTION=pusher
# or reverb, redis, etc.
```

### Broadcastable Event

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    /**
     * Channels to broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'updated_at' => $this->order->updated_at->toISOString(),
        ];
    }

    /**
     * Event name on frontend.
     */
    public function broadcastAs(): string
    {
        return 'order.updated';
    }
}
```

### Channel Types

| Type     | Class             | Use Case                 |
| -------- | ----------------- | ------------------------ |
| Public   | `Channel`         | Anyone can listen        |
| Private  | `PrivateChannel`  | Authenticated users only |
| Presence | `PresenceChannel` | Track who's listening    |

### Channel Authorization

In `routes/channels.php`:

```php
use App\Models\Order;

Broadcast::channel('orders.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);
    return $order && $order->user_id === $user->id;
});
```

### Frontend (JavaScript)

```javascript
Echo.private(`orders.${userId}`).listen(".order.updated", (event) => {
    console.log("Order updated:", event.order_id, event.status);
    updateOrderUI(event);
});
```

### Broadcast Conditionally

```php
public function broadcastWhen(): bool
{
    return $this->order->status !== 'draft';
}
```

### Queue Broadcast

```php
class OrderStatusUpdated implements ShouldBroadcast
{
    public $connection = 'redis';
    public $queue = 'broadcasts';
}

// Or broadcast later
class OrderStatusUpdated implements ShouldBroadcastNow
{
    // Broadcasts synchronously
}
```

---

## 5. Queued Listener Management

### Listener Properties

```php
class ProcessOrder implements ShouldQueue
{
    /**
     * Queue connection
     */
    public $connection = 'redis';

    /**
     * Queue name
     */
    public $queue = 'orders';

    /**
     * Retry attempts
     */
    public $tries = 5;

    /**
     * Timeout in seconds
     */
    public $timeout = 120;

    /**
     * Backoff in seconds
     */
    public $backoff = [60, 300, 900];

    /**
     * Unique for duration
     */
    public $uniqueFor = 3600;

    /**
     * Wait for DB commit
     */
    public $afterCommit = true;
}
```

### Conditional Queueing

```php
/**
 * Determine if listener should be queued.
 */
public function shouldQueue(OrderPlaced $event): bool
{
    return $event->order->total > 1000;
}
```

### Delay Listener

```php
/**
 * Delay before processing.
 */
public function withDelay(OrderPlaced $event): int
{
    return $event->order->is_priority ? 0 : 60;
}
```

### Handle Failure

```php
/**
 * Handle listener failure.
 */
public function failed(OrderPlaced $event, Throwable $exception): void
{
    Log::error('Order processing failed', [
        'order_id' => $event->order->id,
        'error' => $exception->getMessage(),
    ]);

    // Notify admin
    Notification::route('slack', config('services.slack.alerts'))
        ->notify(new ListenerFailedNotification($event, $exception));
}
```

### Unique Listeners

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessPayment implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return 'payment:' . $this->event->order->id;
    }
}
```

---

## 6. Testing Strategies

### Fake All Events

```php
use Illuminate\Support\Facades\Event;

public function test_order_creation(): void
{
    Event::fake();

    $response = $this->post('/orders', $orderData);

    Event::assertDispatched(OrderPlaced::class);
    Event::assertNotDispatched(OrderCancelled::class);
}
```

### Fake Specific Events

```php
Event::fake([OrderPlaced::class]);

// Other events dispatch normally
event(new UserRegistered($user)); // This actually runs
```

### Assert Event Count

```php
Event::assertDispatchedTimes(OrderPlaced::class, 3);
```

### Assert Event Properties

```php
Event::assertDispatched(OrderPlaced::class, function ($event) {
    return $event->order->total === 99.99
        && $event->order->user_id === 1;
});
```

### Test Listener in Isolation

```php
public function test_send_confirmation_listener(): void
{
    Mail::fake();

    $order = Order::factory()->create();
    $event = new OrderPlaced($order);

    $listener = new SendOrderConfirmation();
    $listener->handle($event);

    Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($order) {
        return $mail->hasTo($order->user->email);
    });
}
```

### Test with Real Event Dispatch

```php
public function test_full_event_flow(): void
{
    // Don't fake - let events dispatch
    Mail::fake();

    $this->post('/orders', $orderData);

    // Listeners ran, check side effects
    Mail::assertSent(OrderConfirmationMail::class);
    $this->assertDatabaseHas('inventory_logs', [...]);
}
```

### Test Queued Listeners

```php
public function test_queued_listener_is_pushed(): void
{
    Queue::fake();

    event(new OrderPlaced($order));

    Queue::assertPushed(SendOrderConfirmation::class);
}
```

### Test Subscriber

```php
public function test_order_subscriber_handles_events(): void
{
    $subscriber = new OrderEventSubscriber();

    $placedEvent = new OrderPlaced(Order::factory()->create());
    $subscriber->handleOrderPlaced($placedEvent);

    // Assert side effects
}
```

---

## 7. Performance Considerations

### Sync vs Async Listeners

| Listener Type      | Use When                                       |
| ------------------ | ---------------------------------------------- |
| **Sync**           | Fast operations, must complete before response |
| **Async (Queued)** | Slow operations, can fail independently        |

### Reduce Payload Size

```php
// ❌ Bad - large model serialized
public function __construct(
    public Order $order // Includes relations, attributes
) {}

// ✅ Better - only what's needed
public function __construct(
    public int $orderId,
    public float $total
) {}

public function handle(): void
{
    $order = Order::find($this->orderId);
}
```

### Avoid N+1 in Listeners

```php
// ❌ Bad
public function handle(OrderPlaced $event): void
{
    foreach ($event->order->items as $item) {
        $item->product->decrement('stock', $item->quantity);
    }
}

// ✅ Good
public function handle(OrderPlaced $event): void
{
    $order = Order::with('items.product')->find($event->order->id);

    foreach ($order->items as $item) {
        $item->product->decrement('stock', $item->quantity);
    }
}
```

### Batch Similar Operations

```php
// ❌ Bad - multiple events
foreach ($orders as $order) {
    event(new OrderPlaced($order));
}

// ✅ Better - batch event
event(new OrdersBatchPlaced($orders));
```

### Listener Priority

Process order matters for sync listeners:

```php
// In AppServiceProvider
Event::listen(OrderPlaced::class, [
    ValidateInventory::class,      // First: validate
    ProcessPayment::class,          // Second: charge
    SendConfirmation::class,        // Third: notify
]);
```

---

## 8. Error Handling

### Listener Exceptions

By default, exceptions in sync listeners stop everything. For async:

```php
class ProcessPayment implements ShouldQueue
{
    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function handle(OrderPlaced $event): void
    {
        try {
            $this->chargeCard($event->order);
        } catch (PaymentFailedException $e) {
            // Don't retry - fail immediately
            $this->fail($e);
        }
    }

    public function failed(OrderPlaced $event, Throwable $e): void
    {
        $event->order->update(['payment_status' => 'failed']);

        // Notify customer
        Mail::to($event->order->user)->send(new PaymentFailedMail());
    }
}
```

### Graceful Degradation

```php
public function handle(OrderPlaced $event): void
{
    try {
        $this->syncToExternalCRM($event->order);
    } catch (ExternalServiceException $e) {
        // Log but don't fail - CRM sync isn't critical
        Log::warning('CRM sync failed', [
            'order_id' => $event->order->id,
            'error' => $e->getMessage(),
        ]);

        // Queue for later retry
        SyncOrderToCRM::dispatch($event->order)->delay(now()->addMinutes(30));
    }
}
```

### Global Event Error Handling

```php
// In AppServiceProvider
Event::listen('*', function ($eventName, $data) {
    Log::debug('Event fired', ['event' => $eventName]);
});

// Handle all failed queued listeners
Queue::failing(function ($connection, $job, $exception) {
    // Notify developers
});
```

---

## 9. Event Sourcing Concepts

Event sourcing stores all changes as a sequence of events.

### Basic Concept

```
Traditional:
  Database stores CURRENT state
  Order: { status: 'shipped' }

Event Sourcing:
  Database stores ALL events
  OrderCreated { ... }
  OrderPaid { ... }
  OrderShipped { ... }

  Current state = replay all events
```

### Simple Implementation

```php
// Store events
class OrderEvent extends Model
{
    protected $casts = ['payload' => 'array'];
}

// Record events instead of updating
class Order extends Model
{
    public function ship(): void
    {
        OrderEvent::create([
            'order_id' => $this->id,
            'type' => 'shipped',
            'payload' => [
                'shipped_at' => now(),
                'carrier' => 'ups',
            ],
        ]);

        event(new OrderShipped($this));
    }

    public function getEvents(): Collection
    {
        return OrderEvent::where('order_id', $this->id)
            ->orderBy('created_at')
            ->get();
    }
}
```

### Benefits

- Complete audit trail
- Can replay/rebuild state
- Debugging: see exactly what happened
- Analytics on historical events

### Libraries

For full event sourcing, consider:

- `spatie/laravel-event-sourcing`
- `prooph/event-sourcing`

---

## 10. Best Practices

### Naming Conventions

```
Events (past tense - something happened):
  UserRegistered
  OrderPlaced
  PaymentReceived
  PasswordReset

Listeners (action - what to do):
  SendWelcomeEmail
  UpdateInventory
  NotifyWarehouse
  CreateAuditLog
```

### Keep Events Immutable

```php
class OrderPlaced
{
    // Use readonly properties (PHP 8.1+)
    public function __construct(
        public readonly Order $order,
        public readonly Carbon $occurredAt
    ) {}
}
```

### Single Responsibility Listeners

```php
// ❌ Bad - listener does too much
class HandleOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        $this->sendEmail($event);
        $this->updateInventory($event);
        $this->notifyWarehouse($event);
        $this->createAuditLog($event);
    }
}

// ✅ Good - separate listeners
SendOrderConfirmation::class
UpdateInventory::class
NotifyWarehouse::class
CreateOrderAuditLog::class
```

### Event Versioning

When event structure changes:

```php
// Version in class name
class OrderPlacedV2
{
    public function __construct(
        public Order $order,
        public ?string $couponCode,  // New field
        public ?User $referrer       // New field
    ) {}
}

// Or version property
class OrderPlaced
{
    public int $version = 2;
}
```

### Document Event Contracts

```php
/**
 * Fired when a new order is successfully placed.
 *
 * Listeners:
 *   - SendOrderConfirmation (queued, emails)
 *   - UpdateInventory (queued, default)
 *   - NotifyWarehouse (queued, integrations)
 *   - CreateOrderAuditLog (sync)
 *
 * @property Order $order The newly created order
 */
class OrderPlaced
{
    // ...
}
```

---

## Quick Reference

### Event Class Structure

```php
class YourEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model
    ) {}
}
```

### Listener Class Structure

```php
class YourListener implements ShouldQueue
{
    public $connection = 'redis';
    public $queue = 'default';
    public $tries = 3;
    public $afterCommit = true;

    public function handle(YourEvent $event): void {}

    public function shouldQueue(YourEvent $event): bool {}

    public function failed(YourEvent $event, Throwable $e): void {}
}
```

### Broadcast Event Structure

```php
class YourBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array {}
    public function broadcastWith(): array {}
    public function broadcastAs(): string {}
    public function broadcastWhen(): bool {}
}
```

### Commands

| Command                                        | Purpose                   |
| ---------------------------------------------- | ------------------------- |
| `php artisan make:event Name`                  | Create event              |
| `php artisan make:listener Name`               | Create listener           |
| `php artisan make:listener Name --event=Event` | Create for specific event |
| `php artisan event:list`                       | List all mappings         |
| `php artisan install:broadcasting`             | Setup broadcasting        |

---

## See Also

- [Events Implementation Guide](Laravel-Events-Listeners-Guide.md) — Step-by-step implementation
- [Standard Jobs Guide](../standard-jobs/Laravel-Jobs-Guide.md) — Queued jobs
- [Scheduled Tasks Guide](../scheduled-tasks/Laravel-Scheduled-Tasks-Guide.md) — Cron automation
