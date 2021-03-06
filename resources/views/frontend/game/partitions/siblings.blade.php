<div class="col-md-1 text-left">
    @if(isset($siblings['prev']->id))
        <a href="{{ route('game.visit', ['id' => $siblings['prev']->id]) }}" class="prev-game">
            <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
        </a>
    @endif
</div>
<div class="col-md-10 text-center">
    {{ trans('frontend.game.move_to_sibling') }}
</div>
<div class="col-md-1 text-right">
    @if(isset($siblings['next']->id))
        <a href="{{ route('game.visit', ['id' => $siblings['next']->id]) }}" class="next-game">
            <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
        </a>
    @endif
</div>