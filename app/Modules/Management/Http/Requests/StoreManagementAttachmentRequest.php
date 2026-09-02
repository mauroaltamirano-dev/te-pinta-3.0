<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Models\ManagementAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

final class StoreManagementAttachmentRequest extends FormRequest
{
    /** @var array<string, list<string>> */
    private const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('create', [ManagementAttachment::class, $this->route('financialTransaction')]) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['file' => ['required', File::types(array_keys(self::MIME_BY_EXTENSION))->max(10 * 1024)]];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $name = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $mime = $file->getMimeType();
            if (mb_strlen($name) > 255 || $name === '' || preg_match('/[\\x00-\\x1F\\x7F\\\\\/]/u', $name) === 1) {
                $validator->errors()->add('file', 'El nombre del archivo no es válido.');

                return;
            }
            if (! isset(self::MIME_BY_EXTENSION[$extension]) || ! is_string($mime) || ! in_array($mime, self::MIME_BY_EXTENSION[$extension], true)) {
                $validator->errors()->add('file', 'La extensión no coincide con el contenido real del archivo.');

                return;
            }

            $path = $file->getRealPath();
            if ($path === false || ! $this->hasValidSignature($path, $mime)) {
                $validator->errors()->add('file', 'El contenido del archivo no es válido.');
            }
        });
    }

    private function hasValidSignature(string $path, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                return false;
            }
            $signature = fread($handle, 5);
            fclose($handle);

            return $signature === '%PDF-';
        }

        $image = @getimagesize($path);

        return is_array($image) && $image['mime'] === $mime;
    }
}
