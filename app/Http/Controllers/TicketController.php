<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

use Zxing\QrReader;

class TicketController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'ticket_image' => 'required',
        ]);

        $fileName = 'ticket_' . date('Ymd-His') . '.jpg';
        $fullPath = null;

        // ✅ Guardar la imagen subida (igual que ya hacías)
        if ($request->hasFile('ticket_image')) {
            $path = $request->file('ticket_image')->store('temp_tickets');
            $fullPath = storage_path('app/' . $path);
        } elseif ($request->has('ticket_image')) {
            $imageData = $request->input('ticket_image');
            $image = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            $image = str_replace(' ', '+', $image);

            $filePath = storage_path('app/temp_tickets/' . $fileName);
            file_put_contents($filePath, base64_decode($image));

            $fullPath = $filePath;
        }

        $fechaObj = null;

        // 🔹 1) Intentar leer QR
        try {
            $qrcode = new QrReader($fullPath);
            $text = $qrcode->text();

            if ($text) {
                // Los QR de AFIP vienen en JSON
                $data = json_decode($text, true);
                if (isset($data['fecha'])) {
                    $fechaObj = \DateTime::createFromFormat('Y-m-d', $data['fecha']);
                }
            }
        } catch (\Exception $e) {
            // si no hay QR o falla, seguimos al OCR
        }

        // 🔹 2) Si no hubo fecha en QR → OCR
        if (!$fechaObj) {
            $image = new \Imagick($fullPath);
            $image->setImageResolution(300, 300);
            $image->setImageUnits(\Imagick::RESOLUTION_PIXELSPERINCH);
            $image->setImageFormat('tiff');
            $image->modulateImage(100, 0, 100);
            $image->enhanceImage();
            $image->contrastStretchImage(0.1, 0.9);
            $preprocessedPath = storage_path('app/temp_tickets/pre_' . basename($fullPath));
            $image->writeImage($preprocessedPath);
            $image->clear();
            $image->destroy();
            $fullPath = $preprocessedPath;

            $ocrText = (new TesseractOCR($fullPath))
                ->executable('C:\Program Files\Tesseract-OCR\tesseract.exe')
                ->lang('spa')
                ->psm(6)
                ->oem(3)
                ->run();

            if (preg_match_all('/\b(\d{2}\/\d{2}\/(\d{2}|\d{4}))\b/', $ocrText, $matches)) {
                $fechasValidas = [];
                foreach ($matches[1] as $fechaStr) {
                    $fechaObjTmp = \DateTime::createFromFormat('d/m/Y', $fechaStr);
                    if (!$fechaObjTmp) {
                        $fechaObjTmp = \DateTime::createFromFormat('d/m/y', $fechaStr);
                    }
                    if ($fechaObjTmp !== false) {
                        $anio = (int)$fechaObjTmp->format('Y');
                        if ($anio >= 2000 && $anio <= date('Y') + 1) {
                            $fechasValidas[] = $fechaObjTmp;
                        }
                    }
                }
                if (!empty($fechasValidas)) {
                    usort($fechasValidas, fn($a, $b) => $a <=> $b);
                    $fechaObj = end($fechasValidas);
                }
            }
        }

        // 🔹 3) Nombre final
        if ($fechaObj) {
            $fileName = 'ticket_' . $fechaObj->format('Ymd-His') . '.jpg';
        } else {
            $fileName = 'ticket_' . date('Ymd-His') . '.jpg';
        }

        // 🔹 4) Subir a Google Drive (igual que tenías)
        $client = new \Google_Client();
        $client->setAuthConfig(storage_path('app/' . env('GOOGLE_SERVICE_ACCOUNT_JSON')));
        $client->addScope(\Google_Service_Drive::DRIVE_FILE);

        $service = new \Google_Service_Drive($client);

        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $fileName,
            'parents' => [env('GOOGLE_DRIVE_FOLDER_ID')],
        ]);

        $content = file_get_contents($fullPath);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => mime_content_type($fullPath),
            'uploadType' => 'multipart',
            'fields' => 'id, name',
            'supportsAllDrives' => true
        ]);

        return redirect()->route('tickets.form')
            ->with('success', 'Archivo subido como: ' . $fileName);
    }
}
