<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Shared create / store / show / edit / update / destroy for the admin
 * resources. Each child supplies its model, fields and validation rules and
 * writes its own index() (every list page has different filters).
 */
abstract class CrudController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Route name prefix, e.g. "admin.events". */
    abstract protected function routeBase(): string;

    abstract protected function label(): string;

    /**
     * Form fields. Each: name, label, type (text|number|date|time|email|password|
     * textarea|select|file|checkboxes), required, options, step.
     */
    abstract protected function fields(Model $item): array;

    abstract protected function rules(?Model $item = null): array;

    /** Request keys that are relations, not columns (synced in afterSave). */
    protected function relationKeys(): array
    {
        return [];
    }

    protected function imageFolder(): ?string
    {
        return null;
    }

    protected function prepare(array $data, Request $request, ?Model $item = null): array
    {
        return $data;
    }

    protected function afterSave(Model $item, Request $request): void
    {
    }

    protected function find($id): Model
    {
        $class = $this->model();

        return $class::findOrFail($id);
    }

    protected function indexUrl(): string
    {
        return route($this->routeBase() . '.index');
    }

    private function formView(Model $item, string $action, string $method)
    {
        return view('administrator.form', [
            'title' => ($item->exists ? 'Edit ' : 'Add ') . $this->label(),
            'fields' => $this->fields($item),
            'item' => $item,
            'action' => $action,
            'method' => $method,
            'backUrl' => $this->indexUrl(),
        ]);
    }

    private function persist(array $data, Request $request, ?Model $item = null): array
    {
        $data = Arr::except($data, $this->relationKeys());

        if ($folder = $this->imageFolder()) {
            unset($data['image']);

            if ($request->hasFile('image')) {
                if ($item && $item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $data['image'] = $request->file('image')->store($folder, 'public');
            }
        }

        return $this->prepare($data, $request, $item);
    }

    public function create()
    {
        $class = $this->model();

        return $this->formView(new $class, route($this->routeBase() . '.store'), 'POST');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $class = $this->model();
        $item = new $class;
        $item->fill($this->persist($data, $request));
        $item->save();
        $this->afterSave($item, $request);

        return redirect($this->indexUrl())
            ->with('success', $this->label() . ' created successfully.');
    }

    public function show($id)
    {
        $item = $this->find($id);
        $rows = [];

        foreach ($this->fields($item) as $field) {
            $value = $item->{$field['name']} ?? null;

            if ($field['type'] === 'password') {
                continue;
            }
            if ($field['type'] === 'checkboxes') {
                $value = collect($field['options'])
                    ->only($field['selected'] ?? [])
                    ->implode(', ');
            } elseif ($field['type'] === 'select') {
                $value = $field['options'][$value] ?? $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('M d, Y');
            }

            $rows[$field['label']] = $value;
        }

        return view('administrator.show', [
            'title' => $this->label() . ' #' . $item->getKey(),
            'rows' => $rows,
            'backUrl' => $this->indexUrl(),
            'editUrl' => route($this->routeBase() . '.edit', $item->getKey()),
        ]);
    }

    public function edit($id)
    {
        $item = $this->find($id);

        return $this->formView(
            $item,
            route($this->routeBase() . '.update', $item->getKey()),
            'PUT'
        );
    }

    public function update(Request $request, $id)
    {
        $item = $this->find($id);
        $data = $request->validate($this->rules($item));

        $item->fill($this->persist($data, $request, $item));
        $item->save();
        $this->afterSave($item, $request);

        return redirect($this->indexUrl())
            ->with('success', $this->label() . ' updated successfully.');
    }

    public function destroy($id)
    {
        $item = $this->find($id);

        if ($this->imageFolder() && $item->image) {
            Storage::disk('public')->delete($item->image);
        }

        $item->delete();

        return redirect($this->indexUrl())
            ->with('success', $this->label() . ' deleted.');
    }
}
