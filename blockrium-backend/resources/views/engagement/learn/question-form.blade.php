@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><div class="row align-items-center"><div class="col-md-8"><h1>Edit Question</h1><p>{{ $module->title }}</p></div><div class="col-md-4"><div class="admin-header-actions"><a class="btn btn-admin-muted" href="{{ route('admin.engagement.learn.questions', $module->id) }}"><i class="fa-solid fa-arrow-left"></i>Questions</a></div></div></div></div></section>
  <section class="content"><div class="container-fluid">@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<div class="admin-panel"><form method="POST" action="{{ route('admin.engagement.learn.questions.update', [$module->id, $question->id]) }}">@csrf @method('PATCH')
    <div class="form-group"><label>Question</label><textarea class="form-control" name="question" rows="2" required>{{ old('question', $question->question) }}</textarea></div>
    <div class="row"><div class="col-md-6"><div class="form-group"><label>Option A</label><input class="form-control" name="option_a" required value="{{ old('option_a', $options['A'] ?? '') }}"></div></div><div class="col-md-6"><div class="form-group"><label>Option B</label><input class="form-control" name="option_b" required value="{{ old('option_b', $options['B'] ?? '') }}"></div></div></div>
    <div class="row"><div class="col-md-6"><div class="form-group"><label>Option C</label><input class="form-control" name="option_c" required value="{{ old('option_c', $options['C'] ?? '') }}"></div></div><div class="col-md-6"><div class="form-group"><label>Option D</label><input class="form-control" name="option_d" required value="{{ old('option_d', $options['D'] ?? '') }}"></div></div></div>
    <div class="row"><div class="col-md-4"><div class="form-group"><label>Correct Option</label><select class="form-control" name="correct_option" required>@foreach(['A','B','C','D'] as $option)<option value="{{ $option }}" @if(old('correct_option', $question->correct_option) === $option) selected @endif>{{ $option }}</option>@endforeach</select></div></div><div class="col-md-4"><div class="form-group"><label>Sort Order</label><input type="number" min="0" class="form-control" name="sort_order" required value="{{ old('sort_order', $question->sort_order) }}"></div></div></div>
    <div class="form-group"><label>Explanation</label><textarea class="form-control" name="explanation" rows="2">{{ old('explanation', $question->explanation) }}</textarea></div>
    <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i>Save Question</button>
  </form></div></div></section>
</div>
@include('admin.includes.footer')
