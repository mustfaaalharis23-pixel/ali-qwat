<?php

namespace App\Permissions;

use App\Filament\Lenses\Lens;
use App\Policies\ModelPolicy;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use ReflectionMethod;

class PermissionDiscovery
{
    /**
     * Permissions of every model in app/Models and every page in app/Filament/Pages whose Policy extends ModelPolicy.
     *
     * @return array<class-string<Model|Page>, list<string>>
     */
    public function discoverInApp(): array
    {
        return $this->discover([
            ...$this->classesIn('Models', Model::class),
            ...$this->classesIn('Filament/Pages', Page::class),
        ]);
    }

    /**
     * The permission of every lens in app/Filament/Lenses.
     *
     * @return array<class-string<Lens>, string>
     */
    public function discoverLensesInApp(): array
    {
        return $this->discoverLenses($this->classesIn('Filament/Lenses', Lens::class));
    }

    /**
     * The permission of each given lens, one per lens, built from its uriKey.
     *
     * @param  list<class-string<Lens>>  $lenses
     * @return array<class-string<Lens>, string>
     */
    public function discoverLenses(array $lenses): array
    {
        return collect($lenses)
            ->mapWithKeys(fn (string $lens): array => [$lens => $lens::permission()])
            ->all();
    }

    /**
     * Permissions of the given models or pages: the basic ones, then the public methods written in each Policy.
     *
     * @param  list<class-string<Model|Page>>  $classes
     * @return array<class-string<Model|Page>, list<string>>
     */
    public function discover(array $classes): array
    {
        $permissions = [];

        foreach ($classes as $class) {
            $policy = Gate::getPolicyFor($class);

            if (! $policy instanceof ModelPolicy) {
                continue;
            }

            $abilities = [...$policy::basicAbilities(), ...$this->customAbilities($policy)];

            $permissions[$class] = array_map(
                fn (string $ability): string => PermissionName::for($policy::class, $ability),
                $abilities,
            );
        }

        return $permissions;
    }

    /**
     * The classes directly in the given folder of app/ that extend the given parent class.
     *
     * @template TParent of object
     *
     * @param  class-string<TParent>  $parent
     * @return list<class-string<TParent>>
     */
    private function classesIn(string $folder, string $parent): array
    {
        return collect(glob(app_path("{$folder}/*.php")) ?: [])
            ->map(fn (string $path): string => 'App\\'.str_replace('/', '\\', $folder).'\\'.pathinfo($path, PATHINFO_FILENAME))
            ->filter(fn (string $class): bool => is_subclass_of($class, $parent))
            ->values()
            ->all();
    }

    /**
     * Public methods declared in the Policy itself; inherited, protected, and private methods are not permissions.
     * A basic ability the Policy writes again, to add conditions to it, stays one permission and is not counted twice.
     *
     * @return list<string>
     */
    private function customAbilities(ModelPolicy $policy): array
    {
        $reflection = new ReflectionClass($policy);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $reflection->getName()
                && ! $method->isStatic()
                && ! str_starts_with($method->getName(), '__')
                && ! in_array($method->getName(), $policy::basicAbilities(), true))
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->values()
            ->all();
    }
}
