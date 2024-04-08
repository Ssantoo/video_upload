<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;


class StorageService
{
    protected  function getStorage($storageName)
    {
        switch ($storageName) {
            case 'aws':
                return 's3';
            case 'ceph':
                return 'cpeh';
            case 'minio':
                return 'minio';
            default:
                return 'local';
        }
    }

    public function uploadChunk($storage, $file, $key, $chunkNumber, $totalChunks)
    {
        $originalExtension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $filename = $file->getClientOriginalName();
        $tempFilePath = "chunks/{$key}/{$filename}.part{$chunkNumber}";
        Storage::disk('local')->put($tempFilePath, file_get_contents($file));

        if($chunkNumber == $totalChunks -1){
            $finalFilePath = $this->combineChunks('local', $key, $filename, $totalChunks, $originalExtension);

            $disk = $this->getStorage($storage);
            $this-> uploadToCloudStorage($disk, $key, $finalFilePath, $originalExtension);
            return true;
        }

        return false;
    }

    protected function combineChunks($disk, $key, $filename, $totalChunks, $extension)
    {
        $finalFileName = rtrim("{$key}.{$extension}", '.');
        $finalFilePath = "upload/{$finalFileName}";
        $localPath = "/root/{$finalFileName}";
        $outStream = fopen($localPath, 'ab');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFilePath = "chunks/{$key}/{$filename}.part{$i}";
            $chunkStream = Storage::disk('local')->readStream($chunkFilePath);

            stream_copy_to_stream($chunkStream, $outStream);
            fclose($chunkStream);

            Storage::disk($disk)->delete($chunkFilePath);
        }
        fclose($outStream);

        return $localPath;
    }


    protected function uploadToCloudStorage($disk, $key, $localPath, $extension)
    {
        $encryptedFileName = $this->Encrypt($key);
        $finalFileNameWithExtension = $encryptedFileName . (!empty($extension) ? ".{$extension}" : '');

        $finalS3Path = "upload/{$encryptedFileName}/{$finalFileNameWithExtension}";

        Storage::disk($disk)->put($finalS3Path, fopen($localPath, 'r+'), 'public');

        @unlink($localPath);
    }

    protected function Encrypt($key)
    {
        $encryptionKey = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

        $encryptedKey = openssl_encrypt($key, 'aes-256-cbc', $encryptionKey, 0, $iv);

        return base64_encode($encryptedKey);
    }



}
