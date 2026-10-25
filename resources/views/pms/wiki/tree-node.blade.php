@php($children = $pages->where('parent_id',$node->id))
@if($children->isNotEmpty() && $depth < 12)
    <details @if($depth < 2) open @endif><summary><a href="{{ route('pms.wiki.pages.show',$node) }}">{{ $node->title }}</a></summary>
        @foreach($children as $child)
            @include('pms.wiki.tree-node',['node'=>$child,'pages'=>$pages,'depth'=>$depth+1])
        @endforeach
    </details>
@else
    <a href="{{ route('pms.wiki.pages.show',$node) }}">{{ $node->title }}</a>
@endif
