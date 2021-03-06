<!-- Navigation -->
<nav class="navbar navbar-default navbar-fixed-top navbar-shrink">
    <div class="container">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header page-scroll">
            <button type="button" class="navbar-toggle" data-toggle="collapse"
                    data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand page-scroll" href="{{ route('main') }}">{{ $teamData->name }}</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav navbar-right">
                <li class="{{ url()->current() == route('games') ? 'active' : '' }}">
                    <a class="page-scroll" href="{{ route('games') }}">{{ trans('frontend.menu.games') }}</a>
                </li>
                <li class="{{ url()->current() == route('stats') ? 'active' : '' }}">
                    <a class="page-scroll" href="{{ route('stats') }}">{{ trans('frontend.menu.stats') }}</a>
                </li>
                <li class="{{ url()->current() == route('schedule') ? 'active' : '' }}">
                    <a class="page-scroll" href="{{ route('schedule') }}">{{ trans('frontend.menu.schedule') }}</a>
                </li>
                <li class="{{ url()->current() == route('videos') ? 'active' : '' }}">
                    <a class="page-scroll" href="{{ route('videos') }}">{{ trans('frontend.menu.videos') }}</a>
                </li>
                @if(!$trainingData->isEmpty())
                    <li class="dropdown {{ strpos(url()->current(), 'training') !== false ? 'active' : '' }}">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#" role="button" area-haspopup="true"
                           aria-expanded="false">
                            {{ trans('frontend.menu.trainings') }} <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu">
                            @foreach($trainingData as $training)
                                <li>
                                    <a href="{{ route('training.visit', ['id' => $training->id]) }}">
                                        {{ $training->name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
                @if(Auth::check())
                    @if(Auth::user()->isAdmin())
                        <li>
                            <a class="page-scroll"
                               href="{{ route('admin.main') }}">{{ trans('frontend.menu.admin') }}</a>
                        </li>
                    @endif
                    <li>
                        <a class="page-scroll"
                           href="{{ route('logout') }}">{{ trans('frontend.menu.logout') }}</a>
                    </li>
                @else
                    <li>
                        <a class="page-scroll" href="{{ route('auth') }}">{{ trans('frontend.menu.login') }}</a>
                    </li>
                @endif
            </ul>
        </div>
        <!-- /.navbar-collapse -->
    </div>
    <!-- /.container-fluid -->
</nav>