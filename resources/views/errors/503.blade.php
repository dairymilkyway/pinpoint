@extends('errors.layout')

@section('code', '503')
@section('title', 'Down for maintenance')
@section('message')
    Pinpoint is being updated and will be back shortly.
@endsection

@section('actions')
    <button type="button" class="btn btn--primary" onclick="location.reload()">Try again</button>
@endsection
