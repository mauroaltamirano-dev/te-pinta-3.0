<?php

namespace App\Modules\Instagram\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class StoreInstagramStoryAssetFile
{
    /** @return array{file_path:string,mime_type:string,width:int,height:int} */
    public function execute(UploadedFile $file): array
    {
        $source = $file->getRealPath();
        $imageInfo = $source !== false ? @getimagesize($source) : false;
        if ($imageInfo === false || ! in_array($imageInfo['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('El archivo subido no es una imagen JPEG o PNG válida.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        if ($width < 1 || $height < 1 || ($width * $height) > 20_000_000) {
            throw ValidationException::withMessages([
                'image' => 'La imagen supera el límite seguro de procesamiento de 20 megapíxeles.',
            ]);
        }

        $path = 'instagram/stories/'.Str::uuid().'.jpg';
        $disk = Storage::disk((string) config('instagram.media_disk'));

        $image = $imageInfo['mime'] === 'image/jpeg'
            ? @imagecreatefromjpeg($source)
            : @imagecreatefrompng($source);
        if ($image === false) {
            throw new RuntimeException('No se pudo decodificar la imagen.');
        }

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white === false) {
            imagedestroy($image);
            imagedestroy($canvas);
            throw new RuntimeException('No se pudo inicializar el fondo de la imagen.');
        }

        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        $encoded = imagejpeg($canvas, null, 92);
        $contents = ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        if (! $encoded || ! is_string($contents) || ! $disk->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar la imagen de la historia de Instagram.');
        }

        return ['file_path' => $path, 'mime_type' => 'image/jpeg', 'width' => $width, 'height' => $height];
    }
}
