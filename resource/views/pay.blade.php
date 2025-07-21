<!-- resources/views/gateways/tripay/pay.blade.php -->
@extends('layouts.app') {{-- Adjust to your layout --}}

@section('content')
<div class="container text-center my-5">
    <h3>Pay with Tripay</h3>
    <p>Please click the button below to proceed to payment</p>
    <a href="{{ $paymentUrl }}" class="btn btn-primary btn-lg" target="_blank" rel="noopener noreferrer">
        Pay Now
    </a>
</div>
@endsection
