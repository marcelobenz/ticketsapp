@extends('layouts.app')

@section('title', 'Subir Ticket')

@section('content')
    <div class="container">
        <h3>Subir ticket / factura</h3>
        <form action="{{ route('tickets.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <input type="file" name="ticket_image" accept="image/*" capture="environment" required>
            </div>
            <button class="btn btn-primary">Subir a Google Drive</button>
        </form>

        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif
    </div>
@endsection
