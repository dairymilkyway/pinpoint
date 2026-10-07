@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', 'That request could not be completed')
@section('message')
    Something about the request was not right. Go back and try again.
@endsection
