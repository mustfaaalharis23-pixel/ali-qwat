<?php

namespace App\Console\Commands;

use App\Enums\OrderPaymentType;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use App\Models\Payoff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Times every request of the application and counts its queries.
 *
 * Every GET route (the admin application's API, the sales API, the print pages) and the API's POST routes are sent through a fresh copy
 * of the application, as a signed-in super user, the same way a real request is: the framework boots, the middleware runs,
 * the page renders. Writes run inside a transaction that is rolled back, so the database stays as it was.
 *
 * The first run of each request is a profile run: it records every query with the line of application code that ran it,
 * and every lazy load of a relation on a list of models (Model::preventLazyLoading), which is how an N+1 query shows.
 * The timed runs after it record only the time, the query count, and the memory.
 *
 * Run it on the benchmark dataset: DB_DATABASE=waqt_bench php artisan benchmark:requests --label=before
 */
#[Signature('benchmark:requests
    {--iterations=5 : Timed runs of every request, after one profile run}
    {--label= : Saves the results to storage/benchmarks/{label}.json}
    {--compare= : A saved label to compare against}
    {--filter= : Only the requests whose URI contains this text}
    {--repeat-threshold=3 : The same query this many times in one request is reported as repeated}')]
#[Description('Benchmark every request: time, query count, repeated (N+1) queries and lazy loads')]
class BenchmarkRequests extends Command
{
    /**
     * Route URIs that are not benchmarked: the framework's own endpoints, and pages that only redirect.
     *
     * @var array<int, string>
     */
    private const SKIPPED_URIS = ['up', 'locale/{locale}', 'admin/{path?}', 'sanctum/csrf-cookie', 'storage/{path}'];

    private User $user;

    private string $apiToken;

    public function handle(): int
    {
        ini_set('memory_limit', '-1');

        $this->user = User::query()->where('is_super', true)->where('is_active', true)->firstOrFail();
        $this->apiToken = $this->user->createToken('benchmark')->plainTextToken;

        try {
            $results = collect($this->requests())
                ->map(fn (array $request): array => $this->benchmark($request))
                ->values()
                ->all();
        } finally {
            $this->user->tokens()->where('name', 'benchmark')->delete();
        }

        $this->report($results);
        $this->save($results);

        return self::SUCCESS;
    }

    /**
     * Every request to benchmark, with its route parameters filled from the database.
     *
     * @return array<int, array{method: string, uri: string, url: string, api: bool, payload: array<string, mixed>}>
     */
    private function requests(): array
    {
        $requests = [];

        /** @var Route $route */
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();

            if (in_array($uri, self::SKIPPED_URIS, true) || Str::startsWith($uri, ['_boost', 'storage'])) {
                continue;
            }

            $isApi = Str::startsWith($uri, 'api/');
            $method = in_array('GET', $route->methods(), true) ? 'GET' : Arr::first($route->methods());

            if ($method !== 'GET' && ! $isApi) {
                continue;
            }

            if ($method !== 'GET' && in_array($uri, ['api/v1/logout'], true)) {
                continue;
            }

            if ($this->option('filter') && ! Str::contains($uri, $this->option('filter'))) {
                continue;
            }

            foreach ($this->parameterSets($route) as $parameters) {
                if ($parameters === null) {
                    continue;
                }

                $url = '/'.preg_replace_callback('/\{(\w+)\??\}/', fn (array $match): string => (string) ($parameters[$match[1]] ?? ''), $uri);

                $requests[] = [
                    'method' => $method,
                    'uri' => $uri.($parameters === [] ? '' : ' '.json_encode($parameters)),
                    'url' => rtrim($url, '/') ?: '/',
                    'api' => $isApi,
                    'payload' => $method === 'GET' ? [] : $this->payload($uri),
                ];
            }
        }

        return $requests;
    }

    /**
     * The parameter values a route is benchmarked with: one set for most routes, and both paper sizes of the invoice print.
     * Null means the route has a parameter there is no row for.
     *
     * @return array<int, array<string, int|string>|null>
     */
    private function parameterSets(Route $route): array
    {
        $names = $route->parameterNames();

        if ($names === []) {
            return [[]];
        }

        $values = [];

        foreach ($names as $name) {
            if ($name === 'format') {
                continue;
            }

            $id = $this->recordFor($route, $name);

            if ($id === null) {
                return [null];
            }

            $values[$name] = $id;
        }

        if (in_array('format', $names, true)) {
            return [[...$values, 'format' => 'a4'], [...$values, 'format' => 'pos']];
        }

        return [$values];
    }

    /**
     * The record a route parameter points at: the one with the most related rows, so a page shows as much as it can,
     * and a query run once per related row shows in the numbers.
     */
    private function recordFor(Route $route, string $parameter): ?int
    {
        $model = match (true) {
            $parameter === 'order' => Order::class,
            $parameter === 'payment' => CustomerPayment::class,
            $parameter === 'invoice' => Invoice::class,
            class_exists('App\\Models\\'.Str::studly($parameter)) => 'App\\Models\\'.Str::studly($parameter),
            $parameter === 'payoff' => Payoff::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        $richest = [
            Customer::class => 'orders',
            Supplier::class => 'invoices',
            Item::class => 'batches',
            Order::class => 'items',
            \App\Models\Container::class => 'invoices',
            GeneralDescription::class => 'specificDescriptions',
            CustomerPayment::class => 'orders',
        ][$model] ?? null;

        $query = $model::query();

        if ($model === Invoice::class) {
            $isGeneral = Str::contains($route->uri(), 'general-invoices');
            $query = $isGeneral ? $query->where('type', '!=', 'purchase') : $query->purchases();
            $richest = 'items';
        }

        if ($richest !== null && method_exists($model, $richest)) {
            $query->withCount($richest)->orderByDesc(Str::snake($richest).'_count');
        }

        return $query->orderByDesc((new $model)->getKeyName())->value((new $model)->getQualifiedKeyName());
    }

    /**
     * The body of an API write: a real sale of what is in stock, the label of a purchase invoice, the seeded credentials.
     *
     * @return array<string, mixed>
     */
    private function payload(string $uri): array
    {
        return match ($uri) {
            'api/v1/login' => ['user_id' => $this->user->id, 'password' => 'password', 'device_name' => 'benchmark'],
            'api/v1/purchase-invoices/receive' => ['barcode' => 'PI-'.Invoice::query()->purchases()->latest('id')->value('id')],
            'api/v1/orders' => $this->salePayload(),
            default => [],
        };
    }

    /**
     * A sale of one packet of each of the first items that still have packets left.
     *
     * @return array<string, mixed>
     */
    private function salePayload(): array
    {
        $lines = Item::query()->active()->where('available_quantity', '>', 0)->whereNotNull('pieces_per_packet')->limit(10)->get()
            ->map(function (Item $item): ?array {
                $type = collect($item->packetTypesLeft())->first();

                return $type === null ? null : [
                    'item_id' => $item->id,
                    'lens_power_id' => $type['lens_power_id'],
                    'packets' => 1,
                    'colors' => array_map(fn (array $color): array => ['color_id' => $color['id'], 'pieces' => $color['pieces']], $type['colors']),
                ];
            })
            ->filter()
            ->take(3)
            ->values()
            ->all();

        return [
            'customer_id' => Customer::query()->value('id'),
            'payment_type' => OrderPaymentType::Cash->value,
            'lines' => $lines,
        ];
    }

    /**
     * One profile run and the timed runs of a request.
     *
     * @param  array{method: string, uri: string, url: string, api: bool, payload: array<string, mixed>}  $request
     * @return array<string, mixed>
     */
    private function benchmark(array $request): array
    {
        $this->output->write(sprintf('  %-6s %-70s ', $request['method'], Str::limit($request['uri'], 68)));

        $profile = $this->measure($request, profile: true);
        $timings = [];

        foreach (range(1, (int) $this->option('iterations')) as $iteration) {
            $timings[] = $this->measure($request, profile: false)['ms'];
        }

        sort($timings);

        $repeated = collect($profile['queries'])
            ->groupBy('sql')
            ->filter(fn ($runs): bool => $runs->count() >= (int) $this->option('repeat-threshold'))
            ->map(fn ($runs, string $sql): array => [
                'sql' => Str::limit($sql, 300),
                'count' => $runs->count(),
                'sources' => $runs->pluck('source')->countBy()->sortDesc()->take(3)->all(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $result = [
            'method' => $request['method'],
            'uri' => $request['uri'],
            'status' => $profile['status'],
            'queries' => count($profile['queries']),
            'query_ms' => round(array_sum(array_column($profile['queries'], 'ms')), 1),
            'median_ms' => round($timings[intdiv(count($timings), 2)] ?? $profile['ms'], 1),
            'p95_ms' => round($timings[(int) floor((count($timings) - 1) * 0.95)] ?? $profile['ms'], 1),
            'memory_mb' => $profile['memory_mb'],
            'repeated_queries' => $repeated,
            'lazy_loads' => collect($profile['lazy_loads'])->countBy()->sortDesc()->all(),
            'error' => $profile['error'],
        ];

        $this->output->writeln(sprintf(
            '%s %4d q  %7.1f ms%s',
            $result['status'] < 400 ? "<info>{$result['status']}</info>" : "<error>{$result['status']}</error>",
            $result['queries'],
            $result['median_ms'],
            $repeated !== [] || $result['lazy_loads'] !== [] ? '  <comment>N+1</comment>' : '',
        ));

        return $result;
    }

    /**
     * Sends the request through a fresh copy of the application and measures it.
     *
     * @param  array{method: string, uri: string, url: string, api: bool, payload: array<string, mixed>}  $request
     * @return array{status: int, ms: float, memory_mb: float, queries: array<int, array{sql: string, ms: float, source: string}>, lazy_loads: array<int, string>, error: ?string}
     */
    private function measure(array $request, bool $profile): array
    {
        $queries = [];
        $lazyLoads = [];
        $user = $this->user;

        /** @var Application $app */
        $app = require base_path('bootstrap/app.php');

        $app->booted(function (Application $app) use ($request, $user, $profile, &$queries, &$lazyLoads): void {
            $app['db']->connection()->beginTransaction();

            if (! $request['api']) {
                $app['auth']->guard('web')->setUser($user->newQuery()->find($user->id));
            }

            if ($profile) {
                Model::preventLazyLoading();
                Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$lazyLoads): void {
                    $lazyLoads[] = class_basename($model).'::'.$relation.' ← '.$this->sourceLine();
                });
            }

            $app['db']->listen(function (QueryExecuted $query) use ($profile, &$queries): void {
                $queries[] = [
                    'sql' => $this->normalize($query->sql),
                    'ms' => $query->time,
                    'source' => $profile ? $this->sourceLine() : '',
                ];
            });
        });

        $httpRequest = Request::create($request['url'], $request['method'], [], [], [], [
            'HTTP_ACCEPT' => $request['api'] ? 'application/json' : 'text/html',
            'CONTENT_TYPE' => 'application/json',
            ...($request['api'] ? ['HTTP_AUTHORIZATION' => 'Bearer '.$this->apiToken] : []),
        ], $request['payload'] === [] ? null : json_encode($request['payload']));

        $kernel = $app->make(HttpKernel::class);
        $memoryBefore = memory_get_usage();
        $start = hrtime(true);
        $error = null;

        try {
            $response = $kernel->handle($httpRequest);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $error = Str::limit(strip_tags((string) ($response->exception ?? null)?->getMessage() ?: $response->getContent()), 300);
            }
        } catch (Throwable $exception) {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
            $error = $exception->getMessage();
        }

        $ms = (hrtime(true) - $start) / 1e6;
        $memory = round(max(0, memory_get_usage() - $memoryBefore) / 1048576, 1);

        try {
            $connection = $app['db']->connection();

            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            $kernel->terminate($httpRequest, $response ?? new Response);
            $app['db']->disconnect();
        } catch (Throwable) {
        }

        Model::preventLazyLoading(false);
        Model::handleLazyLoadingViolationUsing(null);
        $app->flush();
        $this->restoreConsoleApplication();

        return ['status' => $status, 'ms' => $ms, 'memory_mb' => $memory, 'queries' => $queries, 'lazy_loads' => $lazyLoads, 'error' => $error];
    }

    /**
     * Points the facades, the container, and the models back at this command's application after a request ran in its own.
     */
    private function restoreConsoleApplication(): void
    {
        Container::setInstance($this->laravel);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->laravel);
        Model::setConnectionResolver($this->laravel['db']);
        Model::setEventDispatcher($this->laravel['events']);
    }

    /**
     * The query with its literal lists collapsed, so the same query for another row groups with it.
     */
    private function normalize(string $sql): string
    {
        $sql = preg_replace('/\bin \((\?(, )?)+\)/i', 'in (?…)', $sql);
        $sql = preg_replace('/\b\d+\b/', 'N', $sql);
        $sql = preg_replace('/\bin \((N(, )?)+\)/i', 'in (N…)', $sql);

        return preg_replace('/\s+/', ' ', trim($sql));
    }

    /**
     * The first line of application code (not the framework's, and not this command) on the call stack.
     */
    private function sourceLine(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 80) as $frame) {
            $file = $frame['file'] ?? '';

            if ($file !== '' && ! str_contains($file, '/vendor/') && ! str_contains($file, 'BenchmarkRequests.php') && ! str_contains($file, 'storage/framework/views')) {
                return Str::after($file, base_path().'/').':'.($frame['line'] ?? 0);
            }
        }

        return 'vendor';
    }

    /**
     * The results as a table, the repeated queries under it, and the comparison with a saved run.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    private function report(array $results): void
    {
        $previous = $this->option('compare') ? $this->load($this->option('compare')) : [];

        $this->newLine();
        $this->table(
            ['Method', 'Request', 'Status', 'Queries', 'Median ms', 'p95 ms', 'Repeated', 'Lazy loads', ...($previous ? ['Δ queries', 'Δ ms'] : [])],
            array_map(function (array $result) use ($previous): array {
                $before = $previous[$result['method'].' '.$result['uri']] ?? null;

                return [
                    $result['method'],
                    Str::limit($result['uri'], 60),
                    $result['status'],
                    $result['queries'],
                    $result['median_ms'],
                    $result['p95_ms'],
                    array_sum(array_column($result['repeated_queries'], 'count')),
                    array_sum($result['lazy_loads']),
                    ...($previous ? [
                        $before ? sprintf('%d → %d', $before['queries'], $result['queries']) : 'new',
                        $before ? sprintf('%.0f → %.0f', $before['median_ms'], $result['median_ms']) : '',
                    ] : []),
                ];
            }, $results),
        );

        foreach ($results as $result) {
            if ($result['repeated_queries'] === [] && $result['lazy_loads'] === [] && $result['error'] === null) {
                continue;
            }

            $this->line("<comment>{$result['method']} {$result['uri']}</comment>");

            if ($result['error'] !== null) {
                $this->line('    <error>error</error> '.$result['error']);
            }

            foreach ($result['lazy_loads'] as $load => $count) {
                $this->line("    lazy ×{$count}  {$load}");
            }

            foreach ($result['repeated_queries'] as $query) {
                $this->line("    ×{$query['count']}  ".Str::limit($query['sql'], 140));
                $this->line('          '.collect($query['sources'])->map(fn (int $count, string $source): string => "{$source} ×{$count}")->implode(', '));
            }
        }

        $totals = fn (array $rows): array => [array_sum(array_column($rows, 'queries')), array_sum(array_column($rows, 'median_ms'))];
        [$queries, $ms] = $totals($results);
        $this->newLine();
        $this->info(sprintf('%d requests, %d queries, %.0f ms total median time.', count($results), $queries, $ms));

        if ($previous) {
            $matching = array_values(array_filter($previous, fn (array $row): bool => collect($results)->contains(fn (array $result): bool => $result['method'].' '.$result['uri'] === $row['method'].' '.$row['uri'])));
            [$beforeQueries, $beforeMs] = $totals($matching);
            $this->info(sprintf('Before (%s): %d queries, %.0f ms.', $this->option('compare'), $beforeQueries, $beforeMs));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function save(array $results): void
    {
        if (! $this->option('label')) {
            return;
        }

        $path = storage_path('benchmarks/'.$this->option('label').'.json');
        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, json_encode([
            'label' => $this->option('label'),
            'ran_at' => now()->toIso8601String(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'iterations' => (int) $this->option('iterations'),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Saved to {$path}");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(string $label): array
    {
        $data = json_decode((string) @file_get_contents(storage_path("benchmarks/{$label}.json")), true);

        return collect($data['results'] ?? [])->keyBy(fn (array $row): string => $row['method'].' '.$row['uri'])->all();
    }
}
