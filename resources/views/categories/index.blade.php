@extends('layouts.app')
@section('title', 'Categories')

@section('content')
<x-page-header title="Inventory Categories" />

<div class="row g-3">
    <div class="col-lg-4">
        <form method="POST" action="{{ route('categories.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Add category</h2></div>
            <div class="card-body">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" value="{{ old('name') }}" class="form-control mb-2" required maxlength="100">
                <label class="form-label" for="description">Description</label>
                <input id="description" name="description" value="{{ old('description') }}" class="form-control" maxlength="255">
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Name</th><th>Description</th><th class="num">Items</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse($categories as $c)
                        <tr>
                            <td colspan="2">
                                <form method="POST" action="{{ route('categories.update', $c) }}" class="row g-1" id="cat{{ $c->id }}">
                                    @csrf @method('PUT')
                                    <div class="col-md-5"><input name="name" value="{{ $c->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="Category name"></div>
                                    <div class="col-md-7"><input name="description" value="{{ $c->description }}" class="form-control form-control-sm" maxlength="255" aria-label="Description"></div>
                                </form>
                            </td>
                            <td class="num">{{ $c->items_count }}</td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-light border" type="submit" form="cat{{ $c->id }}">Save</button>
                                <form method="POST" action="{{ route('categories.destroy', $c) }}" class="d-inline" data-confirm="Delete category “{{ $c->name }}”?" data-confirm-variant="danger" data-confirm-button="Delete">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" @disabled($c->items_count > 0) title="{{ $c->items_count > 0 ? 'In use by items' : 'Delete' }}"><i class="bi bi-trash"></i><span class="visually-hidden">Delete</span></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="tags" message="No categories yet." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
