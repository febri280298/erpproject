<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD scaffold for the small reference tables (satuan, pajak, termin, kategori,
 * gudang, departemen, jabatan, jenis cuti).
 *
 * A subclass declares the model, its labels, the list columns and the form
 * fields; the shared `master.index` / `master.form` views render from those
 * declarations. Anything richer than that gets its own controller and views.
 *
 * Records are resolved by id rather than route-model binding, because the base
 * class cannot type-hint a concrete model.
 */
abstract class SimpleMasterController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $routeName;

    protected string $title;

    /** Permission subject, e.g. `uom` — actions are appended (`uom.create`). */
    protected string $permission;

    protected string $icon = 'ti ti-list';

    protected int $perPage = 20;

    /** Relations eager-loaded on the index page. */
    protected array $with = [];

    /** Relations counted on the index page (adds a `<relation>_count` attribute). */
    protected array $withCount = [];

    /** @return array<int,array{key:string,label:string,type?:string,class?:string,render?:callable}> */
    abstract protected function columns(): array;

    /** @return array<int,array<string,mixed>> */
    abstract protected function fields(?Model $record = null): array;

    /** @return array<string,mixed> */
    abstract protected function rules(?Model $record = null): array;

    public function index(Request $request): View
    {
        $records = $this->model::query()
            ->with($this->with)
            ->withCount($this->withCount)
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->query('status') === 'active'))
            ->orderBy($this->defaultSort())
            ->paginate($this->perPage)
            ->withQueryString();

        return view('master.index', [
            'records' => $records,
            'columns' => $this->columns(),
            'routeName' => $this->routeName,
            'title' => $this->title,
            'permission' => $this->permission,
            'icon' => $this->icon,
            'hasStatusFilter' => $this->hasStatusFilter(),
        ]);
    }

    public function create(): View
    {
        return view('master.form', [
            'record' => null,
            'fields' => $this->fields(),
            'routeName' => $this->routeName,
            'title' => $this->title,
            'action' => route("{$this->routeName}.store"),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $record = $this->model::create($this->transform($data, $request));
        $this->afterSave($record, $request);

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', "{$this->title} \"{$record->name}\" berhasil ditambahkan.");
    }

    public function edit(int $id): View
    {
        $record = $this->model::findOrFail($id);

        return view('master.form', [
            'record' => $record,
            'fields' => $this->fields($record),
            'routeName' => $this->routeName,
            'title' => $this->title,
            'action' => route("{$this->routeName}.update", $record),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $record = $this->model::findOrFail($id);

        $data = $request->validate($this->rules($record));
        $record->update($this->transform($data, $request, $record));
        $this->afterSave($record, $request);

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', "{$this->title} \"{$record->name}\" berhasil diperbarui.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $record = $this->model::findOrFail($id);
        $name = $record->name;

        if ($blocker = $this->deleteBlocker($record)) {
            return back()->with('error', $blocker);
        }

        $record->delete();

        return redirect()
            ->route("{$this->routeName}.index")
            ->with('success', "{$this->title} \"{$name}\" berhasil dihapus.");
    }

    /** Hook: massage validated input before it reaches the model. */
    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        return $data;
    }

    /** Hook: run after create/update (e.g. enforce a single default row). */
    protected function afterSave(Model $record, Request $request): void {}

    /** Return a message to refuse deletion, or null to allow it. */
    protected function deleteBlocker(Model $record): ?string
    {
        return null;
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function hasStatusFilter(): bool
    {
        return true;
    }
}
