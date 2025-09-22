@extends('layouts.app')

@section('content')
    <div class="container">
        <h3>Subir ticket / factura</h3>

        <!-- Input de archivo -->
        <input type="file" id="ticketInput" accept="image/*" capture="environment">

        <!-- Donde se mostrará la imagen a recortar -->
        <div class="mt-3">
            <img id="preview" style="max-width:100%; display:none;">
        </div>
        <!-- 🔹 Mensaje de éxito -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif
        <!-- Botón para recortar y subir -->
        <form id="uploadForm" action="{{ route('tickets.upload') }}" method="POST">
            @csrf
            <input type="hidden" name="ticket_image" id="croppedImage">
            <button type="submit" class="btn btn-primary mt-3">Subir a Google Drive</button>
        </form>

    </div>

    <!-- Importar CropperJS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

    <script>
        let cropper;
        const ticketInput = document.getElementById('ticketInput');
        const preview = document.getElementById('preview');
        const croppedInput = document.getElementById('croppedImage');

        ticketInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    preview.style.display = 'block';

                    // Destruir cropper anterior si existe
                    if (cropper) cropper.destroy();

                    // Iniciar Cropper
                    cropper = new Cropper(preview, {
                        aspectRatio: NaN, // libre (puede ser 1 si querés cuadrado, 16/9, etc.)
                        viewMode: 1,
                        responsive: true
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        // Al enviar el formulario, convertir recorte en blob
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Evita que mande el form directo

            if (cropper) {
                cropper.getCroppedCanvas().toBlob((blob) => {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        croppedInput.value = reader.result; // Base64
                        e.target.submit(); // Enviar form con imagen recortada
                    };
                    reader.readAsDataURL(blob);
                }, 'image/jpeg');
            }
        });
    </script>
@endsection
