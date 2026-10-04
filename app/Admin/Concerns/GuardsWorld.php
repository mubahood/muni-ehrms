<?php

namespace App\Admin\Concerns;

use App\Services\Scope;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource controllers over world-bound rows (users, departments, faculties):
 * opening, editing or deleting a row by its address is refused unless it
 * belongs to the viewer's world (real university or demo sandbox) and, for
 * people, to the viewer's scope. Grids already list only those rows; this
 * closes the door for anyone typing an id into the address bar.
 *
 * The using controller defines worldModel(): the Eloquent class it manages.
 */
trait GuardsWorld
{
    abstract protected function worldModel(): string;

    public function show($id, Content $content)
    {
        $this->guardWorld($id);

        return parent::show($id, $content);
    }

    public function edit($id, Content $content)
    {
        $this->guardWorld($id);

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        $this->guardWorld($id);

        return parent::update($id);
    }

    public function destroy($id)
    {
        $this->guardWorld($id);

        return parent::destroy($id);
    }

    /** @param  int|string  $ids  one id, or several comma-separated (batch delete) */
    protected function guardWorld($ids): void
    {
        $viewer = Admin::user();
        $class = $this->worldModel();
        foreach (array_filter(explode(',', (string) $ids)) as $id) {
            /** @var Model|null $row */
            $row = $class::find((int) $id);
            if (!$row) {
                continue; // laravel-admin answers "not found" itself
            }
            abort_if((bool) $row->is_demo !== $viewer->isDemo(), 404);
            if ($row instanceof \App\Models\User) {
                abort_unless(Scope::canSee($viewer, (int) $row->id), 403);
            }
        }
    }

    /** New rows belong to the creator's world. */
    protected static function stampWorld(Model $model): void
    {
        if (!$model->exists) {
            $model->is_demo = Admin::user()->isDemo();
        }
    }
}
