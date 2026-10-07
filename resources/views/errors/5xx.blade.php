@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', 'Something went wrong on our side')
@section('message')
    An unexpected error stopped this request. Please try again in a moment.
@endsection
