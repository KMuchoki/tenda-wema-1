@extends('layouts.email')

@section('content')
	<p>Dear {{ $user->fname }},</p>

	<p>
		You have advanced to <strong>{{ ucfirst(strtolower($user->social_level)) }} Social Level</strong>. You have been awarded <strong>{{ number_format($amount) }} Simba Coins</strong> for advancing. This is your new badge to let other members know your status.
	</p> <br>

    <p class="text-center">
        <img src="{{ $user->badge() }}" alt="" style="max-width: 250px; height: auto">
    </p>
	
	

@endsection