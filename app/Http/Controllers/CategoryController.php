<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', ['categories' => InventoryCategory::withCount('items')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = InventoryCategory::create($this->validated($request));
        AuditLogger::log('category.created', "Category \"{$category->name}\" created", $category);

        return back()->with('success', "Category \"{$category->name}\" added.");
    }

    public function update(Request $request, InventoryCategory $category): RedirectResponse
    {
        $old = $category->only(['name', 'description']);
        $category->update($this->validated($request, $category));
        AuditLogger::log('category.updated', "Category \"{$category->name}\" updated", $category, $old, $category->only(['name', 'description']));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(InventoryCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'This category is assigned to inventory items and cannot be deleted.');
        }
        AuditLogger::log('category.deleted', "Category \"{$category->name}\" deleted", $category);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?InventoryCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
