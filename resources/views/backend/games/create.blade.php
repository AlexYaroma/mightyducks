@extends('backend.main')

@section('content')

@include('backend.partitions.breadcrumbs', ['route' => 'admin.games',
'parent_title' => trans('backend.games.list.title'), 'title' => trans('backend.games.create.title')])

<div class="row">
    <div class="col-lg-12">
        <h1 class="page-header">{{ trans('backend.games.create.title') }}</h1>
    </div>
    <!-- /.col-lg-12 -->
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                {{ trans('backend.games.create.subtitle') }}
            </div>
            <div class="panel-body">

                @include('backend.partitions.errors')

                <div class="row">
                    <div class="col-lg-12">
                        {!! Form::open(['route' => ['admin.games.store']]) !!}
                            @include('backend.games.partitions.form')
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /.col-lg-12 -->
</div>
@endsection