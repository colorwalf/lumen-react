<?php
/**
 * Class TodoEventSubscriber
 *
 * @date      4/9/2016
 * @author    Mosufy <mosufy@gmail.com>
 * @copyright Copyright (c) Mosufy
 */

namespace App\Listeners;

use App\Events\TodoCreated;
use App\Events\TodoDeleted;
use App\Events\TodosDeleted;
use App\Events\TodoUpdated;
use App\Jobs\UpdateTodoSearchIndex;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Class TodoEventSubscriber
 *
 * Subscribes to Todos events.
 */
class TodoEventSubscriber
{
    protected $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Handle todos created events.
     *
     * @param TodoCreated $event
     */
    public function onTodoCreated($event)
    {
        // Add to Elasticsearch index
        if (env('QUEUE_SWITCH') == 'on') {
            dispatch((new UpdateTodoSearchIndex($event->todo, 'insert'))->onQueue('default')); // @codeCoverageIgnore
        } else {
            app(Dispatcher::class)->dispatchNow((new UpdateTodoSearchIndex($event->todo, 'insert')));
        }

        // Clear user's Todos caches
        $this->cache->forget('todosByUserId_' . $event->todo->user_id);
    }

    /**
     * Handle todos updated events.
     *
     * @param TodoUpdated $event
     */
    public function onTodoUpdated($event)
    {
        // Add to Elasticsearch index
        if (env('QUEUE_SWITCH') == 'on') {
            dispatch((new UpdateTodoSearchIndex($event->todo, 'update'))->onQueue('default')); // @codeCoverageIgnore
        } else {
            app(Dispatcher::class)->dispatchNow((new UpdateTodoSearchIndex($event->todo, 'update')));
        }

        // Clear user's Todos caches
        $this->cache->forget('todosByUserId_' . $event->todo->user_id);
    }

    /**
     * Handle todox deleted event.
     *
     * @param TodoDeleted $event
     */
    public function onTodoDeleted($event)
    {
        // Delete search index
        /*if (env('QUEUE_SWITCH') == 'on') {
            dispatch((new UpdateTodoSearchIndex($event->todo, 'delete'))->onQueue('default')); // @codeCoverageIgnore
        } else {
            app(Dispatcher::class)->dispatchNow((new UpdateTodoSearchIndex($event->todo, 'delete')));
        }*/

        // FIXME: Deleting queued job above does not seem to work. Will have to re-look into this.
        app(Dispatcher::class)->dispatchNow((new UpdateTodoSearchIndex($event->todo, 'delete')));

        // Clear user's Todos caches
        $this->cache->forget('todosByUserId_' . $event->todo->user_id);
    }

    /**
     * Handle todos deleted events.
     *
     * @param TodosDeleted $event
     */
    public function onTodosDeleted($event)
    {
        // FIXME: Deleting queued job above does not seem to work. Will have to re-look into this.
        foreach ($event as $todo) {
            app(Dispatcher::class)->dispatchNow((new UpdateTodoSearchIndex($todo, 'delete')));
        }

        // Clear user's Todos caches
        $this->cache->forget('todosByUserId_' . $event->todos[0]->user_id);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events)
    { // @codeCoverageIgnoreStart
        $events->listen(
            'App\Events\TodoCreated',
            'App\Listeners\TodoEventSubscriber@onTodoCreated'
        );

        $events->listen(
            'App\Events\TodoUpdated',
            'App\Listeners\TodoEventSubscriber@onTodoUpdated'
        );

        $events->listen(
            'App\Events\TodoDeleted',
            'App\Listeners\TodoEventSubscriber@onTodoDeleted'
        );

        $events->listen(
            'App\Events\TodosDeleted',
            'App\Listeners\TodoEventSubscriber@onTodosDeleted'
        );
    } // @codeCoverageIgnoreEnd
}
