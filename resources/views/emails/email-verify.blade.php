@extends('layouts.email')

@section('content')
	<p>{{ $user->fname }},</p>

	<p>
		You need to verify your email in order to get full access to {{ config('app.name') }} Website. Please click the link below to verify your email.
	</p> <br>

	<p class="text-center">
		<a href="{{ route('auth.email.verify', ['token' => $user->email_token]) }}" class="btn btn-info">Verify Email</a>
	</p> <br>

	<p>
		If for some reason the button does not work, please copy and paste this link to your browser. <br>
		<a href="{{ route('auth.email.verify', ['token' => $user->email_token]) }}">{{ route('auth.email.verify', ['token' => $user->email_token]) }}</a>

	</p>

@endsection