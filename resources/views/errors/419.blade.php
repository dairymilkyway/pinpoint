@extends('errors.layout')

@section('code', '419')
@section('title', 'Your session expired')
@section('message')
    The form was open too long and its security token expired. Go back, reload the page, and try again.
@endsection

@section('actions')
    <button type="button" class="btn btn--primary" onclick="history.back()">Go back</button>
@endsection
