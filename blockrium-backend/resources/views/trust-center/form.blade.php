@include('admin.includes.header')
@include('admin.includes.sidebar')
@php $editing = !empty($section); @endphp
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><div class="row align-items-center"><div class="col-md-8"><h1>{{ $editing ? 'Edit Trust Section' : 'Add Trust Section' }}</h1><p>Use clear safety wording. Do not promise income, profit, or phone hardware mining.</p></div><div class="col-md-4"><div class="admin-header-actions"><a class="btn btn-admin-muted" href="{{ route('admin.trust-center.index') }}"><i class="fa-solid fa-arrow-left"></i>Sections</a></div></div></div></div></section>
  <section class="content"><div class="container-fluid">@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<div class="admin-panel"><form method="POST" action="{{ $editing ? route('admin.trust-center.update', $section->id) : route('admin.trust-center.store') }}">@csrf @if($editing) @method('PATCH') @endif
    <div class="row"><div class="col-md-6"><div class="form-group"><label>Key</label><input class="form-control" name="key" required value="{{ old('key', $section->key ?? '') }}" placeholder="wallet_safety"></div></div><div class="col-md-6"><div class="form-group"><label>Title</label><input class="form-control" name="title" required value="{{ old('title', $section->title ?? '') }}"></div></div></div>
    <div class="form-group"><label>Body</label><textarea class="form-control" name="body" rows="7" required>{{ old('body', $section->body ?? '') }}</textarea></div>
    <div class="row"><div class="col-md-6"><div class="form-group"><label>Sort Order</label><input type="number" min="0" class="form-control" name="sort_order" required value="{{ old('sort_order', $section->sort_order ?? 0) }}"></div></div><div class="col-md-6"><div class="form-group"><label>Status</label><input type="hidden" name="is_active" value="0"><div class="form-check mt-2"><input type="checkbox" class="form-check-input" id="trust_active" name="is_active" value="1" @if(old('is_active', $section->is_active ?? 1)) checked @endif><label class="form-check-label" for="trust_active">Active</label></div></div></div></div>
    <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i>Save Section</button>
  </form></div></div></section>
</div>
@include('admin.includes.footer')
