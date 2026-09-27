<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Tag;
use App\Modules\Products\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class TagManagementController
{
    public function store(Request $request, UniqueSlug $uniqueSlug): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('tags', 'slug')],
        ]);

        Tag::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $uniqueSlug->for('tags', $data['name']),
        ]);

        return to_route('admin.products.index')->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('tags', 'slug')->ignore($tag->id)],
        ]);

        $tag->update($data);

        return to_route('admin.products.index')->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        if ($tag->products()->exists()) {
            throw ValidationException::withMessages(['tag' => 'Quitá esta etiqueta de los productos antes de eliminarla.']);
        }

        $tag->delete();

        return to_route('admin.products.index')->with('success', 'Etiqueta eliminada.');
    }
}
