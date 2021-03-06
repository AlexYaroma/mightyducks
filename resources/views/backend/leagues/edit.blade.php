@extends('backend.main')

@section('content')

    @include('backend.partitions.breadcrumbs', ['route' => 'admin.leagues',
        'parent_title' => trans('backend.leagues.list.title'), 'title' => trans('backend.leagues.edit.title')])

    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">{{ trans('backend.leagues.edit.title') }}</h1>
        </div>
        <!-- /.col-lg-12 -->
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    {{ trans('backend.leagues.edit.subtitle') }}
                </div>
                <div class="panel-body">

                    @include('backend.partitions.errors')

                    <div class="row">
                        <div class="col-lg-12">
                            {!! Form::model($league, ['method' => 'PATCH', 'files' => true, 'route' => ['admin.leagues.update', $league->id]]) !!}
                                @include('backend.leagues.partitions.form')
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- /.col-lg-12 -->
    </div>
@endsection