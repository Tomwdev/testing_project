# Laravel Events & Listeners — Implementation Guide

A step-by-step blueprint for implementing event-driven architecture using Laravel's event system.

---

## What are Events & Listeners?

**Events** represent something that happened in your application (e.g., "user registered", "order placed").

**Listeners** react to events and perform actions (e.g., "send welcome email", "notify warehouse").

This creates **decoupled architecture** — the code that triggers the event doesn't need to know what happens next.

---

## Why Use Events?

| Approach                   | Problem                                           |
| -------------------------- | ------------------------------------------------- |
| Direct calls in controller | Controller becomes bloated with side effects      |
| Calling multiple services  | Tight coupling, hard to modify                    |
| **Events & Listeners**     | Decoupled, testable, easy to add/remove behaviors |

### Example: User Registration

**Without Events (Coupled):**

```php
public function register(Request $request)
{
    $user = User::create($request->validated());

    // Controller knows about ALL side effects
    Mail::to($user)->send(new WelcomeMail($user));
    Newsletter::subscribe($user->email);
    Analytics::track('user_registered', $user);
    Slack::notify("New user: {$user->name}");
}
```

**With Events (Decoupled):**

```php
public function register(Request $request)
{
    $user = User::create($request->validated());

    // Controller just announces what happened
    event(new UserRegistered($user));
}
```

Now you can add/remove behaviors without touching the controller.

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    Something happens in your app                              │
│                    (User registers, Order placed, etc.)                       │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │ event(new UserRegistered($user))
                                   ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                              EVENT DISPATCHER                                 │
│                                                                               │
│   Finds all listeners registered for this event                              │
└──────────────────────────────────┬───────────────────────────────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────────┐
         ▼                         ▼                             ▼
┌─────────────────┐      ┌─────────────────┐          ┌─────────────────┐
│ SendWelcome     │      │ Subscribe       │          │ NotifySlack     │
│ Email           │      │ ToNewsletter    │          │                 │
│ (sync)          │      │ (queued)        │          │ (queued)        │
└─────────────────┘      └─────────────────┘          └─────────────────┘
         │                         │                             │
         ▼                         ▼                             ▼
   Runs immediately         Added to queue              Added to queue
```

---

## Files You'll Create

| File                             | Purpose                          |
| -------------------------------- | -------------------------------- |
| `app/Events/YourEvent.php`       | The event class (data container) |
| `app/Listeners/YourListener.php` | Reacts to the event              |

---

## Phase 1: Create the Event

```bash
php artisan make:event UserRegistered
```

This creates `app/Events/UserRegistered.php`.

### Step-by-Step

| Step    | Task                  | What to Do                         | Why                                 |
| ------- | --------------------- | ---------------------------------- | ----------------------------------- |
| **1.1** | Define constructor    | Accept relevant data (models, IDs) | Listeners need context              |
| **1.2** | Use public properties | `public User $user`                | Listeners access via `$event->user` |
| **1.3** | Keep events simple    | Only data, no logic                | Events are data transfer objects    |

### Event Template

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user
    ) {}
}
```

### Traits Explained

| Trait              | Purpose                                                  |
| ------------------ | -------------------------------------------------------- |
| `Dispatchable`     | Allows `UserRegistered::dispatch($user)` syntax          |
| `SerializesModels` | Properly serializes Eloquent models for queued listeners |

### Event with Multiple Properties

```php
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public User $customer,
        public ?string $couponCode = null
    ) {}
}
```

---

## Phase 2: Create the Listener

```bash
php artisan make:listener SendWelcomeEmail --event=UserRegistered
```

This creates `app/Listeners/SendWelcomeEmail.php`.

### Step-by-Step

| Step    | Task                         | What to Do                                      | Why                          |
| ------- | ---------------------------- | ----------------------------------------------- | ---------------------------- |
| **2.1** | Type-hint event              | `public function handle(UserRegistered $event)` | Access event data            |
| **2.2** | Access event data            | `$event->user`                                  | Get the data passed to event |
| **2.3** | Perform the action           | Send email, API call, etc.                      | The actual work              |
| **2.4** | Add `ShouldQueue` (optional) | `implements ShouldQueue`                        | Run asynchronously           |

### Synchronous Listener

Runs immediately when event is dispatched:

```php
<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }
}
```

### Queued Listener

Runs asynchronously via queue:

```php
<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    /**
     * The queue connection to use.
     */
    public $connection = 'redis';

    /**
     * The queue to use.
     */
    public $queue = 'emails';

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }
}
```

---

## Phase 3: Register the Event-Listener Mapping

Laravel needs to know which listeners respond to which events.

### Option A: Automatic Discovery (Recommended)

Laravel 11+ automatically discovers listeners. Just ensure:

1. Listeners are in `app/Listeners/`
2. The `handle()` method type-hints the event class

```php
// Laravel automatically detects this relationship
public function handle(UserRegistered $event): void
```

### Option B: Manual Registration

In `app/Providers/AppServiceProvider.php`:

```php
use App\Events\UserRegistered;
use App\Listeners\SendWelcomeEmail;
use App\Listeners\SubscribeToNewsletter;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(
        UserRegistered::class,
        SendWelcomeEmail::class
    );

    // Multiple listeners for same event
    Event::listen(
        UserRegistered::class,
        SubscribeToNewsletter::class
    );
}
```

### Option C: Closure Listeners

For simple cases:

```php
Event::listen(function (UserRegistered $event) {
    logger('User registered: ' . $event->user->email);
});
```

---

## Phase 4: Dispatch the Event

### From a Controller

```php
use App\Events\UserRegistered;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $user = User::create($request->validated());

        // Dispatch the event
        event(new UserRegistered($user));

        // OR using static method (if using Dispatchable trait)
        UserRegistered::dispatch($user);

        return redirect('/dashboard');
    }
}
```

### From a Model

```php
class User extends Authenticatable
{
    protected static function booted(): void
    {
        static::created(function (User $user) {
            event(new UserRegistered($user));
        });
    }
}
```

### From Anywhere

```php
event(new OrderPlaced($order, $customer, $couponCode));
```

---

## Phase 5: Configure Queued Listeners

### Listener Properties

```php
class SendWelcomeEmail implements ShouldQueue
{
    /**
     * Queue connection
     */
    public $connection = 'redis';

    /**
     * Queue name
     */
    public $queue = 'emails';

    /**
     * Delay before processing
     */
    public $delay = 60; // seconds

    /**
     * Max attempts
     */
    public $tries = 3;

    /**
     * Timeout
     */
    public $timeout = 120;

    /**
     * Backoff between retries
     */
    public $backoff = [10, 60, 300];
}
```

### Conditional Queuing

```php
public function shouldQueue(UserRegistered $event): bool
{
    return $event->user->wants_emails;
}
```

### After Commit

Wait for database transaction to commit:

```php
public $afterCommit = true;
```

---

## Complete Example

### Event

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
```

### Listeners

```php
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    public $queue = 'emails';

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user)
            ->send(new OrderConfirmation($event->order));
    }
}
```

```php
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\InventoryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateInventory implements ShouldQueue
{
    public function __construct(
        private InventoryService $inventory
    ) {}

    public function handle(OrderPlaced $event): void
    {
        foreach ($event->order->items as $item) {
            $this->inventory->decrement($item->product_id, $item->quantity);
        }
    }
}
```

```php
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyWarehouse implements ShouldQueue
{
    public $queue = 'integrations';

    public function handle(OrderPlaced $event): void
    {
        Http::post('https://warehouse.example.com/api/orders', [
            'order_id' => $event->order->id,
            'items' => $event->order->items->toArray(),
        ]);
    }
}
```

### Dispatching

```php
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        OrderPlaced::dispatch($order);

        return redirect()->route('orders.show', $order);
    }
}
```

---

## Dispatch Methods

| Method                                     | Description                      |
| ------------------------------------------ | -------------------------------- |
| `event(new Event($data))`                  | Dispatch event (helper function) |
| `Event::dispatch($data)`                   | Dispatch using static method     |
| `Event::dispatchIf($condition, $data)`     | Dispatch conditionally           |
| `Event::dispatchUnless($condition, $data)` | Dispatch unless condition        |

---

## Testing Events

### Fake Events

```php
use Illuminate\Support\Facades\Event;

public function test_user_registration_dispatches_event(): void
{
    Event::fake();

    $this->post('/register', $userData);

    Event::assertDispatched(UserRegistered::class);
}
```

### Assert Event Properties

```php
Event::assertDispatched(UserRegistered::class, function ($event) {
    return $event->user->email === 'test@example.com';
});
```

### Assert Listener Behavior

```php
public function test_welcome_email_sent_on_registration(): void
{
    Mail::fake();

    $user = User::factory()->create();
    $listener = new SendWelcomeEmail();

    $listener->handle(new UserRegistered($user));

    Mail::assertSent(WelcomeMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
}
```

### Fake Specific Events

```php
// Only fake these events, others dispatch normally
Event::fake([
    OrderPlaced::class,
    PaymentReceived::class,
]);
```

---

## Built-in Model Events

Eloquent models dispatch events automatically:

| Event       | When Fired                  |
| ----------- | --------------------------- |
| `retrieved` | After model fetched from DB |
| `creating`  | Before model created        |
| `created`   | After model created         |
| `updating`  | Before model updated        |
| `updated`   | After model updated         |
| `saving`    | Before create or update     |
| `saved`     | After create or update      |
| `deleting`  | Before model deleted        |
| `deleted`   | After model deleted         |
| `restoring` | Before soft-delete restored |
| `restored`  | After soft-delete restored  |

### Listen to Model Events

```php
// In AppServiceProvider
User::created(function (User $user) {
    event(new UserRegistered($user));
});
```

### Using $dispatchesEvents

```php
class Order extends Model
{
    protected $dispatchesEvents = [
        'created' => OrderPlaced::class,
        'updated' => OrderUpdated::class,
    ];
}
```

---

## Implementation Checklist

### Event

- [ ] Event created with `php artisan make:event`
- [ ] `Dispatchable` trait added
- [ ] `SerializesModels` trait added (if passing models)
- [ ] Constructor accepts necessary data
- [ ] Properties are public

### Listener

- [ ] Listener created with `php artisan make:listener`
- [ ] `handle()` method type-hints the event
- [ ] `ShouldQueue` implemented (if async)
- [ ] Queue properties configured (`$queue`, `$tries`, etc.)
- [ ] `$afterCommit` set if using transactions

### Registration

- [ ] Automatic discovery working (Laravel 11+)
- [ ] OR manually registered in `AppServiceProvider`

### Testing

- [ ] Events faked in tests
- [ ] Dispatch assertions made
- [ ] Listener logic tested directly

---

## When to Use Events vs Direct Calls

| Use Events When           | Use Direct Calls When        |
| ------------------------- | ---------------------------- |
| Multiple side effects     | Single, critical operation   |
| Side effects might change | Logic is stable              |
| Side effects are optional | Operation must succeed       |
| Want to decouple code     | Tight coupling is acceptable |
| Need async processing     | Must be synchronous          |

---

## Common Patterns

### Event for Each Model Action

```
UserRegistered
UserUpdatedProfile
UserChangedPassword
UserDeleted

OrderPlaced
OrderPaid
OrderShipped
OrderDelivered
OrderCancelled
```

### Event for Business Processes

```
SubscriptionStarted
SubscriptionRenewed
SubscriptionCancelled

PaymentSucceeded
PaymentFailed
RefundIssued
```

---

## Quick Reference

### Commands

| Command                                                    | Purpose                            |
| ---------------------------------------------------------- | ---------------------------------- |
| `php artisan make:event EventName`                         | Create event                       |
| `php artisan make:listener ListenerName`                   | Create listener                    |
| `php artisan make:listener ListenerName --event=EventName` | Create listener for specific event |
| `php artisan event:list`                                   | List all events and listeners      |

### Event Class

```php
class YourEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model
    ) {}
}
```

### Listener Class

```php
class YourListener implements ShouldQueue
{
    public $queue = 'default';
    public $tries = 3;

    public function handle(YourEvent $event): void
    {
        // React to event
    }

    public function failed(YourEvent $event, Throwable $e): void
    {
        // Handle failure
    }
}
```

---

## See Also

- [Events Production Reference](Laravel-Events-Listeners-Production-Reference.md) — Advanced patterns, subscribers, broadcasting
- [Standard Jobs Guide](../standard-jobs/Laravel-Jobs-Guide.md) — Queued jobs without events
- [Batch Jobs Guide](../batch-jobs/Laravel-Batch-Jobs-Guide.md) — Batch processing
