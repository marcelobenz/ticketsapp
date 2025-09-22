<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'ticket_image' => 'required|image|max:5120', // max 5MB, ajustar si hace falta
        ]);

        // Guardar temporalmente en storage (opcional)
        $path = $request->file('ticket_image')->store('temp_tickets');

        $fullPath = storage_path('app/' . $path);

        // Inicializar cliente Google
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/' . env('GOOGLE_SERVICE_ACCOUNT_JSON')));
        $client->addScope(Google_Service_Drive::DRIVE_FILE);
        // Si querés acceso completo usa Google_Service_Drive::DRIVE

        $service = new Google_Service_Drive($client);

        // Preparar metadata del archivo (nombre + carpeta destino)
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => 'ticket_' . date('Ymd-His') . '.' . pathinfo($fullPath, PATHINFO_EXTENSION),
            'parents' => [env('GOOGLE_DRIVE_FOLDER_ID')],
        ]);

        // Subir archivo (media upload)
        $content = file_get_contents($fullPath);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => mime_content_type($fullPath),
            'uploadType' => 'multipart',
            'fields' => 'id, name, mimeType, parents',
            'supportsAllDrives' => true
        ]);

        // opcional: obtener link para ver (hay que configurar permisos o crear permiso público)
        $fileId = $file->id;

        // Borrar temporal si querés
        Storage::delete($path);

        return redirect()->route('tickets.form')->with('success', 'Archivo subido a Google Drive con ID: ' . $fileId);
    }
}
