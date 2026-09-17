<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * A tree of checkboxes: one parent checkbox per group that selects all of its permissions, with the permissions nested under it.
 * The state is a flat list of the selected permission names.
 */
class PermissionTree extends Field
{
    protected string $view = 'filament.forms.components.permission-tree';

    /**
     * @var array<string, array{label: string, permissions: array<string, string>}>|Closure
     */
    protected array|Closure $groups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }

    /**
     * @param  array<string, array{label: string, permissions: array<string, string>}>|Closure  $groups
     */
    public function groups(array|Closure $groups): static
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public function getGroups(): array
    {
        return $this->evaluate($this->groups);
    }
}
