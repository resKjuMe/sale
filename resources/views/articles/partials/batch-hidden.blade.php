@csrf
<input type="hidden" name="back" value="{{ $back }}">
@foreach ($articles as $article)
    <input type="hidden" name="ids[]" value="{{ $article->id }}">
@endforeach
