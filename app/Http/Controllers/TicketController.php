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
        // Si usás file normal, validación con image|file
        // Si usás cropper (base64), validás como string
        $request->validate([
            'ticket_image' => 'required',
        ]);

        $fullPath = null;
        $fileName = 'ticket_' . date('Ymd-His') . '.jpg';

        if ($request->hasFile('ticket_image')) {
            // ✅ Caso archivo subido normalmente
            $path = $request->file('ticket_image')->store('temp_tickets');
            $fullPath = storage_path('app/' . $path);
        } elseif ($request->has('ticket_image')) {
            // ✅ Caso imagen recortada en base64 desde Cropper.js
            $imageData = $request->input('ticket_image');
            $image = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            $image = str_replace(' ', '+', $image);

            $filePath = storage_path('app/temp_tickets/' . $fileName);
            file_put_contents($filePath, base64_decode($image));

            $fullPath = $filePath;
        }

        // Inicializar cliente Google
        $client = new \Google_Client();
        $client->setAuthConfig(storage_path('app/' . env('GOOGLE_SERVICE_ACCOUNT_JSON')));
        $client->addScope(\Google_Service_Drive::DRIVE_FILE);

        $service = new \Google_Service_Drive($client);

        // Preparar metadata
        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [env('GOOGLE_DRIVE_FOLDER_ID')],
        ]);

        // Subir
        $content = file_get_contents($fullPath);
        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => mime_content_type($fullPath),
            'uploadType' => 'multipart',
            'fields' => 'id, name, mimeType, parents',
            'supportsAllDrives' => true,
        ]);

        $fileId = $file->id;

        return redirect()->route('tickets.form')
            ->with('success', 'Archivo subido a Google Drive con nombre: ' . $fileName);
    }
}
