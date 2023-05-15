@extends('layouts.app')

@section('content')
    <agora-chat :allusers="{{ $users }}" authuserid="{{ auth()->id() }}" authuser="{{ auth()->user()->name }}"
        agora_id="{{ env('AGORA_APP_ID') }}"                                      :user="{{ $user }}" :others="{{ $others }}" pusher-key="{{ config('broadcasting.connections.pusher.key') }}" pusher-cluster="{{ config('broadcasting.connections.pusher.options.cluster') }}" />



@endsection
