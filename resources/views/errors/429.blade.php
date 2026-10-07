@extends('errors.layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('message')
    {{ isset($exception) && ($exception->getHeaders()['Retry-After'] ?? null) ? 'Please wait '.$exception->getHeaders()['Retry-After'].' seconds and try again.' : 'Please wait a moment and try again.' }}
@endsection
