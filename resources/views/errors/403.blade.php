@extends('errors.layout')

@section('code', '403')
@section('title', 'You do not have access to this')
@section('message')
    {{ $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'Your role does not allow this action. Ask an administrator if you think it should.' }}
@endsection
